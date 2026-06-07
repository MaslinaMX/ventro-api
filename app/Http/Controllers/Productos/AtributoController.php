<?php

namespace App\Http\Controllers\Productos;

use App\Http\Controllers\Controller;
use App\Models\Atributo;
use App\Models\AtributoValor;
use Illuminate\Http\Request;

class AtributoController extends Controller
{
    public function index()
    {
        return response()->json(Atributo::with('valores')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => ['required', 'string'],
            'valores' => ['nullable', 'array'],
            'valores.*.valor' => ['required', 'string'],
        ]);

        $atributo = Atributo::create(['nombre' => $request->nombre]);

        if ($request->valores) {
            foreach ($request->valores as $v) {
                AtributoValor::create([
                    'atributo_id' => $atributo->id,
                    'valor' => $v['valor'],
                ]);
            }
        }

        return response()->json($atributo->load('valores'), 201);
    }

    public function show(int $id)
    {
        $atributo = Atributo::findOrFail($id);

        return response()->json($atributo->load('valores'));
    }

    public function update(Request $request, int $id)
    {
        $atributo = Atributo::findOrFail($id);

        $request->validate([
            'nombre' => ['sometimes', 'string'],
        ]);

        $atributo->update($request->only(['nombre']));

        return response()->json($atributo->load('valores'));
    }

    public function destroy(int $id)
    {
        $atributo = Atributo::findOrFail($id);
        $atributo->delete();

        return response()->json(['message' => 'Atributo eliminado.']);
    }

    public function agregarValor(Request $request, int $id)
    {
        $atributo = Atributo::findOrFail($id);

        $request->validate([
            'valor' => ['required', 'string'],
        ]);

        $valor = AtributoValor::create([
            'atributo_id' => $atributo->id,
            'valor' => $request->valor,
        ]);

        return response()->json($valor, 201);
    }

    public function eliminarValor(int $id)
    {
        $valor = AtributoValor::findOrFail($id);
        $valor->delete();

        return response()->json(['message' => 'Valor eliminado.']);
    }
}
