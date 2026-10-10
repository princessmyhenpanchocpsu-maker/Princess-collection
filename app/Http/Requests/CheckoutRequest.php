<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
    }

    /**
     * Deliberately small: items, prices and totals are never accepted from the client.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Must be one of the signed-in customer's own addresses.
            'address_id' => ['required', 'integer', Rule::exists('addresses', 'id')->where('user_id', $this->user()->id)],
            'payment_method' => ['required', Rule::in([PaymentMethod::CashOnDelivery->value])],
            'notes' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['required', 'string', 'min:16', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'address_id.exists' => 'Choose one of your saved delivery addresses.',
            'payment_method.in' => 'Cash on Delivery is the only payment method available right now.',
            'idempotency_key.required' => 'The Idempotency-Key header is required.',
        ];
    }
}
