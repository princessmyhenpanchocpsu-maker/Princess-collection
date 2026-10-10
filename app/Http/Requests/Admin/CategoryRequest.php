<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;
use App\Services\ImageStorage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('category');

        return $category
            ? $this->user()->can('update', $category)
            : $this->user()->can('create', Category::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = $this->route('category')?->id;

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')->ignore($id)],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash:ascii', Rule::unique('categories', 'slug')->ignore($id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
            'image' => ['sometimes', 'nullable', ...ImageStorage::rules()],
        ];
    }
}
