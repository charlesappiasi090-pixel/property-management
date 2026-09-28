<?php

namespace App\Http\Requests;

use App\Models\Payment;
use Illuminate\Validation\Rule;

/**
 * Validation for editing a payment.
 *
 * Extends `PaymentRequest` rather than repeating it, because the only two
 * differences are real: the ability being checked, and the amount regex,
 * which must allow the current amount to be re‑entered without failing
 * uniqueness validation.
 *
 * Two things genuinely differ from the create form:
 *   - the ability is `update` on the payment, not `create`;
 *   - the amount regex still applies (the user may change the amount).
 */
class UpdatePaymentRequest extends PaymentRequest
{
    public function authorize(): bool
    {
        $payment = $this->payment();

        return $payment instanceof Payment && ($this->user()?->can('update', $payment) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        /*
         * `ignore($payment)` is what stops the edit form rejecting its own
         * current amount as a duplicate. Without it, saving a payment without
         * touching the amount fails validation every single time.
         *
         * `ignore()` also supplies the `lease_id` scope, because the default
         * is the primary key column; the parent rule's `whereHas` still applies
         * and keeps the comparison inside one lease.
         */
        $rules['amount'] = [
            'required',
            'string',
            'regex:'.PaymentRequest::MONEY_PATTERN,
            Rule::unique('payments', 'amount')
                ->where('lease_id', $this->payment()?->getKey())
                ->whereNull('deleted_at')
                ->ignore($this->payment()),
        ];

        /*
         * The status may be changed, so we re‑validate the enum here.
         */
        $rules['status'] = ['required', 'in:active,failed,refunded'];

        return $rules;
    }

    protected function payment(): ?Payment
    {
        $payment = $this->route('payment');

        return $payment instanceof Payment ? $payment : null;
    }
}