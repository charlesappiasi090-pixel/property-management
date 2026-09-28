<?php

namespace App\Http\Requests;

use App\Models\Message;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for creating/updating a message.
 *
 * A message is always attached to the active business and exchanged between a
 * staff user and a tenant.  The request validates that the recipient tenant
 * exists, the subject and body are present, and the body is within a reasonable
 * size limit.
 */
class MessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('send', Message::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'tenant_id' => [
                'required',
                'exists:tenants,id',
                // Scope the tenant to the active business – the route model
                // binding already does this, but we double‑check here.
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
                'max:10000', // 10 KB max
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.max' => 'The message body is too long (maximum 10 KB).',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tenant_id' => 'recipient tenant',
            'subject' => 'subject',
            'body' => 'body',
        ];
    }

    /**
     * Values safe to hand to `Message`, normalised for storage.
     */
    public function messageAttributes(): array
    {
        $attributes = $this->safe()->only([
            'tenant_id',
            'subject',
            'body',
        ]);

        // Trim whitespace from subject.
        if ($this->has('subject')) {
            $attributes['subject'] = trim($this->input('subject'));
        }

        // Body: trim leading/trailing blank lines but preserve interior.
        if ($this->has('body')) {
            $attributes['body'] = trim($this->input('body'));
        }

        return $attributes;
    }
}