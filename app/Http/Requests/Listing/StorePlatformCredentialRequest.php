<?php

declare(strict_types=1);

namespace App\Http\Requests\Listing;

use Illuminate\Foundation\Http\FormRequest;

class StorePlatformCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'platform' => ['required', 'string', 'in:facebook,google,bing'],
            'access_token' => ['required', 'string'],
            'page_id' => ['required', 'string'],
            'page_access_token' => ['nullable', 'string'],
            'scopes' => ['nullable', 'array'],
            'scopes.*' => ['string'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'platform.in' => 'The platform must be one of: facebook, google, bing.',
            'access_token.required' => 'An access token is required to connect the platform.',
            'page_id.required' => 'A page ID is required to connect the platform.',
        ];
    }
}
