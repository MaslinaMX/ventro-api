<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class TestTenantSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::create([
            'id' => 'test-tenant',
            'name' => 'Repostería de Prueba',
            'email' => 'admin@reposteria.com',
            'plan' => 'basic',
            'status' => 'active',
        ]);

        User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Ramon Olivares',
            'email' => 'ramon@reposteria.com',
            'password' => bcrypt('password123'),
        ]);
    }
}
