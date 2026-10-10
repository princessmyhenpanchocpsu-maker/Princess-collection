<?php

namespace App\Http\Requests\Admin;

use App\Services\ImageStorage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ProductImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('product'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'images' => ['required', 'array', 'min:1', 'max:'.config('store.images.max_per_product')],
            'images.*' => ImageStorage::rules(),
            'alt_text' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $limit = config('store.images.max_per_product');
                $total = $this->route('product')->images()->count() + count((array) $this->file('images'));

                if ($total > $limit) {
                    $validator->errors()->add('images', "A product can have at most {$limit} images.");
                }
            },
        ];
    }
}
