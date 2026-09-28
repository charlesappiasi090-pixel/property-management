<?php

namespace App\Http\Requests;

use App\Models\RenewalNotice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for creating/updating a renewal notice.
 *
 * A renewal notice is always attached to the active business and linked to a
 * specific lease and tenant.  The request validates that the lease and tenant
 * exist and belong to the active business, and that the subject/body are present.
 */
class RenewalNoticeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', RenewalNotice::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'lease_id' => [
                'required',
                'exists:leases,id',
                // Scope the lease to the active business
                'whereHas', function ($q) {
                    $q->where('business_id', app(\App\Support\Tenancy\BusinessContext::class)->id());
                },
            ],
            'tenant_id' => [
                'required',
                'exists:tenants,id',
                // Scope the tenant to the active business
                'whereHas', function ($q) {
                    $q->where('business_id', app(\App\Support\Tenancy\BusinessContext::class)->id());
                },
            ],
            'subject' => [
                'required',
                'string',
                'max:200',
            ],
            'body' => [
                'required',
                'string',
            ],
            'send_at' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subject.max' => 'The subject may not be longer than 200 characters.',
            'body.required' => 'The message body is required.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'lease_id' => 'lease',
            'tenant_id' => 'tenant',
            'subject' => 'subject',
            'body' => 'body',
            'send_at' => 'send date',
        ];
    }

    /**
     * Values safe to hand to `RenewalNotice`, normalised for storage.
     */
    public function renewalNoticeAttributes(): array
    {
        $attributes = $this->safe()->only([
            'lease_id',
            'tenant_id',
            'subject',
            'body',
            'send_at',
        ]);

        // Trim subject.
        if ($this->has('subject')) {
            $attributes['subject'] = trim($this->input('subject'));
        }

        // Body: trim but preserve interior whitespace.
        if ($this->has('body')) {
            $attributes['body'] = trim($this->input('body'));
        }

        // Send at: ensure it's a date string or null.
        if ($this->has('send_at') && $this->input('send_at')) {
            $attributes['send_at'] = $this->input('send_at');
        } else {
            $attributes['send_at'] = null;
        }

        return $attributes;
    }
}