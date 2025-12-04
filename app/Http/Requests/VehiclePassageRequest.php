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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'vehicle_id' => 'required|exists:vehicles,id',
            'account_id' => 'nullable|exists:accounts,id',
            'bundle_subscription_id' => 'nullable|exists:bundle_subscriptions,id',
            'payment_type_id' => 'required|exists:payment_types,id',
            'entry_time' => 'nullable|date',
            'entry_operator_id' => 'nullable|exists:users,id',
            'entry_gate_id' => 'required|exists:gates,id',
            'entry_station_id' => 'required|exists:stations,id',
            'exit_time' => 'nullable|date|after:entry_time',
            'exit_operator_id' => 'nullable|exists:users,id',
            'exit_gate_id' => 'nullable|exists:gates,id',
            'exit_station_id' => 'nullable|exists:stations,id',
            'base_amount' => 'required|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'passage_type' => 'required|in:toll,free,exempted',
            'is_exempted' => 'nullable|boolean',
            'exemption_reason' => 'nullable|string|max:255',
            'status' => 'nullable|in:active,cancelled,refunded',
            'duration_minutes' => 'nullable|integer|min:0',
            'notes' => 'nullable|string|max:500',
        ];

        // If this is an update request, make some fields optional
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['vehicle_id'] = 'sometimes|exists:vehicles,id';
            $rules['payment_type_id'] = 'sometimes|exists:payment_types,id';
            $rules['entry_gate_id'] = 'sometimes|exists:gates,id';
            $rules['entry_station_id'] = 'sometimes|exists:stations,id';
            $rules['base_amount'] = 'sometimes|numeric|min:0';
            $rules['total_amount'] = 'sometimes|numeric|min:0';
            $rules['passage_type'] = 'sometimes|in:toll,free,exempted';
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'vehicle_id.required' => 'Vehicle is required.',
            'vehicle_id.exists' => 'Selected vehicle does not exist.',
            'account_id.exists' => 'Selected account does not exist.',
            'bundle_subscription_id.exists' => 'Selected bundle subscription does not exist.',
            'payment_type_id.required' => 'Payment type is required.',
            'payment_type_id.exists' => 'Selected payment type does not exist.',
            'entry_time.date' => 'Entry time must be a valid date.',
            'entry_operator_id.exists' => 'Selected entry operator does not exist.',
            'entry_gate_id.required' => 'Entry gate is required.',
            'entry_gate_id.exists' => 'Selected entry gate does not exist.',
            'entry_station_id.required' => 'Entry station is required.',
            'entry_station_id.exists' => 'Selected entry station does not exist.',
            'exit_time.date' => 'Exit time must be a valid date.',
            'exit_time.after' => 'Exit time must be after entry time.',
            'exit_operator_id.exists' => 'Selected exit operator does not exist.',
            'exit_gate_id.exists' => 'Selected exit gate does not exist.',
            'exit_station_id.exists' => 'Selected exit station does not exist.',
            'base_amount.required' => 'Base amount is required.',
            'base_amount.numeric' => 'Base amount must be a number.',
            'base_amount.min' => 'Base amount must be at least 0.',
            'discount_amount.numeric' => 'Discount amount must be a number.',
            'discount_amount.min' => 'Discount amount must be at least 0.',
            'total_amount.required' => 'Total amount is required.',
            'total_amount.numeric' => 'Total amount must be a number.',
            'total_amount.min' => 'Total amount must be at least 0.',
            'passage_type.required' => 'Passage type is required.',
            'passage_type.in' => 'Passage type must be toll, free, or exempted.',
            'is_exempted.boolean' => 'Is exempted must be true or false.',
            'exemption_reason.max' => 'Exemption reason cannot exceed 255 characters.',
            'status.in' => 'Status must be active, cancelled, or refunded.',
            'duration_minutes.integer' => 'Duration minutes must be a whole number.',
            'duration_minutes.min' => 'Duration minutes must be at least 0.',
            'notes.max' => 'Notes cannot exceed 500 characters.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes(): array
    {
        return [
            'vehicle_id' => 'vehicle',
            'account_id' => 'account',
            'bundle_subscription_id' => 'bundle subscription',
            'payment_type_id' => 'payment type',
            'entry_time' => 'entry time',
            'entry_operator_id' => 'entry operator',
            'entry_gate_id' => 'entry gate',
            'entry_station_id' => 'entry station',
            'exit_time' => 'exit time',
            'exit_operator_id' => 'exit operator',
            'exit_gate_id' => 'exit gate',
            'exit_station_id' => 'exit station',
            'base_amount' => 'base amount',
            'discount_amount' => 'discount amount',
            'total_amount' => 'total amount',
            'passage_type' => 'passage type',
            'is_exempted' => 'is exempted',
            'exemption_reason' => 'exemption reason',
            'status' => 'status',
            'duration_minutes' => 'duration minutes',
            'notes' => 'notes',
        ];
    }
}
