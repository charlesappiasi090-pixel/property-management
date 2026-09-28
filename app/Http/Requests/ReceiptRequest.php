<?php

namespace App\Http\Requests;

use App\Models\Receipt;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for creating a receipt.
 *
 * A receipt is always linked to a payment, so the request must have a
 * `payment_id` that exists and belongs to the active business.  The receipt
 * number must be unique per business.
 */
class ReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        $payment = $this->payment();

        return $payment instanceof Payment && ($this->user()?->can('create', Receipt::class) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'payment_id' => [
                'required',
                'exists:payments,id',
                // Scope the payment to the active business.
                'whereHas', function ($q) {
                    $q->where('business_id', app(BusinessContext::class)->id());
                },
            ],

            'receipt_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('receipts', 'receipt_number')
                    ->where('business_id', app(BusinessContext::class)->id())
                    ->whereNull('deleted_at'),
            ],

            'amount' => ['required', 'string', 'regex:'.\App\Http\Requests\PaymentRequest::MONEY_PATTERN],

            'issued_at' => ['required', 'date'],

            'issued_by' => ['nullable', 'string', 'max:190'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'receipt_number.unique' => 'A receipt with that number already exists for this business.',
            'amount.regex' => 'Enter the amount as a number with up to two decimal places, for example 1250 or 1,250.00.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'payment_id' => 'payment',
            'receipt_number' => 'receipt number',
            'amount' => 'amount',
            'issued_at' => 'issued date',
            'issued_by' => 'issued by',
        ];
    }

    /**
     * Values safe to hand to `Receipt`, normalised for storage.
     *
     * @return array<string, mixed>
     */
    public function receiptAttributes(): array
    {
        $attributes = $this->safe()->only([
            'payment_id', 'receipt_number', 'amount', 'issued_at', 'issued_by',
        ]);

        // Text fields: if present, trim and possibly clear to null.
        foreach (['receipt_number', 'issued_by'] as $key) {
            if ($this->has($key)) {
                $attributes[$key] = $this->stringOrNull($key);
            }
        }

        // DECIMAL(15,2): formatted to the column's own scale so the database
        // stores 1250.00 rather than 1250.
        if ($this->has('amount')) {
            $attributes['amount'] = $this->input('amount') === ''
                ? null
                : number_format(
                    (float) str_replace(',', '', (string) $this->input('amount')),
                    2,
                    '.',
                    ''
                );
        }

        return $attributes;
    }

    protected function payment(): ?\App\Models\Payment
    {
        $payment = $this->route('payment');

        return $payment instanceof \App\Models\Payment ? $payment : null;
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
}