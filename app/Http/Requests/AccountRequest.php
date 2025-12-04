<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AccountRequest extends FormRequest
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
        $accountId = $this->route('account');
        
        return [
            'customer_id' => 'required|integer|exists:customers,id',
            'account_number' => "nullable|string|max:50|unique:accounts,account_number,{$accountId},id,deleted_at,NULL",
            'name' => 'required|string|max:100',
            'account_type' => 'required|in:prepaid,postpaid',
            'balance' => 'required|numeric|min:0',
            'credit_limit' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_id.required' => 'Customer is required.',
            'customer_id.exists' => 'Selected customer does not exist.',
            'account_number.unique' => 'Account number must be unique.',
            'name.required' => 'Account name is required.',
            'account_type.required' => 'Account type is required.',
            'account_type.in' => 'Account type must be either prepaid or postpaid.',
            'balance.required' => 'Balance is required.',
            'balance.min' => 'Balance must be at least 0.',
            'credit_limit.min' => 'Credit limit must be at least 0.',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Credit limit is required for postpaid accounts
            if ($this->account_type === 'postpaid' && (!$this->credit_limit || $this->credit_limit <= 0)) {
                $validator->errors()->add('credit_limit', 'Credit limit is required for postpaid accounts.');
            }
        });
    }
}
