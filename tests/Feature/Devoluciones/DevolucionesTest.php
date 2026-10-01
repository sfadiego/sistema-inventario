<?php

namespace Tests\Feature\Devoluciones;

use App\Enums\StatusDevolucionEnum;
use App\Enums\StatusVentaEnum;
use App\Enums\TipoMovimientoEnum;
use App\Models\Devoluciones;
use App\Models\Producto;
use App\Models\Venta;
use App\Models\VentaProducto;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DevolucionesTest extends TestCase
{
    /**
     * Venta finalizada con un producto: 5 piezas vendidas a $10 (total $50), stock restante 10.
     *
     * @return array{0: Venta, 1: Producto}
     */
    private function ventaConProducto(int $cantidad = 5, float $precio = 10, int $stock = 10): array
    {
        $producto = Producto::factory()->create(['stock' => $stock]);
        $venta = Venta::factory()->create([
            'status_venta' => StatusVentaEnum::Finalizada->value,
            'venta_total' => $cantidad * $precio,
        ]);
        VentaProducto::factory()->create([
            'venta_id' => $venta->id,
            'producto_id' => $producto->id,
            'cantidad' => $cantidad,
            'precio' => $precio,
        ]);

        return [$venta, $producto];
    }

    private function payload(Venta $venta, Producto $producto, int|float $cantidad, float $precio = 10): array
    {
        return [
            'venta_id' => $venta->id,
            'motivo' => 'Producto defectuoso',
            'productos' => [[
                'producto_id' => $producto->id,
                'cantidad' => $cantidad,
                'precio_unitario' => $precio,
            ]],
        ];
    }

    public function test_store_devolucion_parcial(): void
    {
        $this->loginAdmin();
        [$venta, $producto] = $this->ventaConProducto();

        $response = $this->postJson('/api/devoluciones', $this->payload($venta, $producto, 2));

        $response->assertStatus(200);
        $devolucion = Devoluciones::findOrFail($response->json('data.id'));
        $this->assertEquals(StatusDevolucionEnum::CREADA->value, $devolucion->status);
        $this->assertEquals(20, $devolucion->total_reembolsado);

        $this->assertDatabaseHas('detalle_devolucion', [
            'devolucion_id' => $devolucion->id,
            'producto_id' => $producto->id,
            'cantidad' => 2,
        ]);
        // el producto regresa al inventario
        $this->assertEquals(12, $producto->fresh()->stock);
        // la venta queda con lo que no se devolvió
        $this->assertEquals(3, $venta->ventaProductos()->first()->cantidad);
        $this->assertEquals(30, $venta->fresh()->venta_total);
        $this->assertDatabaseHas('reporte_movimientos', [
            'producto_id' => $producto->id,
            'tipo_movimiento_id' => TipoMovimientoEnum::DEVOLUCION->value,
            'cantidad' => 2,
            'cantidad_anterior' => 10,
            'cantidad_actual' => 12,
        ]);
    }

    public function test_store_devolucion_total_elimina_producto_de_la_venta(): void
    {
        $this->loginAdmin();
        [$venta, $producto] = $this->ventaConProducto();

        $this->postJson('/api/devoluciones', $this->payload($venta, $producto, 5))->assertStatus(200);

        $this->assertEquals(0, $venta->ventaProductos()->count());
        $this->assertEquals(0, $venta->fresh()->venta_total);
        $this->assertEquals(15, $producto->fresh()->stock);
    }

    public function test_store_devolucion_con_cantidad_mayor_a_la_vendida(): void
    {
        $this->loginAdmin();
        [$venta, $producto] = $this->ventaConProducto();

        $response = $this->postJson('/api/devoluciones', $this->payload($venta, $producto, 6));

        $response->assertStatus(422);
        $this->assertEquals(10, $producto->fresh()->stock);
        $this->assertEquals(5, $venta->ventaProductos()->first()->cantidad);
        $this->assertDatabaseMissing('devoluciones', ['venta_id' => $venta->id]);
    }

    public function test_store_devolucion_con_otra_activa_en_la_misma_venta(): void
    {
        $this->loginAdmin();
        [$venta, $producto] = $this->ventaConProducto();
        $this->postJson('/api/devoluciones', $this->payload($venta, $producto, 2))->assertStatus(200);

        $response = $this->postJson('/api/devoluciones', $this->payload($venta, $producto, 1));

        $response->assertStatus(422);
        $this->assertStringContainsString('activa', $response->json('message'));
        $this->assertEquals(1, Devoluciones::where('venta_id', $venta->id)->count());
        $this->assertEquals(12, $producto->fresh()->stock);
    }

    public function test_store_devolucion_despues_de_cancelar_la_anterior(): void
    {
        $this->loginAdmin();
        [$venta, $producto] = $this->ventaConProducto();
        $payload = $this->payload($venta, $producto, 2);
        $primera = $this->postJson('/api/devoluciones', $payload)->json('data.id');
        $this->putJson("/api/devoluciones/{$primera}", $payload)->assertStatus(200);

        $this->postJson('/api/devoluciones', $payload)->assertStatus(200);

        $this->assertEquals(2, Devoluciones::where('venta_id', $venta->id)->count());
        $this->assertEquals(12, $producto->fresh()->stock);
    }

    public function test_store_devolucion_con_producto_repetido_suma_las_cantidades(): void
    {
        $this->loginAdmin();
        [$venta, $producto] = $this->ventaConProducto();
        $payload = $this->payload($venta, $producto, 3);
        $payload['productos'][] = $payload['productos'][0];

        $response = $this->postJson('/api/devoluciones', $payload);

        $response->assertStatus(422);
        $this->assertEquals(10, $producto->fresh()->stock);
        $this->assertDatabaseMissing('devoluciones', ['venta_id' => $venta->id]);
    }

    public function test_store_devolucion_con_varios_productos_no_procesa_ninguno_si_uno_excede(): void
    {
        $this->loginAdmin();
        [$venta, $primero] = $this->ventaConProducto();
        $segundo = Producto::factory()->create(['stock' => 10]);
        VentaProducto::factory()->create([
            'venta_id' => $venta->id,
            'producto_id' => $segundo->id,
            'cantidad' => 1,
            'precio' => 10,
        ]);
        $payload = $this->payload($venta, $primero, 2);
        $payload['productos'][] = ['producto_id' => $segundo->id, 'cantidad' => 2, 'precio_unitario' => 10];

        $response = $this->postJson('/api/devoluciones', $payload);

        $response->assertStatus(422);
        $this->assertEquals(10, $primero->fresh()->stock);
        $this->assertEquals(10, $segundo->fresh()->stock);
        $this->assertDatabaseMissing('devoluciones', ['venta_id' => $venta->id]);
    }

    public function test_store_devolucion_con_un_producto_ajeno_a_la_venta_en_la_lista(): void
    {
        $this->loginAdmin();
        [$venta, $producto] = $this->ventaConProducto();
        $ajeno = Producto::factory()->create(['stock' => 10]);
        $payload = $this->payload($venta, $producto, 1);
        $payload['productos'][] = ['producto_id' => $ajeno->id, 'cantidad' => 1, 'precio_unitario' => 10];

        $this->postJson('/api/devoluciones', $payload)->assertStatus(422);

        $this->assertEquals(10, $producto->fresh()->stock);
        $this->assertDatabaseMissing('devoluciones', ['venta_id' => $venta->id]);
    }

    public function test_store_devolucion_de_producto_en_metros_acepta_decimales(): void
    {
        $this->loginAdmin();
        $producto = Producto::factory()->create(['stock' => 10, 'unidad' => 'metro']);
        $venta = Venta::factory()->create(['status_venta' => StatusVentaEnum::Finalizada->value]);
        VentaProducto::factory()->create([
            'venta_id' => $venta->id,
            'producto_id' => $producto->id,
            'cantidad' => 2.5,
            'precio' => 10,
        ]);

        $this->postJson('/api/devoluciones', $this->payload($venta, $producto, 1.5))->assertStatus(200);

        $this->assertEquals(11.5, (float) $producto->fresh()->stock);
        $this->assertEquals(1.0, (float) $venta->ventaProductos()->first()->cantidad);

        // quedan 1.0 m: pedir 1.01 excede
        $this->postJson('/api/devoluciones', $this->payload($venta, $producto, 1.01))->assertStatus(422);
        $this->assertEquals(11.5, (float) $producto->fresh()->stock);
    }

    public function test_store_devolucion_respeta_el_maximo_de_devoluciones_por_venta(): void
    {
        $this->loginAdmin();
        [$venta, $producto] = $this->ventaConProducto();
        $payload = $this->payload($venta, $producto, 1);
        // dos devoluciones creadas y canceladas agotan el máximo
        foreach (range(1, Devoluciones::MAX_POR_VENTA) as $i) {
            $id = $this->postJson('/api/devoluciones', $payload)->assertStatus(200)->json('data.id');
            $this->putJson("/api/devoluciones/{$id}", $payload)->assertStatus(200);
        }

        $response = $this->postJson('/api/devoluciones', $payload);

        $response->assertStatus(422);
        $this->assertStringContainsString('máximo', $response->json('message'));
        $this->assertEquals(Devoluciones::MAX_POR_VENTA, Devoluciones::where('venta_id', $venta->id)->count());
        $this->assertEquals(10, $producto->fresh()->stock);
    }

    public function test_las_devoluciones_canceladas_cuentan_para_el_maximo(): void
    {
        $this->loginAdmin();
        [$venta, $producto] = $this->ventaConProducto();
        $payload = $this->payload($venta, $producto, 1);
        $primera = $this->postJson('/api/devoluciones', $payload)->json('data.id');
        $this->putJson("/api/devoluciones/{$primera}", $payload)->assertStatus(200);
        $this->postJson('/api/devoluciones', $payload)->assertStatus(200);

        $this->postJson('/api/devoluciones', $payload)->assertStatus(422);
        $this->assertEquals(2, Devoluciones::where('venta_id', $venta->id)->count());
    }

    public function test_las_reglas_de_devolucion_son_por_venta(): void
    {
        $this->loginAdmin();
        [$ventaA, $productoA] = $this->ventaConProducto();
        [$ventaB, $productoB] = $this->ventaConProducto();
        $this->postJson('/api/devoluciones', $this->payload($ventaA, $productoA, 1))->assertStatus(200);

        // la devolución activa de la venta A no bloquea a la venta B
        $this->postJson('/api/devoluciones', $this->payload($ventaB, $productoB, 1))->assertStatus(200);
    }

    public function test_store_devolucion_rechaza_cantidades_cero_o_negativas(): void
    {
        $this->loginAdmin();
        [$venta, $producto] = $this->ventaConProducto();

        foreach ([0, -2] as $cantidad) {
            try {
                $this->postJson('/api/devoluciones', $this->payload($venta, $producto, $cantidad));
                $this->fail("Se aceptó la cantidad {$cantidad}");
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertEquals(10, $producto->fresh()->stock);
    }

    public function test_store_devolucion_de_producto_que_no_esta_en_la_venta(): void
    {
        $this->loginAdmin();
        [$venta] = $this->ventaConProducto();
        $otro = Producto::factory()->create(['stock' => 10]);

        $response = $this->postJson('/api/devoluciones', $this->payload($venta, $otro, 1));

        $response->assertStatus(422);
        $this->assertEquals(10, $otro->fresh()->stock);
        // una devolución inválida no deja ningún registro (el middleware de transacción no corre en tests)
        $this->assertDatabaseMissing('devoluciones', ['venta_id' => $venta->id]);
    }

    public function test_store_devolucion_valida_campos_requeridos(): void
    {
        $this->loginAdmin();

        $this->expectException(ValidationException::class);
        $this->postJson('/api/devoluciones', []);
    }

    public function test_show_devolucion(): void
    {
        $this->loginAdmin();
        [$venta, $producto] = $this->ventaConProducto();
        $id = $this->postJson('/api/devoluciones', $this->payload($venta, $producto, 1))->json('data.id');

        $response = $this->getJson("/api/devoluciones/{$id}");

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => ['id', 'motivo', 'status', 'venta', 'detalle' => [['cantidad', 'producto']]]]);
    }

    public function test_show_devolucion_inexistente(): void
    {
        $this->loginAdmin();

        $this->getJson('/api/devoluciones/999999')->assertStatus(422);
    }

    public function test_show_devolucion_por_venta(): void
    {
        $this->loginAdmin();
        [$venta, $producto] = $this->ventaConProducto();
        $id = $this->postJson('/api/devoluciones', $this->payload($venta, $producto, 1))->json('data.id');

        $response = $this->getJson("/api/devoluciones/by-venta/{$venta->id}");

        $response->assertStatus(200);
        $this->assertEquals($venta->id, $response->json('data.id'));
        $this->assertNotNull($response->json('data.devolucion_id'));
        $this->assertContains($id, collect($response->json('data.devoluciones'))->pluck('id')->all());
    }

    public function test_index_devoluciones(): void
    {
        $this->loginAdmin();
        [$venta, $producto] = $this->ventaConProducto();
        $this->postJson('/api/devoluciones', $this->payload($venta, $producto, 1))->assertStatus(200);

        $response = $this->getJson('/api/devoluciones');

        $response->assertStatus(206);
        $response->assertJsonStructure(['current_page', 'data']);
    }

    public function test_cancelar_devolucion_restaura_venta_y_stock(): void
    {
        $this->loginAdmin();
        [$venta, $producto] = $this->ventaConProducto();
        $payload = $this->payload($venta, $producto, 2);
        $id = $this->postJson('/api/devoluciones', $payload)->json('data.id');
        $this->assertEquals(12, $producto->fresh()->stock);

        $response = $this->putJson("/api/devoluciones/{$id}", $payload);

        $response->assertStatus(200);
        $this->assertEquals(StatusDevolucionEnum::CANCELADA->value, Devoluciones::find($id)->status);
        $this->assertEquals(10, $producto->fresh()->stock);
        $this->assertEquals(5, $venta->ventaProductos()->first()->cantidad);
        $this->assertEquals(50, $venta->fresh()->venta_total);
        $this->assertDatabaseHas('reporte_movimientos', [
            'producto_id' => $producto->id,
            'tipo_movimiento_id' => TipoMovimientoEnum::CANCELANDO_DEVOLUCION->value,
        ]);
    }

    public function test_cancelar_devolucion_con_stock_insuficiente(): void
    {
        $this->loginAdmin();
        [$venta, $producto] = $this->ventaConProducto();
        $payload = $this->payload($venta, $producto, 2);
        $id = $this->postJson('/api/devoluciones', $payload)->json('data.id');
        // el producto ya se vendió de nuevo y no alcanza para revertir la devolución
        $producto->update(['stock' => 1]);

        $response = $this->putJson("/api/devoluciones/{$id}", $payload);

        $response->assertStatus(422);
        $this->assertEquals(StatusDevolucionEnum::CREADA->value, Devoluciones::find($id)->status);
        $this->assertEquals(1, $producto->fresh()->stock);
    }
}
