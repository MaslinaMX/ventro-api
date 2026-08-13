<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clienteId = $this->route('cliente');

        return [
            'nombre' => ['required', 'string', 'max:255'],
            'tipo' => ['required', Rule::in(['persona_fisica', 'persona_moral'])],

            'telefono' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],

            'direccion' => ['nullable', 'string', 'max:255'],
            'ciudad' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', 'string', 'max:100'],
            'codigo_postal' => ['nullable', 'string', 'max:10'],

            'rfc' => [
                'nullable',
                'string',
                'max:13',
                Rule::unique('clientes', 'rfc')->ignore($clienteId)->whereNull('deleted_at'),
            ],
            'razon_social' => ['nullable', 'string', 'max:255'],
            'regimen_fiscal' => ['nullable', 'string', 'max:3'],
            'uso_cfdi' => ['nullable', 'string', 'max:4'],

            'notas' => ['nullable', 'string'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del cliente es obligatorio.',
            'tipo.required' => 'Especifica si es persona física o moral.',
            'email.email' => 'El correo no tiene un formato válido.',
            'rfc.unique' => 'Ya existe un cliente registrado con este RFC.',
        ];
    }
}
