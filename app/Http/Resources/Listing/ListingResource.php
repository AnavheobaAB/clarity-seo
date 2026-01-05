<?php

declare(strict_types=1);

namespace App\Http\Resources\Listing;

use App\Http\Resources\Location\LocationResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ListingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'location_id' => $this->location_id,
            'platform' => $this->platform,
            'external_id' => $this->external_id,
            'status' => $this->status,
            'name' => $this->name,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
            'phone' => $this->phone,
            'website' => $this->website,
            'categories' => $this->categories,
            'business_hours' => $this->business_hours,
            'description' => $this->description,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'attributes' => $this->attributes,
            'discrepancies' => $this->discrepancies,
            'has_discrepancies' => $this->hasDiscrepancies(),
            'last_synced_at' => $this->last_synced_at,
            'last_published_at' => $this->last_published_at,
            'error_message' => $this->error_message,
            'location' => new LocationResource($this->whenLoaded('location')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
