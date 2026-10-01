<?php

namespace Tests\Feature\TipoMovimiento;

use App\Enums\TipoMovimientoEnum;
use App\Models\TipoMovimiento;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TipoMovimientoTest extends TestCase
{
    public function test_index_tipo_movimientos(): void
    {
        $this->loginAdmin();

        $response = $this->get('/api/tipo-movimientos');

        $response->assertStatus(206);
        $response->assertJsonStructure(['current_page', 'total', 'data' => ['*' => ['id', 'nombre']]]);
        $this->assertEquals(TipoMovimiento::count(), $response->json('total'));
    }

    public function test_tipos_sembrados_coinciden_con_el_enum(): void
    {
        foreach (TipoMovimientoEnum::cases() as $case) {
            $this->assertDatabaseHas('tipo_movimientos', ['id' => $case->value, 'nombre' => $case->label()]);
        }
    }

    public function test_show_tipo_movimiento(): void
    {
        $this->loginAdmin();

        $this->get('/api/tipo-movimientos/'.TipoMovimientoEnum::ENTRADA->value)
            ->assertStatus(200)
            ->assertJson(['data' => ['id' => TipoMovimientoEnum::ENTRADA->value, 'nombre' => 'Entrada']]);
    }

    public function test_store_tipo_movimiento(): void
    {
        $this->loginAdmin();
        $nombre = $this->faker->unique()->lexify('tipo-????');

        $this->post('/api/tipo-movimientos', ['nombre' => $nombre])->assertStatus(200);

        $this->assertDatabaseHas('tipo_movimientos', ['nombre' => $nombre]);
    }

    public function test_store_tipo_movimiento_duplicado(): void
    {
        $this->loginAdmin();

        $this->expectException(ValidationException::class);
        $this->post('/api/tipo-movimientos', ['nombre' => 'Entrada']);
    }

    public function test_update_tipo_movimiento(): void
    {
        $this->loginAdmin();
        $tipo = TipoMovimiento::create(['nombre' => 'temporal-'.$this->faker->unique()->lexify('????')]);
        $nombre = $this->faker->unique()->lexify('editado-????');

        $this->put("/api/tipo-movimientos/{$tipo->id}", ['nombre' => $nombre])->assertStatus(200);

        $this->assertDatabaseHas('tipo_movimientos', ['id' => $tipo->id, 'nombre' => $nombre]);
    }

    public function test_delete_tipo_movimiento(): void
    {
        $this->loginAdmin();
        $tipo = TipoMovimiento::create(['nombre' => 'borrar-'.$this->faker->unique()->lexify('????')]);

        $this->delete("/api/tipo-movimientos/{$tipo->id}")->assertStatus(200);

        $this->assertDatabaseMissing('tipo_movimientos', ['id' => $tipo->id]);
    }

    public function test_update_tipomovimiento_conservando_su_nombre(): void
    {
        $this->loginAdmin();
        $registro = TipoMovimiento::create(['nombre' => $this->faker->unique()->lexify('tipo-?????')]);

        $this->put("/api/tipo-movimientos/{$registro->id}", ['nombre' => $registro->nombre])->assertStatus(200);
    }

    public function test_update_tipomovimiento_con_nombre_de_otro_registro(): void
    {
        $this->loginAdmin();
        $registro = TipoMovimiento::create(['nombre' => $this->faker->unique()->lexify('tipo-?????')]);
        $otro = TipoMovimiento::create(['nombre' => $this->faker->unique()->lexify('tipo-?????')]);

        $this->expectException(ValidationException::class);
        $this->put("/api/tipo-movimientos/{$registro->id}", ['nombre' => $otro->nombre]);
    }
}
