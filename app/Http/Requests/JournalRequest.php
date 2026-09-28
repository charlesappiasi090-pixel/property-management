<?php

namespace App\Http\Requests;

use App\Models\JournalEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for creating/updating a journal entry.
 *
 * A journal entry is always attached to the active business.  The request
 * validates that the amount is a valid decimal string, the transaction type
 * is one of the enum values, and the description is present.
 */
class JournalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', JournalEntry::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'description' => [
                'required',
                'string',
                'max:255',
            ],

            'amount' => [
                'required',
                'string',
                'regex:'.\App\Http\Requests\PaymentRequest::MONEY_PATTERN,
            ],

            'transaction_type' => [
                'required',
                'string',
                Rule::in(['debit', 'credit']),
            ],

            'reference_type' => [
                'nullable',
                'string',
                Rule::in([
                    Property::class,
                    Expense::class,
                    Lease::class,
                    MaintenanceRequest::class,
                ]),
            ],

            'reference_id' => [
                'nullable',
                'exists:' . fn ($q) => $q->from('properties'), // placeholder; actual check in controller
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.regex' => 'The amount must be a valid decimal string (e.g. 1250.00).',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'description' => 'description',
            'amount' => 'amount',
            'transaction_type' => 'transaction type',
            'reference_type' => 'reference type',
            'reference_id' => 'reference id',
        ];
    }

    /**
     * Values safe to hand to `JournalEntry`, normalised for storage.
     */
    public function journalEntryAttributes(): array
    {
        $attributes = $this->safe()->only([
            'description',
            'amount',
            'transaction_type',
            'reference_type',
            'reference_id',
        ]);

        // Amount: ensure it is a clean decimal string, no thousands separators.
        if ($this->has('amount')) {
            $attributes['amount'] = number_format(
                (float) str_replace(',', '', (string) $this->input('amount')),
                2,
                '.',
                ''
            );
        }

        // Reference fields: if present, ensure they are integers.
        foreach (['reference_id'] as $key) {
            if ($this->has($key)) {
                $attributes[$key] = (int) $this->input($key);
            }
        }

        return $attributes;
    }
}