<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VehiclePassageRequest extends FormRequest
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
            'vehicle_id' => 'required|exists:vehicles,id',
            'entry_gate_id' => 'required|exists:gates,id',
            'exit_gate_id' => 'nullable|exists:gates,id',
            'entry_time' => 'required|date',
            'exit_time' => 'nullable|date|after:entry_time',
            'total_amount' => 'required|numeric|min:0',
            'payment_type_id' => 'required|exists:payment_types,id',
            'status' => 'required|in:pending,completed,cancelled',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'vehicle_id.required' => 'Vehicle is required.',
            'vehicle_id.exists' => 'Selected vehicle does not exist.',
            'entry_gate_id.required' => 'Entry gate is required.',
            'entry_gate_id.exists' => 'Selected entry gate does not exist.',
            'exit_gate_id.exists' => 'Selected exit gate does not exist.',
            'entry_time.required' => 'Entry time is required.',
            'entry_time.date' => 'Entry time must be a valid date.',
            'exit_time.date' => 'Exit time must be a valid date.',
            'exit_time.after' => 'Exit time must be after entry time.',
            'total_amount.required' => 'Total amount is required.',
            'total_amount.numeric' => 'Total amount must be a valid number.',
            'total_amount.min' => 'Total amount cannot be negative.',
            'payment_type_id.required' => 'Payment type is required.',
            'payment_type_id.exists' => 'Selected payment type does not exist.',
            'status.required' => 'Status is required.',
            'status.in' => 'Status must be pending, completed, or cancelled.',
            'notes.max' => 'Notes cannot exceed 1000 characters.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'vehicle_id' => 'vehicle',
            'entry_gate_id' => 'entry gate',
            'exit_gate_id' => 'exit gate',
            'entry_time' => 'entry time',
            'exit_time' => 'exit time',
            'total_amount' => 'total amount',
            'payment_type_id' => 'payment type',
            'status' => 'status',
            'notes' => 'notes',
        ];
    }
}
