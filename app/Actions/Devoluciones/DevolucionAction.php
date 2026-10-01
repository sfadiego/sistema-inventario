<?php

namespace App\Actions\Devoluciones;

use App\Actions\Productos\ProductosAction;
use App\Actions\Ventas\VentasAction;
use App\Enums\StatusDevolucionEnum;
use App\Enums\TipoMovimientoEnum;
use App\Models\Devoluciones;
use App\Models\Producto;
use App\Models\ReporteMovimiento;
use App\Models\Venta;
use Illuminate\Support\Facades\Log;

class DevolucionAction
{
    protected $productosAction;

    protected $ventasAction;

    public function __construct(ProductosAction $productosAction, VentasAction $ventasAction)
    {
        $this->productosAction = $productosAction;
        $this->ventasAction = $ventasAction;
    }

    /**
     * Valida que cada producto esté en la venta y que, sumando las líneas repetidas,
     * no se devuelva más de lo que queda registrado en la venta.
     *
     * @return Venta|null null si algún producto no pertenece a la venta
     *
     * @throws \Exception si la venta ya alcanzó el máximo de devoluciones, tiene una activa o se pide devolver más de lo vendido
     */
    public function validateSale(int $ventaId, array $productos): ?Venta
    {
        if (Devoluciones::where('venta_id', $ventaId)->count() >= Devoluciones::MAX_POR_VENTA) {
            throw new \Exception('La venta ya alcanzó el máximo de '.Devoluciones::MAX_POR_VENTA.' devoluciones');
        }

        $hayDevolucionActiva = Devoluciones::where('venta_id', $ventaId)
            ->where('status', '!=', StatusDevolucionEnum::CANCELADA->value)
            ->exists();
        if ($hayDevolucionActiva) {
            throw new \Exception('La venta ya tiene una devolución activa, cancélala antes de generar otra');
        }

        $solicitado = collect($productos)
            ->groupBy('producto_id')
            ->map(fn ($lineas) => $lineas->sum('cantidad'));

        $venta = Venta::where('id', $ventaId)
            ->with('ventaProductos', function ($q) use ($solicitado) {
                $q->whereIn('producto_id', $solicitado->keys());
            })
            ->first();

        foreach ($solicitado as $productoId => $cantidad) {
            $ventaProducto = $venta->ventaProductos->firstWhere('producto_id', $productoId);

            if (! $ventaProducto) {
                Log::info('devolucion: El producto no se encuentra en la venta', [
                    'venta_id' => $ventaId,
                    'producto_id' => $productoId,
                ]);

                return null;
            }

            if (round($cantidad, 2) > round($ventaProducto->cantidad, 2)) {
                throw new \Exception('La cantidad a devolver es mayor que la cantidad vendida');
            }
        }

        return $venta;
    }

    public function processProductsAndUpdateTotal(Devoluciones $devolucion, array $productos): void
    {
        $total = 0;
        $venta = Venta::find($devolucion->venta_id);

        foreach ($productos as $item) {
            $productoId = $item['producto_id'];
            $cantidad = $item['cantidad'];

            $this->ventasAction->updateVentaProductoDevolucion(
                $venta->ventaProductos()->where('producto_id', $productoId)->first(),
                $cantidad
            );

            $devolucion->detalle()->create([
                'producto_id' => $productoId,
                'cantidad' => $cantidad,
                'precio_unitario' => $item['precio_unitario'],
            ]);

            $total += $cantidad * $item['precio_unitario'];

            $producto = Producto::find($productoId);
            $stockAnterior = $producto->stock;
            $nuevoStock = $producto->stock + $cantidad;
            $this->productosAction->updateStock($producto, $cantidad); // devuelve (agrega) al stock

            ReporteMovimiento::create([
                'producto_id' => $productoId,
                'tipo_movimiento_id' => TipoMovimientoEnum::DEVOLUCION->value,
                'motivo' => $devolucion->motivo,
                'cantidad' => $cantidad,
                'cantidad_anterior' => $stockAnterior,
                'cantidad_actual' => $nuevoStock,
                'user_id' => auth()->user()->id,
                'created_at' => now(),
            ]);
        }

        $this->ventasAction->updateVentaTotal($venta);
        $devolucion->update([
            'total_reembolsado' => $total,
        ]);
    }

    public function procesarCancelarDevolucion(Devoluciones $devolucion, array $productos): void
    {
        $venta = Venta::find($devolucion->venta_id);
        foreach ($productos as $item) {
            $producto = Producto::find($item['producto_id']);
            $nuevoStock = $producto->stock - $item['cantidad'];
            $cantidad = $item['cantidad'];

            if ($nuevoStock < 0) {
                throw new \Exception('El stock del producto '.$producto->nombre.' es insuficiente');
            }

            $ventaProducto = $venta->ventaProductos()
                ->where('producto_id', $item['producto_id'])
                ->first();

            if (! $ventaProducto) {
                $venta->ventaProductos()->create([
                    'producto_id' => $item['producto_id'],
                    'cantidad' => $item['cantidad'],
                    'precio' => $item['precio_unitario'],
                    'venta_id' => $venta->id,
                ]);
            } else {
                $ventaProducto->increment('cantidad', $item['cantidad']);
            }

            ReporteMovimiento::create([
                'producto_id' => $producto->id,
                'tipo_movimiento_id' => TipoMovimientoEnum::CANCELANDO_DEVOLUCION->value,
                'motivo' => "Cancelación devolución #{$devolucion->id}",
                'cantidad' => $cantidad,
                'cantidad_anterior' => $producto->stock,
                'cantidad_actual' => $nuevoStock,
                'user_id' => auth()->user()->id,
            ]);

            $this->productosAction->updateStock($producto, $cantidad, '-'); // restaura la venta (quita) al stock
        }

        $venta->unsetRelation('ventaProductos');
        $venta->update([
            'venta_total' => $venta->ventaTotal(),
        ]);

        $devolucion->update([
            'status' => StatusDevolucionEnum::CANCELADA->value,
        ]);
    }
}
