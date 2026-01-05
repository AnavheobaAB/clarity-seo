<?php

declare(strict_types=1);

namespace App\Http\Resources\Listing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlatformCredentialResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'platform' => $this->platform,
            'is_active' => $this->is_active,
            'is_valid' => $this->isValid(),
            'expires_at' => $this->expires_at,
            'scopes' => $this->scopes,
            'page_id' => $this->getPageId(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
