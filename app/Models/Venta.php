<?php

namespace App\Models;

use App\Enums\StatusDevolucionEnum;
use App\Enums\StatusVentaEnum;
use App\Enums\TipoCompraEnum;
use App\Enums\TipoMovimientoEnum;
use App\Traits\Movimientos;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class Venta extends Model
{
    use HasFactory, Movimientos, SoftDeletes;

    protected $table = 'venta';

    protected $fillable = [
        'venta_total',
        'nombre_venta',
        'folio',
        'cliente_id',
        'tipo_compra',
        'status_venta',
    ];

    protected $casts = [
        'venta_total' => 'decimal:2',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function devoluciones(): HasMany
    {
        return $this->hasMany(Devoluciones::class, 'venta_id', 'id');
    }

    public function devolucion()
    {
        return $this->hasOne(Devoluciones::class, 'venta_id')
            ->where('status', '!=', StatusDevolucionEnum::CANCELADA->value);
    }

    public function ventaProductos(): HasMany
    {
        return $this->hasMany(VentaProducto::class);
    }

    public static function createVenta(array $data): Venta
    {
        $venta = new self([
            'venta_total' => $data['venta_total'] ?? 0,
            'nombre_venta' => $data['nombre_venta'] ?? '',
            'folio' => self::createFolio(),
            'cliente_id' => $data['cliente_id'] ?? null,
            'tipo_compra' => $data['tipo_compra'] ?? TipoCompraEnum::Contado->value,
            'status_venta' => StatusVentaEnum::Activa->value,
        ]);

        // Venta registrada con retraso: created_at es la fecha en que ocurrió la venta
        if (! empty($data['fecha'])) {
            $fecha = Carbon::parse($data['fecha']);
            $venta->created_at = $fecha;
            $venta->updated_at = $fecha;
        }

        $venta->save();

        return $venta;
    }

    public function finalizarVenta(): Venta
    {
        if ($this->status_venta === StatusVentaEnum::Finalizada->value) {
            throw new \Exception('La venta ya está finalizada');
        }

        DB::beginTransaction();
        collect($this->ventaProductos)
            ->groupBy('producto_id')
            ->map(function ($items, $productoId) {
                return [
                    'producto_id' => $productoId,
                    'cantidad_total' => $items->sum('cantidad'),
                ];
            })
            ->map(function ($item) {
                $cantidadDescontar = $item['cantidad_total'];
                $producto = Producto::find($item['producto_id']);
                $stockOriginal = $producto->stock;
                $stockActual = $producto->stock - $cantidadDescontar;
                if ($stockActual < 0) {
                    DB::rollBack();
                    throw new \Exception('No se puede descontar, stock insuficiente');
                }
                $producto->stock = $stockActual;
                $producto->update();
                $this->nuevoMovimiento([
                    'producto_id' => $producto->id,
                    'tipo_movimiento_id' => TipoMovimientoEnum::SALIDA->value,
                    'motivo' => $this->motivoSalida(),
                    'cantidad' => $cantidadDescontar,
                    'cantidad_anterior' => $stockOriginal,
                    'cantidad_actual' => $stockActual,
                    'user_id' => auth()->user()->id,
                ]);
            });

        $ventaTotal = $this->ventaTotal();

        if ($this->tipo_compra === TipoCompraEnum::Credito->value && $this->cliente_id) {
            $cliente = Cliente::find($this->cliente_id);
            $adeudoActual = $cliente->adeudo;
            $adeudoTotal = $adeudoActual + (-$ventaTotal);
            $cliente->adeudo = $adeudoTotal;
            $cliente->update();

            $adeudo = new HistorialAdeudo([
                'cliente_id' => $this->cliente_id,
                'venta_id' => $this->id,
                'total_adeudo' => (-$ventaTotal),
            ]);
            // el adeudo queda con la fecha de la venta (created_at no es asignable en masa)
            $adeudo->created_at = $this->created_at;
            $adeudo->save();
        }

        $this->update([
            'venta_total' => number_format($ventaTotal, 2, '.', ''),
            'status_venta' => StatusVentaEnum::Finalizada->value,
            'updated_at' => now(),
        ]);
        DB::commit();

        return $this->refresh();
    }

    /**
     * El movimiento de inventario se registra con la fecha real del cambio de stock;
     * si la venta es de otro día, el motivo conserva la fecha en que ocurrió.
     */
    private function motivoSalida(): string
    {
        if ($this->created_at->isSameDay(now())) {
            return 'Venta de producto';
        }

        return 'Venta de producto (venta del '.$this->created_at->format('d/m/Y').')';
    }

    public function scopeVentaTotal(): float
    {
        return (float) $this->ventaProductos()
            ->selectRaw('ROUND(SUM(cantidad * precio), 2) as total')
            ->value('total') ?? 0;
    }

    public static function createFolio(): string
    {
        return date('ymdHis').strtoupper(substr(uniqid('', true), 0, 10));
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where('nombre_venta', 'like', "%$search%")
            ->orWhere('folio', $search);
    }

    public static function reporteVentas($fechaInicio = null, $fechaFin = null, $orderDate = 'desc'): Collection
    {
        $fechaInicio = $fechaInicio ?? now()->startOfYear();
        $fechaFin = $fechaFin ?? now();

        return Venta::where('status_venta', StatusVentaEnum::Finalizada)
            ->when($fechaInicio && $fechaFin, function ($q) use ($fechaInicio, $fechaFin) {
                $q->whereBetween('created_at', [$fechaInicio, $fechaFin]);
            })
            ->when($fechaInicio && ! $fechaFin, function ($q) use ($fechaInicio) {
                $q->where('created_at', '>=', $fechaInicio);
            })
            ->when(! $fechaInicio && $fechaFin, function ($q) use ($fechaFin) {
                $q->where('created_at', '<=', $fechaFin);
            })
            ->orderBy('created_at', $orderDate)
            ->get();
    }
}
