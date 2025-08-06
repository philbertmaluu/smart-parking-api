<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentTypeRequest extends FormRequest
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
     */
    public function rules(): array
    {
        $paymentTypeId = $this->route('payment_type');
        $nameRule = $paymentTypeId ? "unique:payment_types,name,{$paymentTypeId}" : 'unique:payment_types,name';

        return [
            'name' => "required|string|max:100|{$nameRule}",
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Payment type name is required.',
            'name.unique' => 'Payment type name must be unique.',
            'name.max' => 'Payment type name cannot exceed 100 characters.',
            'description.max' => 'Description cannot exceed 500 characters.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'payment type name',
            'description' => 'description',
        ];
    }
}
