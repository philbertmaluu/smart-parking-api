<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BundleRequest extends FormRequest
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
        $bundleId = $this->route('bundle');
        
        return [
            'bundle_type_id' => 'required|integer|exists:bundle_types,id',
            'name' => "required|string|max:100|unique:bundles,name,{$bundleId},id,deleted_at,NULL",
            'amount' => 'required|numeric|min:0',
            'max_vehicles' => 'required|integer|min:1',
            'max_passages' => 'nullable|integer|min:1',
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
            'bundle_type_id.required' => 'Bundle type is required.',
            'bundle_type_id.exists' => 'Selected bundle type does not exist.',
            'name.required' => 'Bundle name is required.',
            'name.unique' => 'Bundle name must be unique.',
            'name.max' => 'Bundle name cannot exceed 100 characters.',
            'amount.required' => 'Bundle amount is required.',
            'amount.numeric' => 'Bundle amount must be a valid number.',
            'amount.min' => 'Bundle amount must be at least 0.',
            'max_vehicles.required' => 'Maximum vehicles is required.',
            'max_vehicles.integer' => 'Maximum vehicles must be a whole number.',
            'max_vehicles.min' => 'Maximum vehicles must be at least 1.',
            'max_passages.integer' => 'Maximum passages must be a whole number.',
            'max_passages.min' => 'Maximum passages must be at least 1.',
            'description.max' => 'Description cannot exceed 500 characters.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'bundle_type_id' => 'bundle type',
            'name' => 'bundle name',
            'amount' => 'amount',
            'max_vehicles' => 'maximum vehicles',
            'max_passages' => 'maximum passages',
            'description' => 'description',
            'is_active' => 'status',
        ];
    }
}
