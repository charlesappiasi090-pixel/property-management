<?php

namespace Tests\Feature;

use App\Enums\PermissionName;
use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Models\Business;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\SubscriptionState;
use App\Services\Tenancy\BusinessMembershipService;
use App\Support\Tenancy\BusinessContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\Concerns\SeedsPermissions;
use Tests\TestCase;

/**
 * Tenant isolation, policy authorization, and the atomicity of onboarding.
 *
 * These are the tests that matter most for a multi-tenant application: a bug
 * here leaks one landlord's data into another's screen, which is the failure
 * mode that ends the product. The happy-path tests elsewhere cannot catch it,
 * because a leaky query still returns rows — just the wrong ones.
 *
 * A NOTE ON HOW THESE TESTS SET UP TENANTS
 * ---------------------------------------
 * Every business here is created through `createBusinessWithOwner()`, and
 * every role is granted through `BusinessMembershipService::attach()`. That is
 * deliberate. A hand-rolled `DB::table('business_user')->insert()` would skip
 * the Spatie team id, the per-business role row, and the pivot team column, and
 * the test would then pass for a fixture the application cannot produce.
 *
 * It also means the ACTIVE business is stated explicitly with `actingIn()`
 * before any authorization assertion. Spatie resolves roles per "team", and the
 * team id is global mutable state, so a check made while the wrong business is
 * active is not testing anything — it is testing which fixture ran last.
 */
class TenancyTest extends TestCase
{
    use RefreshDatabase;
    use SeedsPermissions;

    private BusinessMembershipService $memberships;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();
        $this->memberships = app(BusinessMembershipService::class);
    }

    /**
     * A trialing business owned by `$owner`.
     */
    private function business(string $name = 'Test Landlord', ?User $owner = null): Business
    {
        $plan = Plan::factory()->create();

        $business = $this->memberships->createBusinessWithOwner(
            owner: $owner ?? User::factory()->create(),
            name: $name,
        );

        app(SubscriptionState::class)->startTrial($business, $plan);

        return $business;
    }

    /**
     * A business with a trialing subscription, and `$user` running it as
     * `$role`, already signed in and already inside that tenant.
     */
    private function tenant(Role $role, string $name = 'Test Landlord'): Business
    {
        $business = $this->business($name);

        $user = $role === Role::OWNER
            ? $business->owners()->first()
            : User::factory()->create();

        if ($user->id !== $business->owners()->first()?->id) {
            $this->memberships->attach($business, $user, $role);
        }

        $this->actingIn($business, $user);

        return $business;
    }

    /**
     * Sign `$user` in with `$business` as the active tenant.
     *
     * `withSession()` is what the middleware reads; setting the session alone
     * is enough for a request, but the assertions below also call
     * `hasRoleInBusiness()` and `can()` directly, which need the Spatie team id
     * set too. Doing both here means a test can never accidentally assert
     * against the tenant that happens to be left over from a previous test.
     *
     * Pass `null` for the "signed in but owns nothing yet" state that
     * onboarding starts from.
     */
    private function actingIn(?Business $business, User $user): User
    {
        $this->actingAs($user);
        $this->withSession(['business_id' => $business?->getKey()]);

        app(BusinessContext::class)->set($business);

        return $user;
    }

    /* ------------------------------------------------------------------ */
    /* Membership and role scoping                                         */
    /* ------------------------------------------------------------------ */

    /**
     * A role assigned in one business must never be visible in another.
     *
     * This is the Spatie teams feature doing its job. If it regresses, an owner
     * of landlord A would inherit landlord B's staff-management rights the
     * moment they switched workspace.
     */
    public function test_a_role_does_not_leak_across_businesses(): void
    {
        $a = $this->tenant(Role::OWNER, 'Landlord A');
        $user = $a->owners()->first();

        $b = $this->business('Landlord B');
        $this->memberships->attach($b, $user, Role::ACCOUNTANT);

        // As the owner of A, the user has A's full permission set.
        $this->actingIn($a, $user);
        $this->assertTrue($user->hasRoleInBusiness(Role::OWNER));
        $this->assertTrue($user->can(PermissionName::STAFF_INVITE->value));
        $this->assertTrue($user->can(PermissionName::SUBSCRIPTION_MANAGE->value));

        // Switched to B, they are an accountant: no staff management, no
        // billing, and definitely not an owner.
        $this->actingIn($b, $user);
        $this->assertTrue($user->hasRoleInBusiness(Role::ACCOUNTANT));
        $this->assertFalse(
            $user->hasRoleInBusiness(Role::OWNER),
            'an owner of business A was treated as an owner of business B'
        );
        $this->assertFalse(
            $user->can(PermissionName::STAFF_INVITE->value),
            'an accountant inherited staff.invite from another landlord'
        );
        $this->assertFalse($user->can(PermissionName::SUBSCRIPTION_MANAGE->value));
    }

    /**
     * A tenant's portal permissions must not grant back-office access.
     */
    public function test_b_tenant_cannot_reach_the_back_office(): void
    {
        $this->tenant(Role::TENANT);

        $response = $this->get('/app');

        $response->assertRedirect(route('portal.home'));

        $this->assertFalse(
            auth()->user()->can(PermissionName::STAFF_VIEW->value),
            'a tenant was granted staff.view'
        );
    }

    /**
     * The business list a user can switch into contains only real memberships.
     */
    public function test_c_available_businesses_are_limited_to_memberships(): void
    {
        $mine = $this->tenant(Role::OWNER, 'Mine');
        $user = auth()->user();

        // A business the user is NOT a member of.
        $this->business('Not Mine');

        $names = $user->availableBusinesses()->pluck('name');

        $this->assertTrue($names->contains('Mine'));
        $this->assertFalse(
            $names->contains('Not Mine'),
            'a user was offered a business they do not belong to'
        );
    }

    /**
     * `BelongsToBusiness` scopes a query to the active business, so a record
     * belonging to another landlord is not even fetched.
     *
     * The probe is a real (anonymous) model that opts into the trait, because
     * the trait installs a global scope in `booted()`. Querying the table with
     * `DB::table()` would bypass it entirely and prove nothing — the scope is
     * an Eloquent-level construct, not a database view.
     */
    public function test_d_belongs_to_business_scope_hides_foreign_rows(): void
    {
        $mine = $this->tenant(Role::OWNER, 'Mine');
        $theirs = $this->tenant(Role::OWNER, 'Theirs');
        $user = auth()->user();

        $probe = new class extends Model
        {
            use \App\Concerns\BelongsToBusiness;

            protected $table = 'business_user';

            public $timestamps = false;

            protected $guarded = [];
        };

        $scoped = fn (): array => (new $probe)->newQuery()
            ->pluck('business_user.business_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();

        // Active business is "Mine".
        $this->actingIn($mine, $user);
        $this->assertSame([$mine->id], $scoped(), 'the scope did not hide the other landlord\'s rows');

        // Active business is "Theirs". The same query returns the other tenant,
        // which is what proves the scope is following the context rather than
        // returning a constant.
        $this->actingIn($theirs, auth()->user());
        $this->assertSame([$theirs->id], $scoped());

        // With no business resolved the scope must FAIL CLOSED and return
        // nothing, rather than defaulting to "all rows".
        app(BusinessContext::class)->forget();
        $this->assertSame([], $scoped(), 'an unresolved tenant leaked every row');
    }

    /* ------------------------------------------------------------------ */
    /* Policy authorization                                                */
    /* ------------------------------------------------------------------ */

    public function test_e_property_manager_cannot_manage_staff(): void
    {
        $this->tenant(Role::PROPERTY_MANAGER);

        $response = $this->get('/app/staff/create');

        $this->assertSame(403, $response->getStatusCode(), 'redirected to: '.$response->headers->get('Location'));
    }

    public function test_f_owner_can_reach_staff_management(): void
    {
        $this->tenant(Role::OWNER);

        $response = $this->get('/app/staff/create');

        $this->assertSame(200, $response->getStatusCode(), 'redirected to: '.$response->headers->get('Location'));
    }

    public function test_g_a_user_with_no_business_is_sent_to_onboarding(): void
    {
        $this->tenant(Role::OWNER);

        // No membership anywhere: the only productive action is creating one,
        // so this is a redirect rather than a 403.
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->withSession(['business_id' => null])
            ->get('/app/staff')
            ->assertRedirect(route('onboarding.create'));
    }

    /**
     * An owner cannot invite somebody into a business they do not own.
     *
     * The route has no business in it, so the "which business" question is
     * answered by the context, not the URL. A `business_id` posted in the body
     * must be ignored entirely — it is not a validated field, so the only way
     * it can influence anything is if something reads it raw.
     */
    public function test_h_staff_cannot_be_added_to_a_foreign_business(): void
    {
        $mine = $this->tenant(Role::OWNER, 'Mine');
        $theirs = $this->business('Theirs');

        $invitee = User::factory()->create(['email' => 'intruder@example.test']);

        $this->post('/app/staff', [
            'name' => 'Intruder',
            'email' => 'intruder@example.test',
            'role' => Role::PROPERTY_MANAGER->value,
            'business_id' => $theirs->id,
        ])->assertRedirect(route('app.staff.index'));

        // The invite landed in the ACTIVE business, not the one named in the
        // request body.
        $this->assertDatabaseHas('business_user', [
            'business_id' => $mine->id,
            'user_id' => $invitee->id,
        ]);

        $this->assertDatabaseMissing('business_user', [
            'business_id' => $theirs->id,
            'user_id' => $invitee->id,
        ]);

        $this->assertFalse(
            $theirs->members()->where('users.id', $invitee->id)->exists(),
            'the invitee became a member of the business named in the request body'
        );
    }

    /**
     * The first member of a business cannot be added as anything but owner.
     *
     * A business with no owner is permanently unadministrable, so the service
     * refuses the write instead of quietly promoting whoever arrived first —
     * which would hand a brand-new tenant the full owner permission set.
     */
    public function test_i_first_member_must_be_an_owner(): void
    {
        $business = Business::factory()->create();
        $user = User::factory()->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('a business with no owner cannot be administered');

        try {
            $this->memberships->attach($business, $user, Role::TENANT);
        } finally {
            $this->assertSame(0, $business->members()->count());
            $this->assertDatabaseMissing('model_has_roles', [
                'model_id' => $user->id,
                'model_type' => $user->getMorphClass(),
            ]);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Onboarding atomicity                                                */
    /* ------------------------------------------------------------------ */

    /**
     * If any step of onboarding fails, none of it may persist.
     *
     * A half-created tenant — a business with no owner, or an owner with no
     * subscription — is unreachable and invisible, so the whole sequence has to
     * commit or roll back as one unit.
     *
     * `SubscriptionState` is stubbed to throw, which fails the step AFTER the
     * business and its owner membership have already been written. That is the
     * ordering that actually matters; making the plan lookup fail instead would
     * abort before any write happened and would prove nothing.
     */
    public function test_j_onboarding_rolls_back_completely_on_failure(): void
    {
        $this->seed(\Database\Seeders\PlanSeeder::class);

        $user = User::factory()->create();

        $this->actingIn(null, $user);

        $before = [
            'businesses' => Business::withTrashed()->count(),
            'subscriptions' => Subscription::count(),
        ];

        // The stub inherits SubscriptionState's AuditLogger dependency, so it
        // is constructed from the container like the real one would be.
        $exploding = new class(app(\App\Services\Audit\AuditLogger::class)) extends SubscriptionState
        {
            public function startTrial(Business $business, Plan $plan): Subscription
            {
                throw new RuntimeException('Simulated billing failure mid-onboarding.');
            }
        };

        app()->instance(SubscriptionState::class, $exploding);

        $this->withExceptionHandling();

        try {
            $this->post('/onboarding', [
                'name' => 'Doomed Landlord',
                'plan' => 'starter',
                'password' => 'Correct-Horse-7!',
                'password_confirmation' => 'Correct-Horse-7!',
            ])->assertStatus(500);
        } finally {
            $this->assertSame(
                $before['businesses'],
                Business::withTrashed()->count(),
                'a business survived a failed onboarding'
            );
            $this->assertSame(
                $before['subscriptions'],
                Subscription::count(),
                'a subscription survived a failed onboarding'
            );

            $this->assertDatabaseMissing('businesses', ['name' => 'Doomed Landlord']);
            $this->assertDatabaseMissing('model_has_roles', [
                'model_id' => $user->id,
                'model_type' => $user->getMorphClass(),
            ]);
        }
    }

    /**
     * A successful onboarding creates exactly one of each record and makes the
     * user that business's owner.
     */
    public function test_k_onboarding_creates_owner_membership_and_trial(): void
    {
        $this->seed(\Database\Seeders\PlanSeeder::class);

        $user = User::factory()->create();

        $this->actingIn(null, $user);

        $response = $this->post('/onboarding', [
            'name' => 'Fresh Landlord',
            'plan' => 'starter',
            'password' => 'Correct-Horse-7!',
            'password_confirmation' => 'Correct-Horse-7!',
        ]);

        $response->assertRedirect(route('app.dashboard'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('businesses', ['name' => 'Fresh Landlord']);
        $this->assertDatabaseHas('subscriptions', ['status' => SubscriptionStatus::TRIALING->value]);

        $business = Business::where('name', 'Fresh Landlord')->firstOrFail();

        $this->assertTrue(
            $user->fresh()->hasRoleInBusiness(Role::OWNER, $business),
            'the creator of the business is not its owner'
        );

        $this->assertSame(1, $business->members()->count());
    }

    /* ------------------------------------------------------------------ */
    /* Route wiring                                                        */
    /* ------------------------------------------------------------------ */

    /**
     * Guards against a route being registered that does not exist, which is
     * the failure mode behind the old `/` -> `dashboard` redirect loop.
     */
    public function test_l_named_routes_resolve(): void
    {
        $required = [
            'home', 'login', 'register', 'password.request', 'password.email',
            'password.reset', 'password.update', 'password.confirm',
            'verification.notice', 'verification.verify', 'verification.send',
            'onboarding.create', 'onboarding.store',
            'profile.edit', 'profile.update', 'profile.destroy',
            'app.dashboard', 'app.staff.index', 'app.staff.create',
            'app.staff.store', 'app.staff.edit', 'app.staff.update', 'app.staff.destroy',
            'app.subscription.show', 'app.subscription.cancel',
            'app.settings.business.edit', 'app.settings.business.update',
            'business.switch', 'portal.home', 'logout',
        ];

        $missing = array_values(array_filter($required, fn (string $name): bool => ! Route::has($name)));

        $this->assertSame([], $missing, 'unregistered routes: '.implode(', ', $missing));
    }

    /**
     * Switching workspaces is POST-only, membership-checked, and reflected in
     * the session — the three properties that make the switcher safe.
     */
    public function test_m_switching_business_requires_membership_and_a_post(): void
    {
        $mine = $this->tenant(Role::OWNER, 'Mine');
        $user = auth()->user();

        $theirs = $this->business('Theirs');

        // GET must not switch: a link, an image tag or a prefetch could
        // otherwise change the active tenant behind the user's back.
        $this->get(route('business.switch'))->assertStatus(405);

        // Not a member -> refused, and the active tenant is unchanged.
        $this->post(route('business.switch'), ['business_id' => $theirs->id])
            ->assertRedirect();

        // Asserted against the SESSION, not the live BusinessContext: the
        // ForgetActiveBusiness middleware tears the context down at the end of
        // every request, so a post-request context read is always null and
        // would hide the bug rather than show it. The session is the durable
        // state the next request actually revalidates from.
        $this->assertSame(
            $mine->id,
            session('business_id'),
            'a non-member changed the active tenant'
        );

        // A business that does not exist must be indistinguishable from one the
        // user simply does not belong to.
        $this->post(route('business.switch'), ['business_id' => 999999])
            ->assertRedirect()
            ->assertSessionHas('error');

        // The user genuinely belongs to the other business now.
        $this->memberships->attach($theirs, $user, Role::ACCOUNTANT);

        $this->post(route('business.switch'), ['business_id' => $theirs->id])
            ->assertRedirect(route('app.dashboard'));

        $this->assertSame($theirs->id, session('business_id'));
        $this->assertTrue($user->hasRoleInBusiness(Role::ACCOUNTANT, $theirs));

        // And the permission set really did change with the tenant: staff
        // management belonged to them as owner of A, and does not here.
        $this->assertFalse($user->can(PermissionName::STAFF_INVITE->value));
    }

    /**
     * A business that is not the user's own must not be reachable by writing the
     * session directly — the middleware revalidates every request.
     */
    public function test_n_a_forged_session_business_id_is_ignored(): void
    {
        $this->tenant(Role::OWNER, 'Mine');
        $user = auth()->user();

        $theirs = $this->business('Theirs');

        $outsider = User::factory()->create(['name' => 'Test Staff Of Theirs']);
        $this->memberships->attach($theirs, $outsider, Role::ACCOUNTANT);

        $response = $this->actingAs($user)
            ->withSession(['business_id' => $theirs->id])
            ->get('/app/staff');

        // Falls back to the user's own business rather than serving the forged
        // one, and never renders the other landlord's staff list.
        $response->assertOk();
        $response->assertDontSee('Test Staff Of Theirs');
    }
}
