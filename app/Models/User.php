<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
     * Predefined role templates.
     */
    public static function getRoleTemplates(): array
    {
        return [
            'admin' => [
                'name' => 'Administrador',
                'description' => 'Acceso total al sistema',
                'permissions' => [
                    'can_sync_calls' => true,
                    'can_edit_extensions' => true,
                    'can_update_ips' => true,
                    'can_edit_rates' => true,
                    'can_manage_pbx' => true,
                    'can_export_pdf' => true,
                    'can_export_excel' => true,
                    'can_view_charts' => true,
                ],
            ],
            'supervisor' => [
                'name' => 'Supervisor',
                'description' => 'Puede sincronizar y ver reportes avanzados',
                'permissions' => [
                    'can_sync_calls' => true,
                    'can_edit_extensions' => false,
                    'can_update_ips' => false,
                    'can_edit_rates' => false,
                    'can_manage_pbx' => false,
                    'can_export_pdf' => true,
                    'can_export_excel' => true,
                    'can_view_charts' => true,
                ],
            ],
            'operator' => [
                'name' => 'Operador',
                'description' => 'Puede editar extensiones y exportar',
                'permissions' => [
                    'can_sync_calls' => false,
                    'can_edit_extensions' => true,
                    'can_update_ips' => true,
                    'can_edit_rates' => false,
                    'can_manage_pbx' => false,
                    'can_export_pdf' => true,
                    'can_export_excel' => true,
                    'can_view_charts' => true,
                ],
            ],
            'viewer' => [
                'name' => 'Solo Lectura',
                'description' => 'Solo puede ver información y exportar',
                'permissions' => [
                    'can_sync_calls' => false,
                    'can_edit_extensions' => false,
                    'can_update_ips' => false,
                    'can_edit_rates' => false,
                    'can_manage_pbx' => false,
                    'can_export_pdf' => true,
                    'can_export_excel' => true,
                    'can_view_charts' => true,
                ],
            ],
            'basic' => [
                'name' => 'Básico',
                'description' => 'Acceso mínimo - solo ver llamadas',
                'permissions' => [
                    'can_sync_calls' => false,
                    'can_edit_extensions' => false,
                    'can_update_ips' => false,
                    'can_edit_rates' => false,
                    'can_manage_pbx' => false,
                    'can_export_pdf' => false,
                    'can_export_excel' => false,
                    'can_view_charts' => false,
                ],
            ],
        ];
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
