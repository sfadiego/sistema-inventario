<?php

namespace Tests\Feature\Ubicacion;

use App\Models\Ubicacion;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UbicacionTest extends TestCase
{
    public function test_index_ubicaciones(): void
    {
        $this->loginAdmin();
        foreach (range(1, 5) as $i) {
            Ubicacion::factory()->create(['nombre' => $this->faker->unique()->lexify('ubic-?????')]);
        }

        $response = $this->get('/api/ubicaciones');

        $response->assertStatus(206);
        $response->assertJsonStructure(['current_page', 'total', 'data' => ['*' => ['id', 'nombre']]]);
        $this->assertEquals(Ubicacion::count(), $response->json('total'));
    }

    public function test_show_ubicacion(): void
    {
        $this->loginAdmin();
        $ubicacion = Ubicacion::factory()->create(['nombre' => $this->faker->unique()->lexify('ubic-?????')]);

        $this->get("/api/ubicaciones/{$ubicacion->id}")
            ->assertStatus(200)
            ->assertJson(['data' => ['id' => $ubicacion->id, 'nombre' => $ubicacion->nombre]]);
    }

    public function test_store_ubicacion(): void
    {
        $this->loginAdmin();
        $nombre = $this->faker->unique()->lexify('ubicacion-????');

        $this->post('/api/ubicaciones', ['nombre' => $nombre])->assertStatus(200);

        $this->assertDatabaseHas('ubicaciones', ['nombre' => $nombre]);
    }

    public function test_store_ubicacion_duplicada(): void
    {
        $this->loginAdmin();
        $ubicacion = Ubicacion::factory()->create(['nombre' => $this->faker->unique()->lexify('ubic-?????')]);

        $this->expectException(ValidationException::class);
        $this->post('/api/ubicaciones', ['nombre' => $ubicacion->nombre]);
    }

    public function test_store_ubicacion_sin_nombre(): void
    {
        $this->loginAdmin();

        $this->expectException(ValidationException::class);
        $this->post('/api/ubicaciones', []);
    }

    public function test_update_ubicacion(): void
    {
        $this->loginAdmin();
        $ubicacion = Ubicacion::factory()->create(['nombre' => $this->faker->unique()->lexify('ubic-?????')]);
        $nombre = $this->faker->unique()->lexify('nueva-????');

        $this->put("/api/ubicaciones/{$ubicacion->id}", ['nombre' => $nombre])->assertStatus(200);

        $this->assertDatabaseHas('ubicaciones', ['id' => $ubicacion->id, 'nombre' => $nombre]);
    }

    public function test_delete_ubicacion(): void
    {
        $this->loginAdmin();
        $ubicacion = Ubicacion::factory()->create(['nombre' => $this->faker->unique()->lexify('ubic-?????')]);

        $this->delete("/api/ubicaciones/{$ubicacion->id}")->assertStatus(200);

        $this->assertDatabaseMissing('ubicaciones', ['id' => $ubicacion->id]);
    }

    public function test_update_ubicacion_conservando_su_nombre(): void
    {
        $this->loginAdmin();
        $registro = Ubicacion::factory()->create(['nombre' => $this->faker->unique()->lexify('ubic-?????')]);

        $this->put("/api/ubicaciones/{$registro->id}", ['nombre' => $registro->nombre])->assertStatus(200);
    }

    public function test_update_ubicacion_con_nombre_de_otro_registro(): void
    {
        $this->loginAdmin();
        $registro = Ubicacion::factory()->create(['nombre' => $this->faker->unique()->lexify('ubic-?????')]);
        $otro = Ubicacion::factory()->create(['nombre' => $this->faker->unique()->lexify('ubic-?????')]);

        $this->expectException(ValidationException::class);
        $this->put("/api/ubicaciones/{$registro->id}", ['nombre' => $otro->nombre]);
    }
}
