<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Mail\ResetPasswordMail;
use App\Mail\UserInvitedMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    // ─── GET /usuarios ────────────────────────────────────────────────────────
    public function index(): JsonResponse
    {
        $users = User::with('sucursal')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($u) => $this->formatUser($u));

        return response()->json($users);
    }

    // ─── POST /usuarios ───────────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users')],
            'phone' => ['nullable', 'string', 'max:20'],
            'employee_number' => ['nullable', 'string', Rule::unique('users')],
            'sucursal_id' => ['nullable', 'integer', 'exists:sucursales,id'],
            'role' => ['required', Rule::in(User::ROLES)],
            'is_seller' => ['boolean'],
            // ← sin password ni security_pin
        ]);

        $employeeNumber = $request->filled('employee_number')
            ? strtoupper($request->employee_number)
            : User::nextEmployeeNumber();

        $inviteToken = Str::random(64);

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'name' => $request->first_name.' '.$request->last_name,
            'email' => $request->email,
            'password' => null,
            'phone' => $request->phone,
            'employee_number' => $employeeNumber,
            'sucursal_id' => $request->sucursal_id,
            'role' => $request->role,
            'is_seller' => $request->boolean('is_seller'),
            'is_deletable' => true,
            'activo' => false, // inactivo hasta activar
            'security_pin' => '1234',
            'invite_token' => $inviteToken,
            'invited_at' => now(),
            'pin_changed' => false,
        ]);

        $activationUrl = config('app.frontend_url')
            .'/#/activar?token='.$inviteToken
            .'&tenant='.tenant('id');

        Mail::to($user->email)->send(new UserInvitedMail($user, $activationUrl));

        return response()->json($this->formatUser($user), 201);
    }

    // ─── GET /usuarios/{id} ───────────────────────────────────────────────────
    public function show(int $id): JsonResponse
    {
        $user = User::with('sucursal')->findOrFail($id);

        return response()->json($this->formatUser($user));
    }

    // ─── PATCH /usuarios/{id} ─────────────────────────────────────────────────
    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $request->validate([
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'employee_number' => ['nullable', 'string', Rule::unique('users')->ignore($user->id)],
            'sucursal_id' => ['nullable', 'integer', 'exists:sucursales,id'],
            'role' => ['sometimes', Rule::in(User::ROLES)],
            'is_seller' => ['boolean'],
            'security_pin' => ['sometimes', 'digits:4'],
            'password' => ['sometimes', 'string', 'min:8'],
        ]);

        $data = $request->only([
            'first_name', 'last_name', 'phone', 'employee_number',
            'sucursal_id', 'role', 'is_seller',
        ]);

        if ($request->filled('first_name') || $request->filled('last_name')) {
            $data['name'] = ($request->first_name ?? $user->first_name)
                .' '.($request->last_name ?? $user->last_name);
        }

        if ($request->filled('password')) {
            $data['password'] = bcrypt($request->password);
        }

        if ($request->filled('security_pin')) {
            $data['security_pin'] = $request->security_pin;
            $data['pin_updated_at'] = now();
            $data['pin_changed'] = true;
        }

        $user->update($data);

        return response()->json($this->formatUser($user->fresh('sucursal')));
    }

    // ─── DELETE /usuarios/{id} ────────────────────────────────────────────────
    public function destroy(int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        if (! $user->is_deletable) {
            return response()->json(['message' => 'Este usuario no se puede eliminar.'], 403);
        }

        $user->delete();

        return response()->json(['message' => 'Usuario eliminado correctamente.']);
    }

    // ─── PATCH /usuarios/{id}/toggle-activo ───────────────────────────────────
    public function toggleActivo(int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        if (! $user->is_deletable) {
            return response()->json(['message' => 'No puedes desactivar este usuario.'], 403);
        }

        $user->update(['activo' => ! $user->activo]);

        return response()->json($this->formatUser($user->fresh()));
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────
    private function formatUser(User $user): array
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
            'activo' => $user->activo,
            'sucursal_id' => $user->sucursal_id,
            'sucursal' => $user->sucursal?->nombre,
            'pin_updated_at' => $user->pin_updated_at,
            'pin_is_default' => ! $user->pin_changed,
        ];
    }

    // PATCH /usuarios/me/pin
    public function updatePin(Request $request): JsonResponse
    {
        $request->validate([
            'security_pin' => ['required', 'digits:4'],
        ]);

        $user = $request->user();
        $user->update([
            'security_pin' => $request->security_pin,
            'pin_updated_at' => now(),
            'pin_changed' => true,
        ]);

        return response()->json(['message' => 'PIN actualizado correctamente.']);
    }

    // PATCH /usuarios/me/password
    public function updatePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'La contraseña actual es incorrecta.',
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json(['message' => 'Contraseña actualizada correctamente.']);
    }

    public function enviarResetPassword(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'admin_password' => ['required', 'string'],
        ]);

        if (! Hash::check($request->admin_password, $request->user()->password)) {
            return response()->json([
                'message' => 'Contraseña incorrecta.',
            ], 422);
        }

        $user = User::findOrFail($id);

        $token = Str::random(64);

        Log::info('DB al guardar token: '.DB::connection()->getDatabaseName());

        DB::connection('tenant')->table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => $token,
                'created_at' => now(),
            ]
        );

        $url = config('app.frontend_url')
            .'/#/reset-password?token='.$token
            .'&email='.urlencode($user->email)
            .'&tenant='.tenant('id');

        Mail::to($user->email)
            ->send(new ResetPasswordMail($user, $url));

        return response()->json([
            'message' => "Correo de restablecimiento enviado a {$user->email}.",
        ]);
    }
}
