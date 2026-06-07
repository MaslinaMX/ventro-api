<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Models\Domain;

class CheckDomainController extends Controller
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'slug' => ['required', 'string', 'min:3', 'max:50'],
        ]);

        $slug = Str::slug($request->slug);

        // Verificar que no esté reservado
        $reserved = ['api', 'app', 'www', 'admin', 'ventro', 'mail', 'smtp', 'test'];
        if (in_array($slug, $reserved)) {
            return response()->json([
                'available' => false,
                'slug' => $slug,
                'message' => 'Este nombre está reservado.',
            ]);
        }

        // Verificar que no exista ya como dominio
        $exists = Domain::where('domain', $slug.'.ventro.com.mx')->exists();

        return response()->json([
            'available' => ! $exists,
            'slug' => $slug,
            'message' => $exists ? 'Este nombre ya está en uso.' : 'Disponible.',
        ]);
    }
}
