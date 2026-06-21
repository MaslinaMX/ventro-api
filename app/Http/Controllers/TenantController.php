<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TenantController extends Controller
{
    public function show()
    {
        return response()->json(tenant());
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => 'sometimes|string',
            'razon_social' => 'nullable|string',
            'logo' => 'nullable|string',
        ]);

        tenant()->update($data);

        return response()->json(tenant());
    }

    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        // Eliminar logo anterior si existe
        $current = tenant('logo');
        if ($current) {
            $oldPath = str_replace(env('CLOUDFLARE_R2_URL').'/', '', $current);
            Storage::disk('r2')->delete($oldPath);
        }

        $tenantId = tenant('id');
        $path = $request->file('logo')->storeAs(
            "tenants/{$tenantId}/logos",
            'logo_'.time().'.'.$request->file('logo')->extension(),
            'r2'
        );

        $url = env('CLOUDFLARE_R2_URL').'/'.$path;
        tenant()->update(['logo' => $url]);

        return response()->json(['logo' => $url]);
    }
}
