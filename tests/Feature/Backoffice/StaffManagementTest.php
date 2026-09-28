<?php

namespace Tests\Feature\Backoffice;

use App\Enums\PermissionName;
use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Models\Business;
use App\Models\Plan;
use App\Models\User;
use App\Services\Billing\PlanQuota;
use App\Services\Billing\SubscriptionState;
use App\Services\Tenancy\BusinessMembershipService;
use App\Support\Tenancy\BusinessContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\Concerns\SeedsPermissions;
use Tests\TestCase;

/**
 * Staff management, exercised through the HTTP layer.
 *
 * WHY THESE TESTS EXIST
 * ---------------------
 * Staff screens are where privilege escalation is attempted, so the dangerous
 * cases are the point rather than the happy path:
 *
 *  - a user from ANOTHER business must not be editable, even by id;
 *  - nobody may hand out a role they do not hold themselves;
 *  - a landlord may not lock themselves out by removing the last owner;
 *  - and the permission that admits the request must be the one for the
 *    business being modified, not one held elsewhere.
 *
 * Each of those is a 404, 403 or validation error, and each is invisible in
 * a screenshot of the finished screen. They are asserted here.
 *
 * HOW THE FIXTURES ARE BUILT
 * --------------------------
 * Every business and every membership goes through
 * `BusinessMembershipService`, never a hand-written pivot insert, so the
 * Spatie team id and the per-business role row are created exactly the way the
 * application creates them. A fixture that the application cannot produce
 * would let a test pass against code paths no user can reach.
 */
class StaffManagementTest extends TestCase
{
    use RefreshDatabase;
    use SeedsPermissions;

    private BusinessMembershipService $memberships;

    private SubscriptionState $subscriptions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();
        $this->memberships = app(BusinessMembershipService::class);
        $this->subscriptions = app(SubscriptionState::class);
    }

    /* ------------------------------------------------------------------ */
    /* Fixtures                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * A trialing business owned by a fresh user, with `$plan` on it.
     */
    private function business(?Plan $plan = null, string $name = 'Test Landlord'): Business
    {
        $business = $this->memberships->createBusinessWithOwner(
            owner: User::factory()->create(),
            name: $name,
        );

        $this->subscriptions->startTrial($business, $plan ?? Plan::factory()->create());

        return $business;
    }

    /**
     * Add a member to `$business` with a brand-new account.
     */
    private function member(Business $business, Role $role): User
    {
        $user = User::factory()->create();

        $this->memberships->attach($business, $user, $role);

        return $user;
    }

    /**
     * Sign `$user` in with `$business` as the active tenant.
     *
     * Both halves are set for the same reason as in `TenancyTest`: the session
     * is what the middleware reads, and the Spatie team id is what any direct
     * authorization call in the test would read. Setting only one produces a
     * test that passes or fails depending on which fixture ran last.
     */
    private function actingIn(Business $business, User $user): User
    {
        $this->actingAs($user);
        $this->withSession(['business_id' => $business->getKey()]);

        app(BusinessContext::class)->set($business);

        return $user;
    }

    /* ------------------------------------------------------------------ */
    /* Adding a member                                                     */
    /* ------------------------------------------------------------------ */

    public function test_an_owner_can_add_an_existing_account_to_their_business(): void
    {
        $business = $this->business();
        $owner = $this->actingIn($business, $business->owners()->first());
        $invitee = User::factory()->create();

        $response = $this->actingAs($owner)
            ->post(route('app.staff.store'), [
                'email' => $invitee->email,
                'role' => Role::PROPERTY_MANAGER->value,
                'job_title' => 'Portfolio lead',
            ]);

        $response->assertRedirect(route('app.staff.index'));
        $response->assertSessionHas('success');

        // The invariant under test: a membership row AND a matching role, or
        // neither. A pivot row without a role is a user who can see the
        // business in their switcher and pass no permission check at all.
        $this->assertTrue($business->members()->whereKey($invitee->getKey())->exists());
        $this->assertSame(
            Role::PROPERTY_MANAGER,
            $this->memberships->currentRole($business, $invitee),
        );
        $this->assertDatabaseHas('business_user', [
            'business_id' => $business->getKey(),
            'user_id' => $invitee->getKey(),
            'job_title' => 'Portfolio lead',
        ]);
    }

    public function test_a_crafted_invite_cannot_grant_the_owner_role(): void
    {
        $business = $this->business();
        $owner = $this->actingIn($business, $business->owners()->first());
        $invitee = User::factory()->create();

        $response = $this->actingAs($owner)
            ->from(route('app.staff.create'))
            ->post(route('app.staff.store'), [
                'email' => $invitee->email,
                'role' => Role::OWNER->value,
            ]);

        $response->assertRedirect(route('app.staff.create'));
        $response->assertSessionHasErrors('role');

        $this->assertFalse(
            $business->members()->whereKey($invitee->getKey())->exists(),
            'A rejected invite must not create a membership row.'
        );
    }

    public function test_an_invite_for_an_address_with_no_account_is_refused(): void
    {
        $business = $this->business();
        $owner = $this->actingIn($business, $business->owners()->first());

        $response = $this->actingAs($owner)
            ->from(route('app.staff.create'))
            ->post(route('app.staff.store'), [
                'email' => 'nobody-here@example.test',
                'role' => Role::PROPERTY_MANAGER->value,
            ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_an_owner_cannot_invite_themselves(): void
    {
        $business = $this->business();
        $owner = $this->actingIn($business, $business->owners()->first());

        $response = $this->actingAs($owner)
            ->from(route('app.staff.create'))
            ->post(route('app.staff.store'), [
                'email' => $owner->email,
                'role' => Role::PROPERTY_MANAGER->value,
            ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_the_same_person_cannot_be_added_twice(): void
    {
        $business = $this->business();
        $owner = $this->actingIn($business, $business->owners()->first());
        $invitee = $this->member($business, Role::PROPERTY_MANAGER);

        $response = $this->actingAs($owner)
            ->from(route('app.staff.create'))
            ->post(route('app.staff.store'), [
                'email' => $invitee->email,
                'role' => Role::ACCOUNTANT->value,
            ]);

        $response->assertSessionHasErrors('email');

        // The second attempt must not have changed the existing role either.
        $this->assertSame(
            Role::PROPERTY_MANAGER,
            $this->memberships->currentRole($business, $invitee),
        );
    }

    public function test_a_property_manager_may_not_invite_even_though_they_can_view_the_roster(): void
    {
        $business = $this->business();
        $manager = $this->member($business, Role::PROPERTY_MANAGER);
        $this->actingIn($business, $manager);

        // `staff.view` is granted to a property manager; `staff.invite` is not.
        // Confirming the first half is what makes this a real test of the
        // second: a manager who cannot even load the page would trivially pass.
        $this->get(route('app.staff.index'))->assertOk();

        $this->post(route('app.staff.store'), [
            'email' => User::factory()->create()->email,
            'role' => Role::ACCOUNTANT->value,
        ])->assertForbidden();
    }

    public function test_adding_a_member_respects_the_plan_staff_quota(): void
    {
        // max_staff = 2 in the factory, and the owner already occupies a slot.
        $plan = Plan::factory()->create(['max_staff' => 2]);
        $business = $this->business($plan);
        $owner = $this->actingIn($business, $business->owners()->first());
        $this->member($business, Role::PROPERTY_MANAGER);

        $this->assertSame(0, app(PlanQuota::class)->remaining($business, 'staff'));

        $response = $this->actingAs($owner)->post(route('app.staff.store'), [
            'email' => User::factory()->create()->email,
            'role' => Role::ACCOUNTANT->value,
        ]);

        // A quota failure is a user error, not a server error, so it must come
        // back as a redirect with a message rather than an unhandled throw.
        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertSame(2, $business->members()->count());
    }

    /* ------------------------------------------------------------------ */
    /* Changing a role                                                     */
    /* ------------------------------------------------------------------ */

    public function test_an_owner_can_change_a_members_role(): void
    {
        $business = $this->business();
        $owner = $this->actingIn($business, $business->owners()->first());
        $accountant = $this->member($business, Role::ACCOUNTANT);

        $response = $this->actingAs($owner)->put(route('app.staff.update', $accountant), [
            'role' => Role::PROPERTY_MANAGER->value,
            'job_title' => 'Senior manager',
        ]);

        $response->assertRedirect(route('app.staff.index'));
        $this->assertSame(
            Role::PROPERTY_MANAGER,
            $this->memberships->currentRole($business, $accountant->refresh()),
        );
        $this->assertDatabaseHas('business_user', [
            'business_id' => $business->getKey(),
            'user_id' => $accountant->getKey(),
            'job_title' => 'Senior manager',
        ]);
    }

    public function test_an_owner_cannot_change_their_own_role(): void
    {
        /*
         * Two owners, so this is NOT the last-owner guard failing — the
         * business could survive the demotion. The request is refused anyway,
         * because letting someone edit their own row means the permission
         * check and the target are the same person, and a stale tab could
         * promote them a second step further than they intended.
         */
        $business = $this->business();
        $owner = $this->actingIn($business, $business->owners()->first());
        $this->memberships->attach($business, User::factory()->create(), Role::OWNER);

        $this->assertSame(2, $this->memberships->ownerCount($business));

        $this->put(route('app.staff.update', $owner), [
            'role' => Role::PROPERTY_MANAGER->value,
        ])->assertSessionHasErrors('role');

        $this->assertSame(2, $this->memberships->ownerCount($business));
    }

    public function test_a_manager_without_staff_update_is_refused_before_the_role_check_runs(): void
    {
        /*
         * Ordering matters here.
         *
         * A property manager holds `staff.view` but not `staff.update`, so the
         * request is rejected by authorization without ever reaching the
         * self-role rule. Asserting the *response* is the point: a test that
         * expected a validation message would have been asserting an error the
         * user is never shown.
         */
        $business = $this->business();
        $manager = $this->member($business, Role::PROPERTY_MANAGER);
        $this->actingIn($business, $manager);

        $this->put(route('app.staff.update', $manager), [
            'role' => Role::OWNER->value,
        ])->assertForbidden();

        $this->assertSame(
            Role::PROPERTY_MANAGER,
            $this->memberships->currentRole($business, $manager->refresh()),
        );
    }

    public function test_the_only_owner_cannot_demote_themselves(): void
    {
        $business = $this->business();
        $owner = $this->actingIn($business, $business->owners()->first());

        $this->put(route('app.staff.update', $owner), [
            'role' => Role::PROPERTY_MANAGER->value,
        ])->assertSessionHasErrors('role');

        $this->assertSame(1, $this->memberships->ownerCount($business));
    }

    public function test_a_member_of_another_business_is_not_found_rather_than_forbidden(): void
    {
        /*
         * 404, not 403.
         *
         * A 403 would confirm that the user id in the URL exists, turning
         * /app/staff/{user}/edit into a probe for who is a customer. The
         * response must be indistinguishable from a user who does not exist.
         */
        $business = $this->business();
        $this->actingIn($business, $business->owners()->first());

        $stranger = $this->member($this->business(name: 'Someone Else'), Role::PROPERTY_MANAGER);

        $this->get(route('app.staff.edit', $stranger))->assertNotFound();
        $this->put(route('app.staff.update', $stranger), [
            'role' => Role::ACCOUNTANT->value,
        ])->assertNotFound();
        $this->delete(route('app.staff.destroy', $stranger))->assertNotFound();
    }

    /* ------------------------------------------------------------------ */
    /* Removing a member                                                   */
    /* ------------------------------------------------------------------ */

    public function test_an_owner_can_remove_a_member(): void
    {
        $business = $this->business();
        $owner = $this->actingIn($business, $business->owners()->first());
        $accountant = $this->member($business, Role::ACCOUNTANT);

        $response = $this->actingAs($owner)->delete(route('app.staff.destroy', $accountant));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertFalse($business->members()->whereKey($accountant->getKey())->exists());
        $this->assertNull(
            $this->memberships->currentRole($business, $accountant),
            'The role grant must go with the membership, or the grant outlives the access.'
        );
    }

    public function test_the_last_owner_cannot_be_removed(): void
    {
        $business = $this->business();
        $owner = $this->actingIn($business, $business->owners()->first());
        $this->member($business, Role::PROPERTY_MANAGER);

        $response = $this->actingAs($owner)->delete(route('app.staff.destroy', $owner));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame(1, $this->memberships->ownerCount($business));
    }

    public function test_an_owner_cannot_remove_themselves_even_with_another_owner_present(): void
    {
        // Two owners, so the service's last-owner guard does not apply. The
        // controller still refuses, because a "remove" button that removes the
        // person clicking it is a trap rather than a feature.
        $business = $this->business();
        $owner = $this->actingIn($business, $business->owners()->first());
        $this->memberships->attach($business, User::factory()->create(), Role::OWNER);

        $response = $this->actingAs($owner)->delete(route('app.staff.destroy', $owner));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertTrue($business->members()->whereKey($owner->getKey())->exists());
    }

    /* ------------------------------------------------------------------ */
    /* Permission sources                                                 */
    /* ------------------------------------------------------------------ */

    public function test_a_permission_held_only_in_another_business_does_not_unlock_a_route(): void
    {
        /*
         * The isolation case the whole audit exists for.
         *
         * The same person owns a second business, and is a property manager in
         * it. `staff.view` is granted to a property manager, so an ambient
         * team-scoped check would happily answer "yes" here. The route must
         * ask about the business being viewed, so it must not.
         */
        $business = $this->business(name: 'Small Landlord');
        $other = $this->business(name: 'Bigger Landlord');

        // A plain member of the first business, and a property manager in the
        // second. The person must not be the owner of the first, or the
        // assertion below would be checking a permission they legitimately
        // hold there.
        $user = $this->member($business, Role::ACCOUNTANT);
        $this->memberships->attach($other, $user, Role::PROPERTY_MANAGER);

        $this->actingIn($business, $user);

        $this->assertTrue(
            $user->canInBusiness(PermissionName::STAFF_VIEW, $other),
            'The permission really is held in the other business.'
        );
        $this->assertFalse(
            $user->canInBusiness(PermissionName::STAFF_VIEW, $business),
            '...and really is not held in this one.'
        );

        $this->get(route('app.staff.index'))->assertForbidden();
    }

    public function test_a_direct_permission_grant_counts_without_any_role(): void
    {
        /*
         * Some deployments grant one capability to one person without inventing
         * a role for it. `canInBusiness` has to honour that, or a per-user
         * grant is silently ignored and the feature is unreachable.
         */
        $business = $this->business();
        $viewer = $this->member($business, Role::ACCOUNTANT);
        $this->actingIn($business, $viewer);

        $this->get(route('app.staff.index'))->assertForbidden();

        /*
         * `setPermissionsTeamId()` is required, not optional: the teams
         * feature puts `business_id` on `model_has_permissions` too, and the
         * column is NOT NULL. Without it the insert fails, which is the
         * database correctly refusing an unscoped grant.
         */
        setPermissionsTeamId($business->getKey());

        $viewer->givePermissionTo(
            \Spatie\Permission\Models\Permission::findByName(PermissionName::STAFF_VIEW->value)
        );

        $this->assertTrue($viewer->canInBusiness(PermissionName::STAFF_VIEW, $business));
        $this->get(route('app.staff.index'))->assertOk();
    }

    public function test_a_user_holding_both_tenant_and_a_back_office_role_reaches_the_back_office(): void
    {
        /*
         * Dual roles are legitimate: a landlord who also lives in one of their
         * units, or an agency owner with a tenant account. The back office used
         * to be reachable only by an owner, so this person was locked out of
         * their own portfolio.
         */
        $business = $this->business();
        $user = $this->member($business, Role::TENANT);
        $this->memberships->attach($business, $user, Role::PROPERTY_MANAGER);

        $this->actingIn($business, $user);

        $this->get(route('app.staff.index'))->assertOk();
    }

    public function test_a_permission_held_via_a_global_template_role_is_honoured(): void
    {
        /*
         * Spatie's team lookup treats a role with `business_id IS NULL` as
         * visible to every team, which is how `RolePermissionSeeder`'s template
         * roles are meant to work. `canInBusiness` has to keep that behaviour,
         * or a business whose role row has not been materialised yet would deny
         * everything.
         */
        $business = $this->business();
        $viewer = $this->member($business, Role::ACCOUNTANT);

        $template = SpatieRole::query()
            ->where('name', Role::OWNER->value)
            ->whereNull('business_id')
            ->firstOrFail();

        $viewer->assignRole($template);

        $this->assertTrue(
            $viewer->canInBusiness(PermissionName::STAFF_INVITE, $business),
            'A global template role is a valid source of permissions.'
        );
    }

    /* ------------------------------------------------------------------ */
    /* Write gating                                                        */
    /* ------------------------------------------------------------------ */

    public function test_a_read_only_business_cannot_add_staff_but_can_still_read_the_roster(): void
    {
        $business = $this->business();
        $owner = $this->actingIn($business, $business->owners()->first());
        $this->member($business, Role::PROPERTY_MANAGER);

        $this->subscriptions->transitionTo(
            $business->subscription,
            SubscriptionStatus::ACTIVE
        );

        // Now expire it out from under the owner.
        $business->subscription->update([
            'status' => SubscriptionStatus::EXPIRED->value,
            'ended_at' => now(),
            'current_period_end' => now()->subDay(),
        ]);
        $business->update(['status' => \App\Enums\BusinessStatus::EXPIRED->value]);
        $business->unsetRelation('subscription');

        $this->assertFalse($business->canWrite());

        $this->get(route('app.staff.index'))->assertOk();

        // Writes are diverted to billing, where the problem can be fixed,
        // rather than returning a bare 403 that tells the user nothing.
        $response = $this->actingAs($owner)->post(route('app.staff.store'), [
            'email' => User::factory()->create()->email,
            'role' => Role::ACCOUNTANT->value,
        ]);

        $response->assertRedirect(route('app.subscription.show'));
        $response->assertSessionHas('error');
        $this->assertSame(2, $business->members()->count());
    }
}
