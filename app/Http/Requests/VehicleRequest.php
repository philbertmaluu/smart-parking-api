<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VehicleRequest extends FormRequest
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
        $vehicleId = $this->route('vehicle');
        $plateNumberRule = $vehicleId ? "unique:vehicles,plate_number,{$vehicleId}" : 'unique:vehicles,plate_number';

        return [
            'body_type_id' => 'required|exists:vehicle_body_types,id',
            'plate_number' => "required|string|max:20|{$plateNumberRule}",
            'make' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'year' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'color' => 'nullable|string|max:50',
            'owner_name' => 'nullable|string|max:255',
            'is_registered' => 'boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'body_type_id.required' => 'Vehicle body type is required.',
            'body_type_id.exists' => 'Selected vehicle body type does not exist.',
            'plate_number.required' => 'Plate number is required.',
            'plate_number.unique' => 'Plate number must be unique.',
            'plate_number.max' => 'Plate number cannot exceed 20 characters.',
            // 'make.required' => 'Vehicle make is required.',
            // 'make.max' => 'Vehicle make cannot exceed 100 characters.',
            // 'model.required' => 'Vehicle model is required.',
            // 'model.max' => 'Vehicle model cannot exceed 100 characters.',
            // 'year.required' => 'Vehicle year is required.',
            // 'year.integer' => 'Vehicle year must be a valid number.',
            // 'year.min' => 'Vehicle year must be at least 1900.',
            // 'year.max' => 'Vehicle year cannot be in the future.',
            // 'color.required' => 'Vehicle color is required.',
            // 'color.max' => 'Vehicle color cannot exceed 50 characters.',
            'owner_name.max' => 'Owner name cannot exceed 255 characters.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'body_type_id' => 'vehicle body type',
            'plate_number' => 'plate number',
            'make' => 'vehicle make',
            'model' => 'vehicle model',
            'year' => 'vehicle year',
            'color' => 'vehicle color',
            'owner_name' => 'owner name',
        ];
    }
}
