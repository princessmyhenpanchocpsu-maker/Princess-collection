<?php

namespace App\Http\Requests\Admin;

use App\Enums\InventoryMovementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin');
    }

    /**
     * `stock_in` adds received stock (positive only). `adjustment` corrects the count
     * up or down, e.g. after a stocktake or for damaged items.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $stockIn = $this->input('type') === InventoryMovementType::StockIn->value;

        return [
            'product_variant_id' => ['required', 'integer', Rule::exists('product_variants', 'id')],
            'type' => ['required', Rule::in([InventoryMovementType::StockIn->value, InventoryMovementType::Adjustment->value])],
            'quantity' => ['required', 'integer', 'not_in:0', $stockIn ? 'min:1' : 'min:-100000', 'max:100000'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quantity.not_in' => 'The quantity must not be zero.',
            'quantity.min' => 'Stock-in quantity must be at least 1. Use an adjustment to reduce stock.',
        ];
    }
}
