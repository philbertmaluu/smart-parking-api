<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BundleTypeRequest extends FormRequest
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
        $bundleTypeId = $this->route('bundle_type');
        $nameRule = $bundleTypeId ? "unique:bundle_types,name,{$bundleTypeId}" : 'unique:bundle_types,name';

        return [
            'name' => "required|string|max:100|{$nameRule}",
            'description' => 'nullable|string|max:500',
            'duration_days' => 'required|integer|min:1|max:365',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Bundle type name is required.',
            'name.unique' => 'Bundle type name must be unique.',
            'name.max' => 'Bundle type name cannot exceed 100 characters.',
            'description.max' => 'Description cannot exceed 500 characters.',
            'duration_days.required' => 'Duration in days is required.',
            'duration_days.integer' => 'Duration must be a valid number.',
            'duration_days.min' => 'Duration must be at least 1 day.',
            'duration_days.max' => 'Duration cannot exceed 365 days.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'bundle type name',
            'description' => 'description',
            'duration_days' => 'duration in days',
        ];
    }
}
