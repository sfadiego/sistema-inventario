<?php

namespace App\Http\Controllers;

use App\Actions\Devoluciones\DevolucionAction;
use App\Core\Data\IndexData;
use App\Enums\StatusDevolucionEnum;
use App\Http\Requests\Devoluciones\DevolucionesStoreRequest;
use App\Http\Requests\Devoluciones\DevolucionesUpdateRequest;
use App\Logic\Devoluciones\DevolucionesIndexLogic;
use App\Models\Devoluciones;
use App\Models\Venta;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;

// TODO: hacer pruebas
class DevolucionesController extends Controller
{
    public function index(IndexData $data, DevolucionesIndexLogic $logic): JsonResponse
    {
        return $logic->run($data);
    }

    public function showByVenta(Venta $venta): JsonResponse
    {
        $venta = $venta->load([
            'devoluciones',
            'devolucion.detalle.producto',
            'cliente',
        ]);
        $venta->devolucion_id = $venta->devolucion?->id;

        return Response::success($venta);
    }

    public function show(Devoluciones $devolucion): JsonResponse
    {
        $devolucion = $devolucion->load([
            'venta',
            'detalle.producto' => function ($q) {
                $q->select('id', 'nombre', 'unidad');
            },
        ]);

        return Response::success($devolucion);
    }

    public function store(DevolucionesStoreRequest $params, DevolucionAction $action): JsonResponse
    {
        try {
            $venta = $action->validateSale($params->venta_id, $params->productos);
            if (! $venta) {
                return Response::error('No es una devolucion válida');
            }

            $devolucion = Devoluciones::create([
                'motivo' => $params->motivo,
                'venta_id' => $params->venta_id,
                'status' => StatusDevolucionEnum::CREADA->value,
            ]);

            $action->processProductsAndUpdateTotal($devolucion, $params->productos);

            return Response::success($devolucion);
        } catch (\Throwable $th) {
            return Response::error($th->getMessage());
        }
    }

    public function update(DevolucionesUpdateRequest $params, Devoluciones $devolucion, DevolucionAction $action)
    {
        try {
            $action->procesarCancelarDevolucion($devolucion, $params->productos);

            return Response::success($devolucion);
        } catch (\Throwable $th) {
            return Response::error($th->getMessage());
        }
    }
}
