<?php

namespace Tests\Feature\Categoria;

use App\Models\Categoria;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CategoriaTest extends TestCase
{
    public function test_index_categoria(): void
    {
        $this->loginAdmin();

        Categoria::factory()->createMany([
            [
                'nombre' => $this->faker->unique()->name,
                'activa' => true,
            ],
            [
                'nombre' => $this->faker->unique()->name,
                'activa' => true,
            ],
            [
                'nombre' => $this->faker->unique()->name,
                'activa' => true,
            ],
        ]);

        $response = $this->getJson('/api/categorias');

        $response->assertStatus(206);
        $response->assertJsonStructure([
            'current_page',
            'data' => [
                '*' => [
                    'id',
                    'nombre',
                    'activa',
                    'created_at',
                    'updated_at',
                ],
            ],
            'first_page_url',
            'from',
            'last_page',
            'last_page_url',
            'links' => [
                '*' => ['url', 'label', 'page', 'active'],
            ],
            'next_page_url',
            'path',
            'per_page',
            'prev_page_url',
            'to',
            'total',
            'columns' => [
                '*' => ['accessor', 'title'],
            ],
        ]);

        $this->assertEquals(Categoria::count(), $response->json('total'));
    }

    public function test_store_categoria(): void
    {
        $this->loginAdmin();

        $payload = [
            'nombre' => $this->faker->name,
            'activa' => $this->faker->boolean,
        ];

        $response = $this->post('/api/categorias', $payload);
        $response->assertStatus(200);

        $response->assertJson([
            'status' => 'OK',
            'message' => null,
            'data' => [
                'nombre' => $payload['nombre'],
                'activa' => $payload['activa'],
            ],
        ]);
    }

    public function test_show_categoria(): void
    {
        $this->loginAdmin();

        $categoria = Categoria::factory()->create();

        $response = $this->get("/api/categorias/{$categoria->id}");
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'id',
                'nombre',
                'activa',
                'created_at',
                'updated_at',
            ],
        ]);
    }

    public function test_update_categoria(): void
    {
        $this->loginAdmin();

        $categoria = Categoria::factory()->create();

        $payload = [
            'nombre' => $this->faker->name,
            'activa' => $this->faker->boolean,
        ];

        $response = $this->put("/api/categorias/{$categoria->id}", $payload);
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'OK',
            'message' => null,
            'data' => [
                'id' => $categoria->id,
                'nombre' => $payload['nombre'],
                'activa' => $payload['activa'],
            ],
        ]);
    }

    public function test_delete_categoria(): void
    {
        $this->loginAdmin();

        $categoria = Categoria::factory()->create();

        $response = $this->delete("/api/categorias/{$categoria->id}");
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'OK',
            'message' => 'Categoría eliminada',
            'data' => false,
        ]);
    }

    public function test_update_categoria_conservando_su_nombre(): void
    {
        $this->loginAdmin();
        $registro = Categoria::factory()->create(['nombre' => $this->faker->unique()->lexify('cat-?????')]);

        $this->put("/api/categorias/{$registro->id}", ['nombre' => $registro->nombre])->assertStatus(200);
    }

    public function test_update_categoria_con_nombre_de_otro_registro(): void
    {
        $this->loginAdmin();
        $registro = Categoria::factory()->create(['nombre' => $this->faker->unique()->lexify('cat-?????')]);
        $otro = Categoria::factory()->create(['nombre' => $this->faker->unique()->lexify('cat-?????')]);

        $this->expectException(ValidationException::class);
        $this->put("/api/categorias/{$registro->id}", ['nombre' => $otro->nombre]);
    }
}
