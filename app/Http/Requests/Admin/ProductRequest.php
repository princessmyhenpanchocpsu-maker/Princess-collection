<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product
            ? $this->user()->can('update', $product)
            : $this->user()->can('create', Product::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $product = $this->route('product');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash:ascii', Rule::unique('products', 'slug')->ignore($product?->id)],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'description' => ['nullable', 'string', 'max:5000'],
            'base_price' => ['required', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'status' => ['required', Rule::enum(ProductStatus::class)],

            // A new product needs at least one variant to be sellable; on update the list is optional.
            'variants' => [$product ? 'sometimes' : 'required', 'array', 'min:1', 'max:100'],
            'variants.*.id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')->where('product_id', $product?->id ?? 0)],
            'variants.*.size' => ['required', 'string', 'max:10'],
            'variants.*.color' => ['required', 'string', 'max:50'],
            'variants.*.sku' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
            'variants.*.price_override' => ['nullable', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'variants.*.low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'variants.*.is_active' => ['sometimes', 'boolean'],
            // Opening stock for NEW variants only. Existing stock changes go through inventory adjustments.
            'variants.*.stock_quantity' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ];
    }

    /**
     * Cross-row checks: no two variants may share a size + colour or a SKU,
     * counting both this request and the variants already saved.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty() || ! is_array($this->input('variants'))) {
                    return;
                }

                $product = $this->route('product');
                $rows = collect($this->input('variants'));
                $submittedIds = $rows->pluck('id')->filter()->map(fn ($id) => (int) $id);

                // Saved variants this request does not touch still count towards uniqueness.
                $untouched = $product
                    ? $product->variants()->whereNotIn('id', $submittedIds)->get(['size', 'color'])
                    : collect();

                $combos = $untouched->map(fn ($v) => $this->combo($v->size, $v->color))->all();
                $skus = [];

                foreach ($rows as $index => $row) {
                    $combo = $this->combo($row['size'], $row['color']);

                    if (in_array($combo, $combos, true)) {
                        $validator->errors()->add("variants.{$index}.color", "There is already a {$row['size']} / {$row['color']} variant.");
                    }
                    $combos[] = $combo;

                    $sku = strtoupper(trim((string) ($row['sku'] ?? '')));
                    if ($sku === '') {
                        continue;
                    }

                    $takenElsewhere = ProductVariant::where('sku', $sku)
                        ->when($row['id'] ?? null, fn ($q, $id) => $q->whereKeyNot($id))
                        ->exists();

                    if (in_array($sku, $skus, true) || $takenElsewhere) {
                        $validator->errors()->add("variants.{$index}.sku", "The SKU {$sku} is already in use.");
                    }
                    $skus[] = $sku;
                }
            },
        ];
    }

    private function combo(string $size, string $color): string
    {
        return mb_strtolower(trim($size)).'|'.mb_strtolower(trim($color));
    }
}
