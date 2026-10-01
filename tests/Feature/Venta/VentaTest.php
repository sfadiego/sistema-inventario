<?php

namespace Tests\Feature\Venta;

use App\Enums\StatusVentaEnum;
use App\Enums\TipoCompraEnum;
use App\Enums\TipoMovimientoEnum;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\HistorialAdeudo;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\ReporteMovimiento;
use App\Models\Ubicacion;
use App\Models\Venta;
use App\Models\VentaProducto;
use Tests\TestCase;

class VentaTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_index_venta(): void
    {
        $this->loginAdmin();
        // crear venta
        Venta::factory()->count(10)->create();

        // fetch data
        $response = $this->get('/api/ventas');
        $response->assertStatus(206);
        $response->assertJsonStructure([
            'current_page',
            'data' => [
                '*' => [
                    'venta_total',
                    'nombre_venta',
                    'folio',
                    'cliente_id',
                    'tipo_compra',
                    'status_venta',
                ],
            ],
        ]);

        $this->assertDatabaseCount('venta', 10);
        Venta::all()->each(function ($venta) {
            $this->assertDatabaseHas('venta', [
                'id' => $venta->id,
                'folio' => $venta->folio,
            ]);
        });
    }

    public function test_store_venta(): void
    {
        $this->loginAdmin();

        // crear venta
        $payload = [
            'venta_total' => 0,
            'folio' => Producto::createFolio($this->faker->word),
            'nombre_venta' => $this->faker->word,
            'cliente_id' => Cliente::factory()->create()->id,
            'tipo_compra' => TipoCompraEnum::Contado->value,
            'status_venta' => StatusVentaEnum::Activa->value,

        ];

        $response = $this->post('/api/ventas', $payload);
        $response->assertStatus(200);

        $response->assertJson([
            'status' => 'OK',
            'message' => null,
            'data' => [
                'venta_total' => $payload['venta_total'],
                'nombre_venta' => $payload['nombre_venta'],
                'cliente_id' => $payload['cliente_id'],
                'tipo_compra' => $payload['tipo_compra'],
                'status_venta' => $payload['status_venta'],
            ],
        ]);
        $response->json('data');
        $this->assertDatabaseHas('venta', [
            'id' => $response->json('data.id'),
            'folio' => $response->json('data.folio'),
        ]);
    }

    public function test_show_venta(): void
    {
        $this->loginAdmin();

        $venta = Venta::factory()->create();

        $response = $this->get("/api/ventas/{$venta->id}");
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'id',
                'venta_total',
                'nombre_venta',
                'folio',
                'cliente_id',
                'tipo_compra',
                'status_venta',
                'created_at',
                'updated_at',
                'cliente',
            ],
        ]);

        $this->assertDatabaseHas('venta', [
            'id' => $venta->id,

        ]);
    }

    public function test_finalizar_venta(): void
    {
        $this->loginAdmin();

        $venta = Venta::factory()->create(['status_venta' => StatusVentaEnum::Activa->value]);
        $producto = Producto::factory()->create([
            'nombre' => $this->faker->word,
            'proveedor_id' => Proveedor::factory()->create()->id,
            'categoria_id' => Categoria::first()->id,
            'codigo' => strtoupper($this->faker->unique()->bothify('????-#####')),
            'precio_compra' => $this->faker->randomFloat(2, 10, 100),
            'precio_venta' => $this->faker->randomFloat(2, 20, 200),
            'stock' => 10,
            'cantidad_minima' => $this->faker->numberBetween(1, 10),
            'compatibilidad' => $this->faker->text(50),
            'ubicacion_id' => Ubicacion::factory()->create()->id,
            'unidad' => $this->faker->randomElement(['pieza', 'metro', 'par']),
            'activo' => $this->faker->boolean,
        ]);

        VentaProducto::factory()->create([
            'cantidad' => 5,
            'precio' => $producto->precio_venta,
            'producto_id' => $producto->id,
            'venta_id' => $venta->id,
        ]);

        $response = $this->put("/api/ventas/{$venta->id}/finalizar-venta");
        $response->assertStatus(200);
        $data = $response->json('data');
        $response->assertJson([
            'status' => 'OK',
            'message' => null,
            'data' => [
                'id' => $venta['id'],
                'venta_total' => $venta->ventaTotal(),
                'nombre_venta' => $venta['nombre_venta'],
                'folio' => $venta['folio'],
                'cliente_id' => $venta['cliente_id'],
                'tipo_compra' => $venta['tipo_compra'],
                'status_venta' => StatusVentaEnum::Finalizada->value,
            ],
        ]);

        $this->assertEquals(number_format($data['venta_total'], 2, '.', ''), $venta->ventaTotal());
        $this->assertDatabaseHas('venta', [
            'id' => $venta->id,
            'status_venta' => StatusVentaEnum::Finalizada->value,
            'folio' => $venta->folio,
        ]);
    }

    /**
     * Venta activa con un producto: 3 piezas a $10 (total $30) y stock de $stock.
     *
     * @return array{0: Venta, 1: Producto}
     */
    private function ventaActivaConProducto(array $venta = [], int $stock = 10, int $cantidad = 3): array
    {
        $venta = Venta::factory()->create(array_merge([
            'status_venta' => StatusVentaEnum::Activa->value,
            'tipo_compra' => TipoCompraEnum::Contado->value,
        ], $venta));
        $producto = Producto::factory()->create(['stock' => $stock]);
        VentaProducto::factory()->create([
            'venta_id' => $venta->id,
            'producto_id' => $producto->id,
            'cantidad' => $cantidad,
            'precio' => 10,
        ]);

        return [$venta, $producto];
    }

    public function test_finalizar_venta_descuenta_stock_y_registra_movimiento(): void
    {
        $user = $this->loginAdmin();
        [$venta, $producto] = $this->ventaActivaConProducto();

        $this->put("/api/ventas/{$venta->id}/finalizar-venta")->assertStatus(200);

        $this->assertEquals(7, $producto->fresh()->stock);
        $this->assertEquals(30, $venta->fresh()->venta_total);
        $this->assertDatabaseHas('reporte_movimientos', [
            'producto_id' => $producto->id,
            'tipo_movimiento_id' => TipoMovimientoEnum::SALIDA->value,
            'cantidad' => 3,
            'cantidad_anterior' => 10,
            'cantidad_actual' => 7,
            'user_id' => $user->id,
        ]);
    }

    public function test_finalizar_venta_con_stock_insuficiente(): void
    {
        $this->loginAdmin();
        [$venta, $producto] = $this->ventaActivaConProducto(stock: 2, cantidad: 3);

        $response = $this->put("/api/ventas/{$venta->id}/finalizar-venta");

        $response->assertStatus(422);
        $this->assertEquals(2, $producto->fresh()->stock);
        $this->assertEquals(StatusVentaEnum::Activa->value, $venta->fresh()->status_venta);
    }

    public function test_finalizar_venta_a_credito_registra_adeudo_del_cliente(): void
    {
        $this->loginAdmin();
        $cliente = Cliente::factory()->create(['adeudo' => -100]);
        [$venta] = $this->ventaActivaConProducto([
            'cliente_id' => $cliente->id,
            'tipo_compra' => TipoCompraEnum::Credito->value,
        ]);

        $this->put("/api/ventas/{$venta->id}/finalizar-venta")->assertStatus(200);

        $this->assertEquals(-130, $cliente->fresh()->adeudo);
        $this->assertDatabaseHas('historial_adeudo_cliente', [
            'cliente_id' => $cliente->id,
            'venta_id' => $venta->id,
            'total_adeudo' => -30,
        ]);
    }

    public function test_finalizar_venta_de_contado_no_modifica_adeudo(): void
    {
        $this->loginAdmin();
        $cliente = Cliente::factory()->create(['adeudo' => -100]);
        [$venta] = $this->ventaActivaConProducto(['cliente_id' => $cliente->id]);

        $this->put("/api/ventas/{$venta->id}/finalizar-venta")->assertStatus(200);

        $this->assertEquals(-100, $cliente->fresh()->adeudo);
        $this->assertEquals(0, HistorialAdeudo::where('venta_id', $venta->id)->count());
    }

    public function test_finalizar_venta_dos_veces_no_duplica_stock_ni_adeudo(): void
    {
        $this->loginAdmin();
        $cliente = Cliente::factory()->create(['adeudo' => 0]);
        [$venta, $producto] = $this->ventaActivaConProducto([
            'cliente_id' => $cliente->id,
            'tipo_compra' => TipoCompraEnum::Credito->value,
        ]);

        $this->put("/api/ventas/{$venta->id}/finalizar-venta")->assertStatus(200);
        $segunda = $this->put("/api/ventas/{$venta->id}/finalizar-venta");

        $segunda->assertStatus(422);
        $this->assertEquals(7, $producto->fresh()->stock);
        $this->assertEquals(-30, $cliente->fresh()->adeudo);
        $this->assertEquals(1, HistorialAdeudo::where('venta_id', $venta->id)->count());
        $this->assertEquals(1, ReporteMovimiento::where('producto_id', $producto->id)->count());
    }

    public function test_duplicate_folio(): void
    {
        $this->loginAdmin();
        $ventas = Venta::factory()->count(200)->create();
        $folios = $ventas->pluck('folio')->toArray();
        $duplicates = array_diff($folios, array_unique($folios));
        $this->assertTrue(empty($duplicates));
    }
}
