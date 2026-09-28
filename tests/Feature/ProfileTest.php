<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Business;
use App\Models\User;
use App\Services\Tenancy\BusinessMembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsPermissions;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;
    use SeedsPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        // Only the two tests below need roles. Seeding here for all five would
        // make the unrelated profile tests pay for a fixture they don't touch.
        if (str_contains($this->name(), 'owner')) {
            $this->seedPermissions();
        }
    }

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_guests_cannot_view_the_profile_page(): void
    {
        $response = $this->get('/profile');

        $response->assertRedirect('/login');
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => $this->validPassword(),
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();

        // `User` uses `SoftDeletes`, so the row survives with `deleted_at` set.
        // The original Breeze assertion was `assertNull($user->fresh())`, which
        // only holds for a hard delete and so failed against a soft-deleted
        // record. Soft deletion is the intended behaviour here: audit logs and
        // subscriptions reference this user by id, and a hard delete would
        // either break those references or cascade away billing history that
        // has to be retained.
        $this->assertSoftDeleted($user);
    }

    public function test_sole_owner_cannot_delete_their_account_and_strand_a_business(): void
    {
        // A sole owner deleting their account would leave a tenant that nobody
        // can reach, since every other member is only a guest of their
        // workspace. Pinned here so the guard cannot be dropped silently.
        $business = Business::factory()->create();

        $user = User::factory()->create();
        $this->addMember($business, $user, Role::OWNER);

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => $this->validPassword(),
            ]);

        $response->assertSessionHasErrorsIn('userDeletion', 'password');
        $this->assertAuthenticated();
        $this->assertNotSoftDeleted($user);
    }

    public function test_owner_may_delete_their_account_when_another_owner_exists(): void
    {
        // The block is about STRANDING a tenant, not about owning one. With a
        // second owner in place the business stays reachable, so deletion must
        // be allowed to proceed.
        $business = Business::factory()->create();

        $leavingOwner = User::factory()->create();
        $this->addMember($business, $leavingOwner, Role::OWNER);

        $successor = User::factory()->create();
        $this->addMember($business, $successor, Role::OWNER);

        $response = $this
            ->actingAs($leavingOwner)
            ->from('/profile')
            ->delete('/profile', [
                'password' => $this->validPassword(),
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertSoftDeleted($leavingOwner);
        $this->assertNotSoftDeleted($successor);
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }

    /**
     * Add a member with a role, going through the membership service rather
     * than writing `business_user` and `model_has_roles` by hand.
     *
     * The service is what production uses, it sets the Spatie team id to the
     * business being joined, and it creates the tenant's role row. A hand-rolled
     * insert would skip all three, and the test would then pass for a setup the
     * application can never actually produce.
     */
    private function addMember(Business $business, User $user, Role $role): User
    {
        return app(BusinessMembershipService::class)->attach($business, $user, $role);
    }
}
