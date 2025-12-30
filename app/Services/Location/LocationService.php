<?php

declare(strict_types=1);

namespace App\Services\Location;

use App\Models\Location;
use App\Models\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class LocationService
{
    public function list(Tenant $tenant, array $filters = []): LengthAwarePaginator
    {
        $query = $tenant->locations()->getQuery();

        if (isset($filters['search'])) {
            $query->where(function (Builder $q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                    ->orWhere('city', 'like', "%{$filters['search']}%")
                    ->orWhere('address', 'like', "%{$filters['search']}%");
            });
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['city'])) {
            $query->where('city', $filters['city']);
        }

        $perPage = $filters['per_page'] ?? 15;

        return $query->latest()->paginate($perPage);
    }

    public function create(Tenant $tenant, array $data): Location
    {
        $data['status'] = $data['status'] ?? 'active';

        return $tenant->locations()->create($data);
    }

    public function update(Location $location, array $data): Location
    {
        $location->update($data);

        return $location->fresh();
    }

    public function delete(Location $location): void
    {
        $location->delete();
    }

    public function bulkImport(Tenant $tenant, array $locations): int
    {
        $count = 0;

        foreach ($locations as $locationData) {
            $tenant->locations()->create($locationData);
            $count++;
        }

        return $count;
    }

    public function bulkDelete(Tenant $tenant, array $ids): int
    {
        return $tenant->locations()->whereIn('id', $ids)->delete();
    }

    public function findForTenant(Tenant $tenant, int $locationId): ?Location
    {
        return $tenant->locations()->find($locationId);
    }
}
