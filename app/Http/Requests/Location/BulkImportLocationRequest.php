<?php

declare(strict_types=1);

namespace App\Http\Requests\Location;

use Illuminate\Foundation\Http\FormRequest;

class BulkImportLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'locations' => ['required', 'array', 'min:1'],
            'locations.*.name' => ['required', 'string', 'max:255'],
            'locations.*.address' => ['nullable', 'string', 'max:255'],
            'locations.*.city' => ['nullable', 'string', 'max:100'],
            'locations.*.state' => ['nullable', 'string', 'max:100'],
            'locations.*.postal_code' => ['nullable', 'string', 'max:20'],
            'locations.*.country' => ['nullable', 'string', 'size:2'],
            'locations.*.phone' => ['nullable', 'string'],
            'locations.*.website' => ['nullable', 'url'],
            'locations.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'locations.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'locations.*.primary_category' => ['nullable', 'string', 'max:100'],
            'locations.*.categories' => ['nullable', 'array'],
        ];
    }
}
