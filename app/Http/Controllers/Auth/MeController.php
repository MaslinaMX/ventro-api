<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class MeController extends Controller
{
    // GET /api/auth/me
    public function show(Request $request)
    {
        return response()->json($this->formatUser($request->user()));
    }

    // PATCH /api/auth/me
    public function update(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'security_pin' => ['required', 'digits:4'],
            'employee_number' => ['nullable', 'string', 'max:20'],
        ]);

        // Employee number: usar el provisto o generar correlativo
        $employeeNumber = $request->filled('employee_number')
            ? strtoupper($request->employee_number)
            : User::nextEmployeeNumber();

        // Verificar que no esté en uso por otro usuario
        $exists = User::where('employee_number', $employeeNumber)
            ->where('id', '!=', $user->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Ese número de empleado ya está en uso.',
                'errors' => ['employee_number' => ['Ya existe un empleado con ese número.']],
            ], 422);
        }

        $user->update([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'name' => $request->first_name.' '.$request->last_name,
            'phone' => $request->phone,
            'security_pin' => $request->security_pin, // el cast 'hashed' NO aplica aquí — ver nota
            'pin_updated_at' => now(),
            'employee_number' => $employeeNumber,
        ]);

        return response()->json([
            'user' => $this->formatUser($user->fresh()),
            'onboarding_complete' => true,
        ]);
    }

    private function formatUser($user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'employee_number' => $user->employee_number,
            'role' => $user->role,
            'is_seller' => $user->is_seller,
            'is_deletable' => $user->is_deletable,
            'sucursal_id' => $user->sucursal_id,
            'pin_updated_at' => $user->pin_updated_at,
        ];
    }
}
