<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'can_sync_calls',
        'can_edit_extensions',
        'can_update_ips',
        'can_edit_rates',
        'can_manage_pbx',
        'can_export_pdf',
        'can_export_excel',
        'can_view_charts',
    ];

    /**
     * Check if the user is an administrator.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if the user is a regular user.
     */
    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    /**
     * Check specific permission - admins always have all permissions.
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return (bool) $this->getAttribute($permission);
    }

    /**
     * Shortcut methods for each permission.
     */
    public function canSyncCalls(): bool
    {
        return $this->hasPermission('can_sync_calls');
    }

    public function canEditExtensions(): bool
    {
        return $this->hasPermission('can_edit_extensions');
    }

    public function canUpdateIps(): bool
    {
        return $this->hasPermission('can_update_ips');
    }

    public function canEditRates(): bool
    {
        return $this->hasPermission('can_edit_rates');
    }

    public function canManagePbx(): bool
    {
        return $this->hasPermission('can_manage_pbx');
    }

    public function canExportPdf(): bool
    {
        return $this->hasPermission('can_export_pdf');
    }

    public function canExportExcel(): bool
    {
        return $this->hasPermission('can_export_excel');
    }

    public function canViewCharts(): bool
    {
        return $this->hasPermission('can_view_charts');
    }

    /**
     * Get role display name.
     */
    public function getRoleDisplayName(): string
    {
        return match($this->role) {
            'admin' => 'Administrador',
            'supervisor' => 'Supervisor',
            'user' => 'Usuario',
            default => 'Usuario',
        };
    }

    /**
     * Centrales PBX asignadas al usuario.
     */
    public function pbxConnections(): BelongsToMany
    {
        return $this->belongsToMany(PbxConnection::class)->withTimestamps();
    }

    /**
     * Check if user can access a specific PBX connection.
     * Admins can access all connections.
     */
    public function canAccessPbx(int $pbxId): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->pbxConnections()->where('pbx_connection_id', $pbxId)->exists();
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
