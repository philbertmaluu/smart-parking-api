<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OperatorRequest extends FormRequest
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
        $rules = [];

        // For assigning operator to station
        if ($this->route()->getName() === 'operators.assign-station' || 
            $this->isMethod('POST') && $this->route()->parameter('operatorId')) {
            $rules = [
                'station_id' => 'required|exists:stations,id',
            ];
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'station_id.required' => 'Station is required.',
            'station_id.exists' => 'Selected station does not exist.',
        ];
    }
}
