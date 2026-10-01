<?php

namespace Tests\Feature\Images;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Ubicacion;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class TestImages extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_product_image(): void
    {
        $this->loginAdmin();

        $producto = Producto::factory()->create([
            'proveedor_id' => Proveedor::factory()->create()->id,
            'categoria_id' => Categoria::factory()->create()->id,
            'ubicacion_id' => Ubicacion::factory()->create()->id,
        ]);

        $file = UploadedFile::fake()->image('new_image.jpg');
        $payload = [
            'file' => $file,
        ];

        $response = $this->post("/api/productos/{$producto->id}", $payload);
        $imagePath = $response->json('data.imagen.path');
        $imageArchivo = $response->json('data.imagen.archivo');

        $responseImage = $this->get("/api/images/{$imagePath}/{$imageArchivo}", [
            'Content-Type' => 'blob',
        ]);
        // TODO: hacer que pase el test
        $responseImage->assertStatus(200);
        $responseImage->assertHeader('Content-Type', 'image/jpeg');
        $this->assertNotEmpty($responseImage->getContent());
        $this->assertIsString($responseImage->getContent());

        // todo: revisar que este en base de datos
        // revisar que este en local storage

    }
}
