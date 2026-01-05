<?php

declare(strict_types=1);

namespace App\Http\Resources\Listing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ListingStatsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'total_listings' => $this->resource['total_listings'],
            'by_platform' => $this->resource['by_platform'],
            'by_status' => $this->resource['by_status'],
            'with_discrepancies' => $this->resource['with_discrepancies'],
            'recently_synced' => $this->resource['recently_synced'],
        ];
    }
}
