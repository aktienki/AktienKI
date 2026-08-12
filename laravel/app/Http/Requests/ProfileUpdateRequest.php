<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'locale' => ['sometimes', Rule::in(['de', 'en'])],
            'risk_level' => ['sometimes', Rule::in(['cautious', 'normal', 'opportunity_oriented'])],
            'email_service' => ['sometimes', 'boolean'],
            'email_market_summary' => ['sometimes', 'boolean'],
            'email_price_alerts' => ['sometimes', 'boolean'],
            'email_product_updates' => ['sometimes', 'boolean'],
            'mobile_nav_order' => ['nullable', 'json'],
            'mobile_nav_hidden' => ['nullable', 'json'],
            'return_to' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
