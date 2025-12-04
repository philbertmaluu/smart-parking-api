<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomerRequest extends FormRequest
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
        $customerId = $this->route('customer');
        $customerNumberRule = $customerId ? "unique:customers,customer_number,{$customerId}" : 'unique:customers,customer_number';

        return [
            'user_id' => 'required|exists:users,id',
            'customer_number' => "required|string|max:50|{$customerNumberRule}",
            'name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'customer_type' => 'required|in:individual,corporate',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'user_id.required' => 'User is required.',
            'user_id.exists' => 'Selected user does not exist.',
            'customer_number.required' => 'Customer number is required.',
            'customer_number.unique' => 'Customer number must be unique.',
            'customer_number.max' => 'Customer number cannot exceed 50 characters.',
            'name.required' => 'Customer name is required.',
            'name.max' => 'Customer name cannot exceed 255 characters.',
            'company_name.max' => 'Company name cannot exceed 255 characters.',
            'customer_type.required' => 'Customer type is required.',
            'customer_type.in' => 'Customer type must be individual or corporate.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'user_id' => 'user',
            'customer_number' => 'customer number',
            'name' => 'customer name',
            'company_name' => 'company name',
            'customer_type' => 'customer type',
        ];
    }
}
