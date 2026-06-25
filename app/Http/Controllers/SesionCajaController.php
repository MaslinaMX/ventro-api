<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\VerificaEmpleadoPorPin;
use App\Models\Caja;
use App\Models\SesionCaja;
use App\Models\User;
use Illuminate\Http\Request;

class SesionCajaController extends Controller
{
    use VerificaEmpleadoPorPin;

    public function activa(Request $request, $cajaId)
    {
        $sesion = SesionCaja::where('caja_id', $cajaId)
            ->where('estado', 'abierta')
            ->with('usuario')
            ->first();

        return response()->json($sesion);
    }

    public function abrir(Request $request, $cajaId)
    {
        $empleado = $this->verificarEmpleadoPin($request);

        if (! $empleado->hasPermission('caja.abrir')) {
            return response()->json(['message' => 'Este empleado no tiene permiso para abrir caja.'], 403);
        }

        $caja = Caja::findOrFail($cajaId);

        $yaAbierta = SesionCaja::where('caja_id', $caja->id)
            ->where('estado', 'abierta')
            ->exists();

        if ($yaAbierta) {
            return response()->json(['message' => 'Esta caja ya tiene una sesión abierta.'], 422);
        }

        $empleadoYaTieneSesionAbierta = SesionCaja::where('usuario_id', $empleado->id)
            ->where('estado', 'abierta')
            ->exists();

        if ($empleadoYaTieneSesionAbierta) {
            return response()->json(['message' => 'Este empleado ya tiene una sesión de caja abierta en otra caja.'], 422);
        }

        $montoInicial = $request->validate([
            'monto_inicial' => 'required|numeric|min:0',
        ])['monto_inicial'];

        $sesion = SesionCaja::create([
            'caja_id' => $caja->id,
            'usuario_id' => $empleado->id,
            'monto_inicial' => $montoInicial,
            'estado' => 'abierta',
            'abierta_en' => now(),
        ]);

        return response()->json($sesion->load('usuario'), 201);
    }

    public function cerrar(Request $request, $id)
    {
        $empleado = $this->verificarEmpleadoPin($request);
        $sesion = SesionCaja::findOrFail($id);

        if (! $sesion->isAbierta()) {
            return response()->json(['message' => 'Esta sesión ya está cerrada.'], 422);
        }

        $esElMismo = $sesion->usuario_id === $empleado->id;
        $esAdmin = $empleado->role === User::ROLE_ADMIN_EMPRESA
            || $empleado->role === User::ROLE_ADMIN_SUCURSAL;

        if (! $esElMismo && ! $esAdmin) {
            return response()->json(['message' => 'Solo el cajero que abrió esta sesión, o un administrador, puede cerrarla.'], 403);
        }

        $montoContado = $request->validate([
            'monto_final_contado' => 'required|numeric|min:0',
        ])['monto_final_contado'];

        $montoEsperado = $sesion->monto_inicial; // TODO Fase 5: + ventas en efectivo

        $sesion->update([
            'monto_final_esperado' => $montoEsperado,
            'monto_final_contado' => $montoContado,
            'diferencia' => $montoContado - $montoEsperado,
            'estado' => 'cerrada',
            'cerrada_por_id' => $empleado->id,
            'cerrada_en' => now(),
        ]);

        return response()->json($sesion->load(['usuario', 'cerradaPor']));
    }

    public function corteX(Request $request, $id)
    {
        $empleado = $this->verificarEmpleadoPin($request);
        $sesion = SesionCaja::with('usuario')->findOrFail($id);

        if (! $sesion->isAbierta()) {
            return response()->json(['message' => 'Esta sesión no está abierta.'], 422);
        }

        $esElMismo = $sesion->usuario_id === $empleado->id;
        $esAdmin = $empleado->role === User::ROLE_ADMIN_EMPRESA
            || $empleado->role === User::ROLE_ADMIN_SUCURSAL;

        if (! $esElMismo && ! $esAdmin) {
            return response()->json(['message' => 'Solo el cajero que abrió esta sesión, o un administrador, puede ver este corte.'], 403);
        }

        $montoEsperado = $sesion->monto_inicial; // TODO Fase 5

        return response()->json([
            'sesion' => $sesion,
            'monto_esperado' => $montoEsperado,
            'tipo' => 'X',
        ]);
    }
}
