<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantUser extends Model
{
    protected $fillable = ['email', 'tenant_id'];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
