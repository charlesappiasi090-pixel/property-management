<?php

namespace App\Http\Requests;

use App\Enums\PermissionName;
use App\Enums\Role;
use App\Models\User;
use App\Support\Tenancy\BusinessContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for changing a staff member's role within the active business.
 *
 * Two escalation guards:
 *
 *  1. The `role` rule only accepts roles the ACTOR may assign. An owner can
 *     assign property_manager/accountant/maintenance_staff, and can also
 *     promote someone to owner only if they hold `staff.grant_owner` — which
 *     in practice means only an existing owner.
 *
 *  2. A member may not change their OWN role here. Self-promotion to owner
 *     would be privilege escalation, and self-demotion from owner is already
 *     blocked by the service's last-owner guard — but blocking it in
 *     validation gives a better error message.
 */
class UpdateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        // Business named explicitly: see InviteStaffRequest::authorize().
        return $user->canInBusiness(
            PermissionName::STAFF_UPDATE,
            app(BusinessContext::class)->business()
        );
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $assignable = array_values(array_filter(
            Role::cases(),
            fn (Role $role): bool => $this->mayAssign($role)
        ));

        return [
            'role' => ['required', 'string', Rule::in(array_map(
                static fn (Role $role): string => $role->value,
                $assignable
            ))],
            'job_title' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * May the signed-in actor grant this role?
     *
     * Both branches are checked against the ACTIVE business by name. The owner
     * escalation in particular must not be able to be satisfied by a
     * `staff.grant_owner` permission held in a different workspace.
     */
    protected function mayAssign(Role $role): bool
    {
        if (! $role->isAssignableByOwner()) {
            return $role === Role::OWNER
                && $this->user()?->canInBusiness(
                    PermissionName::STAFF_GRANT_OWNER,
                    app(BusinessContext::class)->business()
                ) === true;
        }

        return true;
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator): void {
            /** @var User $member */
            $member = $this->route('user');

            if ($member !== null && $member->is($this->user())) {
                $validator->errors()->add(
                    'role',
                    'You cannot change your own role. Ask another owner to do it.'
                );
            }

            if ($member !== null && ! $this->isMemberOfActiveBusiness($member)) {
                // Not a membership problem, so an error field is wrong. Abort
                // with a 404-equivalent: the caller must not be able to learn
                // whether a given user id belongs to some other tenant.
                abort(404);
            }
        });
    }

    protected function isMemberOfActiveBusiness(User $member): bool
    {
        $business = app(BusinessContext::class)->business();

        return $business !== null
            && $business->members()->where('users.id', $member->getKey())->exists();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.in' => 'You are not allowed to assign that role.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'job_title' => 'job title',
        ];
    }
}
