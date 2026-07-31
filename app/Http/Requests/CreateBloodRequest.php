<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateBloodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patientName'     => 'required|string|min:2|max:255',
            'bloodGroup'      => 'required|string|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
            'unitsRequired'   => 'required|integer|min:1|max:20',
            'hospitalName'    => 'required|string|min:3|max:255',
            'location'        => 'required|string|min:3|max:255',
            'contactNumber'   => 'required|string|max:20',
            'urgencyLevel'    => 'required|string|in:Immediate,Critical,Standard',
            'additionalNotes' => 'nullable|string|max:1000',
            'latitude'        => 'nullable|numeric',
            'longitude'       => 'nullable|numeric',
        ];
    }
}
