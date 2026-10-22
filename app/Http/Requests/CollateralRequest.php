<?php

namespace App\Http\Requests;

use App\Enums\CollateralType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CollateralRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(CollateralType::class)],
            'description' => ['required', 'string', 'max:255'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'owner_name' => ['nullable', 'string', 'max:150'],
            'estimated_value' => ['required', 'numeric', 'min:1'],
            'valuation_date' => ['nullable', 'date', 'before_or_equal:today'],
            'location' => ['nullable', 'string', 'max:255'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }
}
