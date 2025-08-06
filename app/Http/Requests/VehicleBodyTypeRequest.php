<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VehicleBodyTypeRequest extends FormRequest
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
        $bodyTypeId = $this->route('vehicle_body_type');
        $nameRule = $bodyTypeId ? "unique:vehicle_body_types,name,{$bodyTypeId}" : 'unique:vehicle_body_types,name';

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
            'name.required' => 'Vehicle body type name is required.',
            'name.unique' => 'Vehicle body type name must be unique.',
            'name.max' => 'Vehicle body type name cannot exceed 100 characters.',
            'description.max' => 'Description cannot exceed 500 characters.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'vehicle body type name',
            'description' => 'description',
        ];
    }
}
