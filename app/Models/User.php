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
        'role', 'is_seller', 'is_deletable', 'activo', 'sucursal_id',
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
        'invited_at' => 'datetime',
        'activated_at' => 'datetime',
        'pin_changed' => 'boolean',
    ];

    // ─── Roles válidos ──────────────────────────────────────────────────────
    public const ROLE_ADMIN_EMPRESA = 'admin_empresa';

    public const ROLE_ADMIN_SUCURSAL = 'admin_sucursal';

    public const ROLE_VENDEDOR = 'vendedor';

    public const ROLES = [
        self::ROLE_ADMIN_EMPRESA,
        self::ROLE_ADMIN_SUCURSAL,
        self::ROLE_VENDEDOR,
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

    // ─── Alcance / Permisos ─────────────────────────────────────────────────
    /**
     * admin_empresa: todas las sucursales del tenant.
     * admin_sucursal: gestión de productos, inventario, usuarios de su sucursal.
     * vendedor: ventas, cobros, consulta de productos (su caja).
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->role === self::ROLE_ADMIN_EMPRESA) {
            return true;
        }

        if ($this->role === self::ROLE_ADMIN_SUCURSAL) {
            return in_array($permission, [
                'productos.crear', 'productos.editar', 'productos.ver', 'productos.eliminar',
                'inventario.crear', 'inventario.editar', 'inventario.ver', 'inventario.ajustar',
                'usuarios.crear', 'usuarios.editar', 'usuarios.ver',
                'ventas.crear', 'ventas.ver',
                'clientes.crear', 'clientes.ver',
                'caja.abrir', 'caja.cerrar',
            ]);
        }

        if ($this->role === self::ROLE_VENDEDOR) {
            return in_array($permission, [
                'ventas.crear', 'ventas.ver',
                'clientes.crear', 'clientes.ver',
                'productos.ver',
                'caja.abrir', 'caja.cerrar',
            ]);
        }

        return false;
    }

    /**
     * true si el usuario solo debe ver/operar dentro de su propia sucursal
     * (admin_sucursal y vendedor). admin_empresa no tiene restricción.
     */
    public function isScopedToSucursal(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN_SUCURSAL, self::ROLE_VENDEDOR]);
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
