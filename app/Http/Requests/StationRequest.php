<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StationRequest extends FormRequest
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
        $stationId = $this->route('station');
        $uniqueRule = $stationId ? "unique:stations,code,{$stationId}" : 'unique:stations,code';

        return [
            'name' => 'required|string|max:255',
            'location' => 'required|string|max:500',
            'code' => "required|string|max:50|{$uniqueRule}",
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Station name is required.',
            'name.max' => 'Station name cannot exceed 255 characters.',
            'location.required' => 'Station location is required.',
            'location.max' => 'Station location cannot exceed 500 characters.',
            'code.required' => 'Station code is required.',
            'code.unique' => 'Station code must be unique.',
            'code.max' => 'Station code cannot exceed 50 characters.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'station name',
            'location' => 'station location',
            'code' => 'station code',
        ];
    }
}
