<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'address',
        'address2',
        'city',
        'state',
        'postal_code',
        'country',
        'phone',
        'website',
        'latitude',
        'longitude',
        'primary_category',
        'categories',
        'business_hours',
        'status',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'categories' => 'array',
            'business_hours' => 'array',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
