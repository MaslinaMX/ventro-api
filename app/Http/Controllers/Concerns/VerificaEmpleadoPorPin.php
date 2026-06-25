<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

trait VerificaEmpleadoPorPin
{
    protected function verificarEmpleadoPin(Request $request): User
    {
        $data = $request->validate([
            'employee_number' => 'required|string',
            'pin' => 'required|string',
        ]);

        $empleado = User::where('employee_number', $data['employee_number'])->first();

        if (! $empleado || ! Hash::check($data['pin'], $empleado->security_pin)) {
            abort(422, 'Número de empleado o PIN incorrecto.');
        }

        return $empleado;
    }
}
