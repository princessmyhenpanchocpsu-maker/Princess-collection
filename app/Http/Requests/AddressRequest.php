<?php

namespace App\Http\Requests;

use App\Models\Address;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AddressRequest extends FormRequest
{
    public const MAX_PER_USER = 10;

    public function authorize(): bool
    {
        $address = $this->route('address');

        return $address
            ? $this->user()->can('update', $address)
            : $this->user()->can('create', Address::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'recipient_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^(09\d{9}|\+639\d{9})$/'],
            'address_line' => ['required', 'string', 'max:255'],
            'barangay' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'postal_code' => ['required', 'string', 'regex:/^\d{4}$/'],
            'delivery_instructions' => ['nullable', 'string', 'max:500'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a valid Philippine mobile number, e.g. 09171234567.',
            'postal_code.regex' => 'Enter a 4-digit postal code.',
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $this->route('address') && $this->user()->addresses()->count() >= self::MAX_PER_USER) {
                    $validator->errors()->add('address', 'You can save up to '.self::MAX_PER_USER.' addresses. Remove one to add another.');
                }
            },
        ];
    }
}
