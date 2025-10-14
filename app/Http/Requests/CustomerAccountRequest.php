<?php

namespace App\Http\Requests;

use App\Models\Account;
use Illuminate\Foundation\Http\FormRequest;

class CustomerAccountRequest extends FormRequest
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
        $accountId = $this->route('customer_account');
        $userId = $this->route('customer_account') ? 
            Account::find($accountId)?->customer?->user?->id : null;
        
        return [
            // User validation
            'username' => 'required|string|max:50|unique:users,username,' . $userId,
            'email' => 'required|email|max:100|unique:users,email,' . $userId,
            'phone' => 'required|string|max:20|unique:users,phone,' . $userId,
            // Password is handled by backend - not validated from request
            'address' => 'nullable|string|max:255',
            'gender' => 'nullable|in:male,female,other',
            'date_of_birth' => 'nullable|date|before:today',
            'is_active' => 'boolean',

            // Customer validation
            'customer_number' => 'nullable|string|max:50|unique:customers,customer_number',
            'name' => 'required|string|max:100',
            'company_name' => 'nullable|string|max:100',
            'customer_type' => 'required|in:individual,corporate',

            // Account validation
            'account_number' => 'nullable|string|max:50|unique:accounts,account_number,' . $accountId,
            'account_name' => 'required|string|max:100',
            'account_type' => 'required|in:prepaid,postpaid',
            'initial_balance' => 'required|numeric|min:0',
            'balance' => 'nullable|numeric|min:0',
            'credit_limit' => 'nullable|numeric|min:0',
            'account_is_active' => 'boolean',
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
            // User messages
            'username.required' => 'Username is required.',
            'username.unique' => 'Username must be unique.',
            'email.required' => 'Email is required.',
            'email.email' => 'Email must be a valid email address.',
            'email.unique' => 'Email must be unique.',
            'phone.required' => 'Phone number is required.',
            'phone.unique' => 'Phone number must be unique.',
            // Password validation messages removed - handled by backend
            'gender.in' => 'Gender must be male, female, or other.',
            'date_of_birth.date' => 'Date of birth must be a valid date.',
            'date_of_birth.before' => 'Date of birth must be before today.',

            // Customer messages
            'customer_number.unique' => 'Customer number must be unique.',
            'name.required' => 'Customer name is required.',
            'customer_type.required' => 'Customer type is required.',
            'customer_type.in' => 'Customer type must be individual or corporate.',

            // Account messages
            'account_number.unique' => 'Account number must be unique.',
            'account_name.required' => 'Account name is required.',
            'account_type.required' => 'Account type is required.',
            'account_type.in' => 'Account type must be prepaid or postpaid.',
            'initial_balance.required' => 'Initial balance is required.',
            'initial_balance.min' => 'Initial balance must be at least 0.',
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

            // Password validation removed - handled entirely by backend
        });
    }
}
