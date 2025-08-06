<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
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
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => 'required|string|max:20',
            'password' => 'required|string|min:8|confirmed',
            'address' => 'required|string|max:500',
            'gender' => 'required|in:male,female,other',
            'date_of_birth' => 'required|date|before:today',
            'role_id' => 'nullable|exists:roles,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'username.required' => 'Username is required.',
            'username.unique' => 'Username is already taken.',
            'username.max' => 'Username cannot exceed 255 characters.',
            'email.required' => 'Email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'Email address is already registered.',
            'email.max' => 'Email address cannot exceed 255 characters.',
            'phone.required' => 'Phone number is required.',
            'phone.max' => 'Phone number cannot exceed 20 characters.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
            'address.required' => 'Address is required.',
            'address.max' => 'Address cannot exceed 500 characters.',
            'gender.required' => 'Gender is required.',
            'gender.in' => 'Gender must be male, female, or other.',
            'date_of_birth.required' => 'Date of birth is required.',
            'date_of_birth.date' => 'Date of birth must be a valid date.',
            'date_of_birth.before' => 'Date of birth must be in the past.',
            'role_id.exists' => 'Selected role does not exist.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'username' => 'username',
            'email' => 'email address',
            'phone' => 'phone number',
            'password' => 'password',
            'address' => 'address',
            'gender' => 'gender',
            'date_of_birth' => 'date of birth',
            'role_id' => 'role',
        ];
    }
}
