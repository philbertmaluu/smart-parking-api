<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GateDeviceRequest extends FormRequest
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
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        $rules = [
            'gate_id' => $isUpdate ? 'sometimes|exists:gates,id' : 'required|exists:gates,id',
            'device_type' => $isUpdate ? 'sometimes|in:camera' : 'required|in:camera',
            'name' => 'nullable|string|max:255',
            'ip_address' => $isUpdate ? 'sometimes|ip' : 'required|ip',
            'http_port' => 'nullable|integer|min:1|max:65535',
            'rtsp_port' => 'nullable|integer|min:1|max:65535',
            'username' => $isUpdate ? 'sometimes|string|max:255' : 'required|string|max:255',
            'password' => $isUpdate ? 'nullable|string|max:255' : 'required|string|max:255',
            'direction' => 'nullable|in:entry,exit,both',
            'status' => 'nullable|in:active,inactive,maintenance,error',
        ];

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'gate_id.required' => 'Gate is required.',
            'gate_id.exists' => 'Selected gate does not exist.',
            'device_type.required' => 'Device type is required.',
            'device_type.in' => 'Device type must be camera.',
            'ip_address.required' => 'IP address is required.',
            'ip_address.ip' => 'IP address must be a valid IP address.',
            'http_port.integer' => 'HTTP port must be a number.',
            'http_port.min' => 'HTTP port must be at least 1.',
            'http_port.max' => 'HTTP port cannot exceed 65535.',
            'rtsp_port.integer' => 'RTSP port must be a number.',
            'rtsp_port.min' => 'RTSP port must be at least 1.',
            'rtsp_port.max' => 'RTSP port cannot exceed 65535.',
            'username.required' => 'Username is required.',
            'password.required' => 'Password is required.',
            'direction.in' => 'Direction must be entry, exit, or both.',
            'status.in' => 'Status must be active, inactive, maintenance, or error.',
        ];
    }
}
