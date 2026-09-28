<?php

namespace App\Http\Requests;

use App\Enums\ExpenseCategory;
use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for creating an expense.
 *
 * CATEGORY IS AN ENUM
 * -----------------
 * `category` is validated against `ExpenseCategory::class` so that a value
 * added to the enum becomes valid automatically instead of needing this file
 * edited.  The enum also provides the `label()` method for nice UI display.
 *
 * AMOUNT IS A STRING, NEVER A FLOAT
 * -------------------------------
 * `amount` is validated with a grouping-aware regex and stored as a decimal
 * string. Two traps are being avoided deliberately:
 *
 *   - Accepting "1,250.00" is right for a form, and stripping the separators
 *     with `str_replace(',', '')` alone is wrong: "1,2,3" becomes 123 and is
 *     accepted. The regex below only allows separators in groups of three, so
 *     a typo is a validation error instead of a silently wrong amount.
 *   - Nothing here passes the value through `floatval()`. The column is
 *     DECIMAL(15,2) and the model casts it with `decimal:2`; a float in
 *     between would reintroduce the rounding error that
 *     `App\Support\Money\Money` exists to eliminate.
 *
 * EXPENSE DATE
 * ------------
 * `expense_date` is a plain date, validated as such.
 *
 * RECEIPT PATH
 * ------------
 * `receipt_path` is a free‑text field – the UI may present a button to
 * upload an image, but at the database level we just store the path/key.
 */
class ExpenseRequest extends FormRequest
{
    protected const MONEY_PATTERN = '/^\d{1,3}(,\d{3})*(\.\d{1,2})?$|^\d+(\.\d{1,2})?$/';

    public function authorize(): bool
    {
        $property = $this->property();

        return $property instanceof \App\Models\Property && ($this->user()?->can('create', Expense::class) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'property_id' => [
                'required',
                'exists:properties,id',
                // Scope the property to the active business – the route model
                * binding already does this, but a malicious caller could
                * craft a POST with a random property id.
                'whereHas', function ($q) {
                    $q->where('business_id', app(BusinessContext::class)->id());
                },
            ],

            'lease_id' => ['nullable', 'exists:leases,id'],

            'unit_id' => ['nullable', 'exists:units,id'],

            'tenant_id' => ['nullable', 'exists:tenants,id'],

            'category' => ['required', ExpenseCategory::class],

            'amount' => ['required', 'string', 'regex:'.self::MONEY_PATTERN],

            'status' => ['required', 'in:pending,approved,reimbursed,written_off'],

            'description' => ['nullable', 'string', 'max:1000'],

            'receipt_path' => ['nullable', 'string', 'max:255'],

            'expense_date' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category.in' => 'Select a valid expense category.',
            'amount.regex' => 'Enter the amount as a number with up to two decimal places, for example 1250 or 1,250.00.',
            'status.in' => 'Status must be one of: pending, approved, reimbursed, written_off.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'property_id' => 'property',
            'lease_id' => 'lease',
            'unit_id' => 'unit',
            'tenant_id' => 'tenant',
            'category' => 'category',
            'amount' => 'amount',
            'status' => 'status',
            'description' => 'description',
            'receipt_path' => 'receipt path',
            'expense_date' => 'expense date',
        ];
    }

    /**
     * Values safe to hand to `Expense`, normalised for storage.
     *
     * Empty text submissions become NULL so a field can be cleared, numeric
 * fields are returned as fixed-scale STRINGS to match the DECIMAL columns
 * exactly.
 *
 * @return array<string, mixed>
 */
    public function expenseAttributes(): array
    {
        $attributes = $this->safe()->only([
            'property_id', 'lease_id', 'unit_id', 'tenant_id',
            'category', 'amount', 'status', 'description', 'receipt_path',
        ]);

        // Text fields: if present, trim and possibly clear to null.
        foreach (['description', 'receipt_path'] as $key) {
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

        if ($this->has('expense_date')) {
            $attributes['expense_date'] = $this->dateOrNull('expense_date');
        }

        return $attributes;
    }

    protected function property(): ?\App\Models\Property
    {
        $property = $this->route('property');

        return $property instanceof \App\Models\Property ? $property : null;
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