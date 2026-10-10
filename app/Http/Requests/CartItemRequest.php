<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CartItemRequest extends FormRequest
{
    /**
     * Ownership is enforced by the controller, which only ever looks inside the signed-in user's cart.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Adding a line names the variant; changing a line only sends the new quantity.
            'product_variant_id' => [$this->isMethod('POST') ? 'required' : 'prohibited', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }
}
