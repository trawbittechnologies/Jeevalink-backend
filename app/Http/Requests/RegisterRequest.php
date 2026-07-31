<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'           => 'required_without:fullName|nullable|string|max:255',
            'fullName'       => 'nullable|string|max:255',
            'email'          => 'required|email|unique:users,email',
            'password'       => 'required|string|min:6',
            'mobile'         => 'required|string|max:20',
            'bloodGroup'     => 'nullable|string|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
            'district'       => 'nullable|string|max:255',
            'city'           => 'nullable|string|max:255',
            'role'           => 'nullable|string|in:user,donor,volunteer,unit_squad,block_admin,super_admin,technical_admin',
            'isDonor'        => 'nullable|boolean',
            'dateOfBirth'    => 'nullable|date',
            'lastDonatedAt'  => 'nullable|date',
            'profilePicture' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'An email address is required.',
            'email.unique'   => 'This email address is already registered.',
            'mobile.required' => 'A valid mobile number is required.',
            'password.min'   => 'Password must be at least 6 characters.',
        ];
    }
}
