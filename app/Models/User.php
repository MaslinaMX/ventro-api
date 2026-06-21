<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'first_name', 'last_name', 'email', 'password',
        'phone', 'employee_number', 'security_pin', 'pin_updated_at',
        'role', 'permissions', 'is_seller', 'is_deletable', 'activo', 'sucursal_id',
        'invite_token', 'invited_at', 'activated_at', 'pin_changed',
    ];

    protected $hidden = ['password', 'remember_token', 'security_pin'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'pin_updated_at' => 'datetime',
        'password' => 'hashed',
        'security_pin' => 'hashed',
        'is_seller' => 'boolean',
        'is_deletable' => 'boolean',
        'activo' => 'boolean',
        'permissions' => 'array',
        'invited_at' => 'datetime',
        'activated_at' => 'datetime',
        'pin_changed' => 'boolean',
    ];

    // ─── Relaciones ───────────────────────────────────────────────────────────
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class);
    }

    // ─── Permisos ─────────────────────────────────────────────────────────────
    public function hasPermission(string $permission): bool
    {
        if ($this->role === 'admin') {
            return true;
        }

        if ($this->role === 'vendedor') {
            return in_array($permission, [
                'ventas.crear', 'ventas.ver',
                'clientes.crear', 'clientes.ver',
                'productos.ver',
                'caja.abrir', 'caja.cerrar',
            ]);
        }

        // Personalizado
        [$modulo, $accion] = explode('.', $permission);

        return in_array($accion, $this->permissions[$modulo] ?? []);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────
    public static function nextEmployeeNumber(): string
    {
        $last = self::whereNotNull('employee_number')
            ->orderByDesc('employee_number')
            ->value('employee_number');

        if (! $last) {
            return 'EMP-0001';
        }

        $number = (int) str_replace('EMP-', '', $last);

        return 'EMP-'.str_pad($number + 1, 4, '0', STR_PAD_LEFT);
    }
}
