<?php

namespace App\Http\Requests\Ventas;

use Illuminate\Foundation\Http\FormRequest;

class VentaStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'venta_total' => ['nullable', 'numeric'],
            'nombre_venta' => ['nullable', 'string', 'max:255'],
            'cliente_id' => ['nullable', 'required_if:tipo_compra,credito', 'exists:clientes,id'],
            'tipo_compra' => ['nullable', 'in:contado,credito'],
            'status_venta' => ['required', 'in:activa,finalizada'],
            // venta registrada con retraso (solo administradores); no puede ser futura
            'fecha' => ['nullable', 'date', 'before_or_equal:now'],
        ];
    }
}
