<?php

namespace App\Http\Requests;

use App\Enums\PermissionName;
use App\Enums\Role;
use App\Models\User;
use App\Support\Tenancy\BusinessContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validation for adding a person to the active business.
 *
 * TENANT SAFETY IN A FORM REQUEST
 * -------------------------------
 * Membership is enforced server-side, never by hiding the button in the UI.
 * `checkNotAlreadyMember()` uses the RESOLVED business from BusinessContext,
 * so it is impossible to trick this request into adding someone to a
 * different tenant than the one you are looking at.
 */
class InviteStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        /*
         * Named business, not a bare `can()`.
         *
         * The route also carries `permission:staff.invite`, but that answers
         * "may this user reach this URL" using the ambient team id. This is the
         * precise half - "may this user add a member to THIS workspace" - and
         * the two must be about the same landlord, or a stale team id would
         * authorise an invite against a different business's permissions.
         */
        return $user->canInBusiness(
            PermissionName::STAFF_INVITE,
            app(BusinessContext::class)->business()
        );
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'string',
                /*
                 * `rfc` only, deliberately NOT `rfc,dns`.
                 *
                 * The `dns` rule resolves the domain over the network on every
                 * request. That makes a form submission depend on outbound DNS:
                 * on a server with restricted egress, or during a resolver
                 * outage, every valid invite is rejected with a misleading
                 * "must be a valid email address". It also means a temporary
                 * domain failure locks staff management entirely.
                 *
                 * The `exists` rule below is what actually matters here — the
                 * address has to belong to a real account — and it needs no
                 * network. Syntax alone is enough to reject typos here; the
                 * address is verified by the confirmation email anyway.
                 */
                'email:rfc',
                'max:190',
                // Must already hold an account (see StaffController::store).
                Rule::exists('users', 'email')->whereNull('deleted_at'),
            ],

            'role' => [
                'required',
                'string',
                Rule::in(array_map(
                    static fn (Role $role): string => $role->value,
                    // The assignable set, NOT all five roles. Without this
                    // rule a hand-crafted POST could grant `owner`.
                    array_filter(Role::cases(), static fn (Role $r): bool => $r->isAssignableByOwner())
                )),
            ],

            'job_title' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * Cross-field and cross-table checks that a declarative rule cannot
     * express cleanly.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->checkNotAlreadyMember($validator);
            $this->checkNotInvitingYourself($validator);
        });
    }

    protected function checkNotAlreadyMember(Validator $validator): void
    {
        $business = app(BusinessContext::class)->business();

        if ($business === null || ! $this->filled('email')) {
            return;
        }

        $userId = User::query()
            ->where('email', $this->string('email'))
            ->value('id');

        if ($userId === null) {
            return;
        }

        $alreadyMember = $business->members()->where('users.id', $userId)->exists();

        if ($alreadyMember) {
            $validator->errors()->add(
                'email',
                'That person is already a member of this business. Change their role from the staff list instead.'
            );
        }
    }

    protected function checkNotInvitingYourself(Validator $validator): void
    {
        if (! $this->filled('email')) {
            return;
        }

        if (strcasecmp((string) $this->input('email'), (string) $this->user()->email) === 0) {
            $validator->errors()->add('email', 'You are already a member of this business.');
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.exists' => 'That person does not have a PropertyHub account yet. Ask them to register first.',
            'role.in' => 'That is not a role you are allowed to assign.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email' => 'email address',
            'job_title' => 'job title',
        ];
    }
}
