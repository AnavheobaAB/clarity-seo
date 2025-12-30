<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'current_tenant_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function ownedTenants(): BelongsToMany
    {
        return $this->tenants()->wherePivot('role', 'owner');
    }

    public function currentTenant(): ?Tenant
    {
        if ($this->current_tenant_id) {
            return $this->tenants()->find($this->current_tenant_id);
        }

        return $this->tenants()->first();
    }

    public function belongsToTenant(Tenant $tenant): bool
    {
        return $this->tenants()->where('tenant_id', $tenant->id)->exists();
    }

    public function getRoleInTenant(Tenant $tenant): ?string
    {
        $membership = $this->tenants()->where('tenant_id', $tenant->id)->first();

        return $membership?->pivot->role;
    }

    public function switchTenant(Tenant $tenant): void
    {
        if ($this->belongsToTenant($tenant)) {
            $this->update(['current_tenant_id' => $tenant->id]);
        }
    }
}
