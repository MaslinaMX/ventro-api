<?php

namespace App\Http\Controllers;

use App\Models\ConfiguracionTicket;
use Illuminate\Http\Request;

class ConfiguracionTicketController extends Controller
{
    public function show()
    {
        return response()->json(ConfiguracionTicket::obtener());
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'mostrar_logo' => 'sometimes|boolean',
            'mensaje_personalizado' => 'nullable|string|max:255',
        ]);

        $config = ConfiguracionTicket::obtener();
        $config->update($data);

        return response()->json($config);
    }
}
