<?php

namespace App\Http\Requests\TipoMovimiento;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TipoMovimientoUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', Rule::unique('tipo_movimientos', 'nombre')->ignore($this->route('tipoMovimiento')), 'string', 'max:255'],
        ];
    }
}
