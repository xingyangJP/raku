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
        $productId = optional($this->route('product'))->id ?? null;

        return [
            // SKU is server-generated. Do not accept from UI.
            'name' => 'required|string|max:450',

            // Category is required to generate SKU. Must exist in categories.
            'category_id' => [
                'required',
                'integer',
                'exists:categories,id',
            ],

            'unit' => 'nullable|string|max:20',
            'price' => 'nullable|numeric|min:0',
            'quantity' => 'nullable|numeric',
            'cost' => 'nullable|numeric|min:0',
            'tax_category' => [
                'nullable',
                'string',
                Rule::in(['untaxable', 'non_taxable', 'tax_exemption', 'five_percent', 'eight_percent', 'eight_percent_as_reduced_tax_rate', 'ten_percent'])
            ],
            'is_deduct_withholding_tax' => 'nullable|boolean',
            'is_active' => 'boolean',
            'description' => 'nullable|string|max:200',
            'attributes' => 'nullable|array',
        ];
    }
}
