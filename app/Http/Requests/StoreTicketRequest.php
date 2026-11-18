<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Invalid input is rejected here (422) before the agent ever runs. */
class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** "+355 69 000 0001" -> "+355690000001", so the ownership check compares the same format. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->customer_phone)) {
            $this->merge(['customer_phone' => preg_replace('/[\s\-()]/', '', $this->customer_phone)]);
        }
    }

    public function rules(): array
    {
        return [
            'channel' => ['required', 'in:whatsapp,walk_in'],
            'customer_phone' => ['required', 'regex:/^\+[1-9]\d{7,14}$/'], // international (E.164) format
            'customer_name' => ['nullable', 'string', 'max:100'],
            'message' => ['required', 'string', 'min:10', 'max:'.config('agent.max_input_chars')],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('agent.max_photo_kb')],
        ];
    }

    public function messages(): array
    {
        return ['customer_phone.regex' => 'The phone number must be in international format, e.g. +355690000001.'];
    }
}
