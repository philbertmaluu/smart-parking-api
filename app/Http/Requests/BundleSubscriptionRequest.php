<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BundleSubscriptionRequest extends FormRequest
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
        $subscriptionId = $this->route('bundle_subscription');
        
        return [
            'account_id' => 'required|integer|exists:accounts,id',
            'bundle_id' => 'required|integer|exists:bundles,id',
            'start_datetime' => 'required|date|after_or_equal:today',
            'end_datetime' => 'required|date|after:start_datetime',
            'amount' => 'required|numeric|min:0',
            'status' => 'required|in:pending,active,suspended,expired,cancelled',
            'auto_renew' => 'boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'account_id.required' => 'Customer account is required.',
            'account_id.exists' => 'Selected customer account does not exist.',
            'bundle_id.required' => 'Bundle is required.',
            'bundle_id.exists' => 'Selected bundle does not exist.',
            'start_datetime.required' => 'Start date is required.',
            'start_datetime.date' => 'Start date must be a valid date.',
            'start_datetime.after_or_equal' => 'Start date cannot be in the past.',
            'end_datetime.required' => 'End date is required.',
            'end_datetime.date' => 'End date must be a valid date.',
            'end_datetime.after' => 'End date must be after start date.',
            'amount.required' => 'Subscription amount is required.',
            'amount.numeric' => 'Subscription amount must be a valid number.',
            'amount.min' => 'Subscription amount must be at least 0.',
            'status.required' => 'Subscription status is required.',
            'status.in' => 'Invalid subscription status.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'account_id' => 'customer account',
            'bundle_id' => 'bundle',
            'start_datetime' => 'start date',
            'end_datetime' => 'end date',
            'amount' => 'subscription amount',
            'status' => 'status',
            'auto_renew' => 'auto renew',
        ];
    }
}
