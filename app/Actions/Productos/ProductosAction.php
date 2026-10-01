<?php

namespace App\Actions\Productos;

use App\Models\Producto;

class ProductosAction
{
    public function updateStock(Producto $producto, int|float $quantity, string $action = '+'): void
    {
        $increase = $action === '+';
        $producto->update(['stock' => round($producto->stock + ($increase ? $quantity : -$quantity), 2)]);
    }
}
