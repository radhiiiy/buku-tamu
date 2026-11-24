<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'guest_name' => ['required', 'string', 'min:2', 'max:120'],
            'guest_phone' => ['required', 'string', 'regex:/^\+?[0-9][0-9\s\-]{6,17}$/'],
            'guest_company' => ['nullable', 'string', 'max:150'],
            'guest_email' => ['nullable', 'email', 'max:150'],
            'purpose' => ['required', Rule::in(config('visitor.purposes'))],
            'host_id' => ['required', Rule::exists('hosts', 'id')->where('is_active', true)],
            'notes' => ['nullable', 'string', 'max:500'],
            'consent' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'guest_name.required' => __('public.err.name'),
            'guest_phone.required' => __('public.err.phone'),
            'guest_phone.regex' => __('public.err.phone'),
            'purpose.required' => __('public.err.purpose'),
            'purpose.in' => __('public.err.purpose'),
            'host_id.required' => __('public.err.host'),
            'host_id.exists' => __('public.err.host'),
            'guest_email.email' => __('public.err.email'),
            'consent.accepted' => __('public.err.consent'),
        ];
    }
}
