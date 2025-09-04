<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sku' => [
                'required',
                'string',
                'max:255',
                Rule::unique('products')->ignore($this->route('product')),
            ],
            'name' => 'required|string|max:255',
            'category_id' => 'nullable|exists:product_categories,id',
            'unit' => 'nullable|string|max:255',
            'price' => 'nullable|integer|min:0',
            'cost' => 'nullable|integer|min:0',
            'tax_category' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'description' => 'nullable|string',
            'attributes' => 'nullable|array',
        ];
    }
}
