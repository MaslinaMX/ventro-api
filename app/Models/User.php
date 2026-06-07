<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name', 'first_name', 'last_name', 'email', 'password',
    'phone', 'employee_number', 'security_pin', 'pin_updated_at',
    'role', 'is_seller', 'is_deletable', 'sucursal_id',
])]
#[Hidden(['password', 'remember_token', 'security_pin'])]
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'pin_updated_at' => 'datetime',
            'password' => 'hashed',
            'security_pin' => 'hashed',
            'is_seller' => 'boolean',
            'is_deletable' => 'boolean',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class);
    }

    // Genera el siguiente employee_number correlativo para este tenant
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
