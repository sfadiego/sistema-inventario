<?php

namespace App\Http\Requests\Devoluciones;

use Illuminate\Foundation\Http\FormRequest;

class DevolucionesStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'venta_id' => 'required|exists:venta,id',
            'motivo' => 'required|string',
            'productos' => 'required|array|min:1',
            'productos.*.producto_id' => 'required|exists:productos,id',
            'productos.*.cantidad' => 'required|numeric|gt:0',
            'productos.*.precio_unitario' => 'required|numeric|min:0',
        ];
    }
}
