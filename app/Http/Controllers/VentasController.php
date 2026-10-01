<?php

namespace App\Http\Controllers;

use App\Core\Data\IndexData;
use App\Enums\TipoCompraEnum;
use App\Http\Requests\Ventas\ReporteVentaRequest;
use App\Http\Requests\Ventas\VentaStoreRequest;
use App\Http\Requests\Ventas\VentaUpdateRequest;
use App\Logic\VentaProductos\ProductosByVentaLogic;
use App\Logic\Ventas\VentasIndexLogic;
use App\Models\Venta;
use App\Traits\Movimientos;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Response;

class VentasController extends Controller
{
    use Movimientos;

    public function index(IndexData $data, VentasIndexLogic $logic): JsonResponse
    {
        return $logic->run($data);
    }

    public function store(VentaStoreRequest $params): JsonResponse
    {
        if ($params->tipo_compra === TipoCompraEnum::Credito->value && empty($params->cliente_id)) {
            return Response::error('Cliente es requerido para ventas de crédito');
        }

        if ($params->filled('fecha') && ! Gate::forUser($params->user())->allows('admin')) {
            return Response::unauthorized();
        }

        $venta = Venta::createVenta($params->all());

        return Response::success($venta);
    }

    public function update(VentaUpdateRequest $params, Venta $venta): JsonResponse
    {
        $venta->update($params->validated());

        return Response::success($venta);
    }

    public function show(Venta $venta): JsonResponse
    {
        $venta->load(['cliente', 'devolucion', 'devoluciones']);

        return Response::success($venta);
    }

    public function delete(Venta $venta): JsonResponse
    {
        $venta->delete();

        return Response::success('', 'Venta eliminada');
    }

    public function productoVenta(IndexData $data, ProductosByVentaLogic $logic): JsonResponse
    {
        return $logic->run($data);
    }

    public function finalizarVenta(Venta $venta): JsonResponse
    {
        try {
            $venta->finalizarVenta();

            return Response::success($venta);
        } catch (\Throwable $th) {
            return Response::error($th->getMessage());
        }
    }

    public function countProductos(Venta $venta): JsonResponse
    {
        $venta = $venta->ventaProductos()->count();

        return Response::success(['total' => $venta]);
    }

    public function reporteVentas(ReporteVentaRequest $params): JsonResponse
    {
        $reporte = Venta::reporteVentas($params?->fecha_inicio, $params?->fecha_fin, $params?->order_date ?? 'desc');

        return Response::success($reporte);
    }
}
