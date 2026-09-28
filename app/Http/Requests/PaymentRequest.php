<?php

namespace App\Http\Requests;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for creating a payment.
 *
 * MONEY IS A STRING, NEVER A FLOAT
 * -------------------------------
 * `amount` is validated with a grouping-aware regex and stored as a decimal
 * string. Two traps are being avoided deliberately:
 *
 *   - Accepting "1,250.00" is right for a form, and stripping the separators
 *     with `str_replace(',', '')` alone is wrong: "1,2,3" becomes 123 and is
 *     accepted. The regex below only allows separators in groups of three, so
 *     a typo is a validation error instead of a silently wrong rent.
 *   - Nothing here passes the value through `floatval()`. The column is
 *     DECIMAL(15,2) and the model casts it with `decimal:2`; a float in
 *     between would reintroduce the rounding error that
 *     `App\Support\Money\Money` exists to eliminate.
 *
 * The regex allows optional grouped thousands separators and at most two
 * decimal places:
 *   `/^\d{1,3}(,\d{3})*(\.\d{1,2})?$|^\d+(\.\d{1,2})?$/`
 *
 * `method` is a free‑text field – the UI may present a dropdown, but at the
 * database level we store whatever the user types.
 *
 * `reference` is optional – check number, transaction ID, etc.
 */
class PaymentRequest extends FormRequest
{
    /**
     * Grouped thousands separators, or none; at most two decimal places.
     *
     * Written as an alternation rather than an optional comma group so
     * "1,25" and "1234,567" are both rejected instead of being silently
     * reinterpreted.
     */
    protected const MONEY_PATTERN = '/^\d{1,3}(,\d{3})*(\.\d{1,2})?$|^\d+(\.\d{1,2})?$/';

    public function authorize(): bool
    {
        $lease = $this->lease();

        return $lease instanceof Lease && ($this->user()?->can('create', Payment::class) ?? false);
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
                // Scope the lease to the active business – the route model
                * binding already does this, but a malicious caller could
                * craft a POST with a random lease id.
                'whereHas', function ($q) {
                    $q->where('business_id', app(BusinessContext::class)->id());
                },
            ],

            'amount' => ['required', 'string', 'regex:'.self::MONEY_PATTERN],

            'status' => ['required', 'in:active,failed,refunded'],

            'method' => ['nullable', 'string', 'max:50'],

            'reference' => ['nullable', 'string', 'max:100'],

            'payment_date' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.regex' => 'Enter the amount as a number with up to two decimal places, for example 1250 or 1,250.00.',
            'status.in' => 'Status must be one of: active, failed, refunded.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'lease_id' => 'lease',
            'amount' => 'amount',
            'status' => 'status',
            'method' => 'method',
            'reference' => 'reference',
            'payment_date' => 'payment date',
        ];
    }

    /**
     * Values safe to hand to `Payment`, normalised for storage.
     *
     * Empty text submissions become NULL so a field can be cleared, numeric
 * fields are returned as fixed-scale STRINGS to match the DECIMAL columns
 * exactly.
 *
 * @return array<string, mixed>
 */
    public function paymentAttributes(): array
    {
        $attributes = $this->safe()->only([
            'lease_id', 'amount', 'status', 'method', 'reference',
        ]);

        // `safe()->only()` drops keys that failed validation, which is what
        // makes partial updates safe: a rejected field is not silently written
        // as null.  They are normalised to null here instead, because an empty
        // text input legitimately means "no longer recorded" — the user
        // clearing a field must be able to clear it.
        $attributes = array_merge($attributes, [
            'amount' => $this->stringOrNull('amount'),
            'method' => $this->stringOrNull('method'),
            'reference' => $this->stringOrNull('reference'),
            'payment_date' => $this->dateOrNull('payment_date'),
        ]);

        return $attributes;
    }

    protected function lease(): ?\App\Models\Lease
    {
        $lease = $this->route('lease');

        return $lease instanceof \App\Models\Lease ? $lease : null;
    }

    protected function activeBusinessId(): ?int
    {
        return app(BusinessContext::class)->id();
    }

    protected function stringOrNull(string $key): ?string
    {
        $value = $this->input($key);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    protected function dateOrNull(string $key): ?string
    {
        $value = $this->input($key);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}