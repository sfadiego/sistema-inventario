<?php

namespace Tests\Feature\Subcategoria;

use App\Models\Categoria;
use App\Models\Subcategoria;
use Tests\TestCase;

class SubcategoriaTest extends TestCase
{
    public function test_index_subcategorias_por_categoria(): void
    {
        $this->loginAdmin();

        $categoria = Categoria::factory()->create();
        Subcategoria::factory()->count(3)->create([
            'categoria_id' => $categoria->id,
        ]);

        $response = $this->getJson("/api/categorias/{$categoria->id}/subcategorias");
        $response->assertStatus(206);
        $response->assertJsonCount(3, 'data');

        foreach ($categoria->subcategorias as $subcategoria) {
            $response->assertJsonFragment([
                'id' => $subcategoria->id,
                'nombre' => $subcategoria->nombre,
                'categoria_id' => $subcategoria->categoria_id,
            ]);
        }
    }

    public function test_index_subcategorias_categoria_sin_subcategorias(): void
    {
        $this->loginAdmin();

        $categoria = Categoria::factory()->create();

        $response = $this->getJson("/api/categorias/{$categoria->id}/subcategorias");
        $response->assertStatus(206);
        $response->assertJson([
            'data' => [],
        ]);
    }

    public function test_index_subcategorias_categoria_invalida(): void
    {
        $this->loginAdmin();

        $this->withExceptionHandling();

        $invalidId = 999999999;

        $response = $this->getJson("/api/categorias/{$invalidId}/subcategorias");

        $response->assertStatus(422);
        $response->assertJson([
            'status' => 'error',
            'message' => 'Categoria no valida',
            'data' => null,
        ]);
    }

    public function test_show_subcategoria_de_categoria(): void
    {
        $this->loginAdmin();

        $categoria = Categoria::factory()->create([
            'nombre' => fake()->unique()->word,
        ]);
        $subcategoria = Subcategoria::factory()->create([
            'categoria_id' => $categoria->id,
            'nombre' => fake()->unique()->word,
        ]);

        $response = $this->getJson("/api/categorias/{$categoria->id}/subcategorias/{$subcategoria->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'id' => $subcategoria->id,
                'nombre' => $subcategoria->nombre,
                'categoria_id' => $categoria->id,
            ],
        ]);

        $this->assertDatabaseHas('subcategoria', [
            'id' => $subcategoria->id,
            'nombre' => $subcategoria->nombre,
            'categoria_id' => $categoria->id,
        ]);
    }

    public function test_show_categoria_sin_subcategorias(): void
    {
        $this->loginAdmin();

        $this->withExceptionHandling();

        $categoria = Categoria::factory()->create();
        $subcategoria_invalid = 999999999;

        $response = $this->getJson("/api/categorias/{$categoria->id}/subcategorias/{$subcategoria_invalid}");

        $response->assertStatus(422);
        $response->assertJson([
            'status' => 'error',
            'message' => 'Subcategoria no encontrada',
            'data' => null,
        ]);
    }

    public function test_store_subcategoria(): void
    {
        $this->loginAdmin();

        $payload = [
            'nombre' => 'Nueva Subcategoria',
            'categoria_id' => Categoria::factory()->create()->id,
        ];

        $response = $this->post('/api/subcategorias', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'OK',
            'message' => null,
            'data' => [
                'nombre' => $payload['nombre'],
                'categoria_id' => $payload['categoria_id'],
            ],
        ]);
    }

    public function test_update_subcategoria(): void
    {
        $this->loginAdmin();

        $subcategoria = Subcategoria::factory()->create();

        $payload = [
            'nombre' => fake()->unique()->word,
            'categoria_id' => Categoria::factory()->create()->first()->id,
        ];

        $response = $this->put("/api/subcategorias/{$subcategoria->id}", $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'OK',
            'message' => null,
            'data' => [
                'id' => $subcategoria->id,
                'nombre' => $payload['nombre'],
                'categoria_id' => $payload['categoria_id'],
            ],
        ]);
    }

    public function test_delete_subcategoria(): void
    {
        $this->loginAdmin();

        $subcategoria = Subcategoria::factory()->create();

        $response = $this->delete("/api/subcategorias/{$subcategoria->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'OK',
            'message' => 'Subcategoría eliminada',
            'data' => false,
        ]);
    }
}
