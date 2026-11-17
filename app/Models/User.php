<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'password', 'phone', 'locale', 'status', 'is_platform_user', 'last_login_at', 'totp_secret', 'totp_confirmed_at'])]
#[Hidden(['password', 'remember_token', 'totp_secret'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (! $user->public_id) {
                $user->public_id = (string) Str::ulid();
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withoutGlobalScope('tenant')
            ->withPivot('branch_id');
    }

    public function hasPermission(string $key): bool
    {
        return DB::table('user_roles')
            ->join('role_permission', 'role_permission.role_id', '=', 'user_roles.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_permission.permission_id')
            ->where('user_roles.user_id', $this->id)
            ->where('permissions.key', $key)
            ->exists();
    }

    public function hasRole(string $key): bool
    {
        return DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('user_roles.user_id', $this->id)
            ->where('roles.key', $key)
            ->exists();
    }

    public function visibleBranches(): Collection
    {
        $query = Branch::query()->orderBy('name_ar');

        if ($this->hasRole('business_owner')) {
            return $query->get();
        }

        return $query->whereIn('branches.id', function ($subquery): void {
            $subquery->select('branch_id')->from('branch_user')->where('user_id', $this->id);
        })->get();
    }

    public function canAccessBranch(Branch $branch): bool
    {
        if ((int) $branch->tenant_id !== (int) $this->tenant_id) {
            return false;
        }

        if ($this->hasRole('business_owner')) {
            return true;
        }

        return DB::table('branch_user')
            ->where('user_id', $this->id)
            ->where('branch_id', $branch->id)
            ->exists();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'totp_confirmed_at' => 'datetime',
            'totp_secret' => 'encrypted',
            'password' => 'hashed',
            'is_platform_user' => 'boolean',
        ];
    }
}
