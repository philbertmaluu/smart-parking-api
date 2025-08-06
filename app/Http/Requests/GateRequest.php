<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GateRequest extends FormRequest
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
        return [
            'name' => 'required|string|max:255',
            'gate_type' => 'required|in:entry,exit,both',
            'station_id' => 'required|exists:stations,id',
            'is_active' => 'boolean',
            'description' => 'nullable|string|max:1000',
            'location_details' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Gate name is required.',
            'name.max' => 'Gate name cannot exceed 255 characters.',
            'gate_type.required' => 'Gate type is required.',
            'gate_type.in' => 'Gate type must be entry, exit, or both.',
            'station_id.required' => 'Station is required.',
            'station_id.exists' => 'Selected station does not exist.',
            'description.max' => 'Description cannot exceed 1000 characters.',
            'location_details.max' => 'Location details cannot exceed 500 characters.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'gate name',
            'gate_type' => 'gate type',
            'station_id' => 'station',
            'description' => 'description',
            'location_details' => 'location details',
        ];
    }
}
