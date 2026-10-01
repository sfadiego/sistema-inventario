<?php

namespace Tests\Feature\Permisos;

use App\Enums\RoleEnum;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\HistorialAdeudo;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\ReporteMovimiento;
use App\Models\Ubicacion;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class PermisosTest extends TestCase
{
    private function adeudo(): HistorialAdeudo
    {
        return HistorialAdeudo::create([
            'cliente_id' => Cliente::factory()->create()->id,
            'venta_id' => Venta::factory()->create()->id,
            'total_adeudo' => -10,
        ]);
    }

    /**
     * Rutas protegidas con `can:admin`: método, uri.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private function rutasSoloAdmin(): array
    {
        return [
            ['delete', '/api/categorias/'.Categoria::factory()->create()->id],
            ['delete', '/api/clientes/'.Cliente::factory()->create()->id],
            ['delete', '/api/marcas/'.Marca::factory()->create()->id],
            ['post', '/api/productos'],
            ['post', '/api/productos/'.Producto::factory()->create()->id],
            ['delete', '/api/productos/'.Producto::factory()->create()->id],
            ['put', '/api/proveedores/'.Proveedor::factory()->create()->id],
            ['delete', '/api/proveedores/'.Proveedor::factory()->create()->id],
            ['delete', '/api/reporte-movimientos/'.ReporteMovimiento::factory()->create()->id],
            ['post', '/api/tipo-movimientos'],
            ['post', '/api/ubicaciones'],
            ['put', '/api/ubicaciones/'.Ubicacion::factory()->create()->id],
            ['delete', '/api/ubicaciones/'.Ubicacion::factory()->create()->id],
            ['post', '/api/users'],
            ['put', '/api/users/'.User::factory()->create()->id],
            ['delete', '/api/users/'.User::factory()->create()->id],
            ['delete', '/api/ventas/'.Venta::factory()->create()->id],
            ['post', '/api/imports'],
            ['get', '/api/pdf/reporte-ventas'],
            ['get', '/api/error-reporting/create-dump'],
            ['put', '/api/adeudos/'.$this->adeudo()->id.'/liquidar'],
            ['put', '/api/adeudos/'.Cliente::factory()->create()->id.'/liquidar-todos'],
        ];
    }

    public function test_empleado_no_puede_usar_rutas_de_administrador(): void
    {
        $this->createUser(RoleEnum::User);

        foreach ($this->rutasSoloAdmin() as [$method, $uri]) {
            try {
                $this->{$method.'Json'}($uri);
                $this->fail("El empleado pudo acceder a {$method} {$uri}");
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_empleado_si_puede_consultar_y_vender(): void
    {
        $this->createUser(RoleEnum::User);

        foreach (['/api/productos', '/api/ventas', '/api/clientes', '/api/marcas', '/api/categorias', '/api/dashboard/total-ventas'] as $uri) {
            $this->getJson($uri)->assertSuccessful();
        }
    }

    public function test_superadmin_tiene_permisos_de_administrador(): void
    {
        $user = $this->createUser(RoleEnum::SuperAdmin);
        $cliente = Cliente::factory()->create();

        $this->assertTrue(Gate::forUser($user)->allows('admin'));
        $this->putJson("/api/adeudos/{$cliente->id}/liquidar-todos")->assertStatus(200);
    }

    public function test_rutas_requieren_autenticacion(): void
    {
        foreach (['/api/productos', '/api/ventas', '/api/clientes', '/api/dashboard/ventas', '/api/users'] as $uri) {
            try {
                $this->getJson($uri);
                $this->fail("Se pudo acceder sin autenticar a {$uri}");
            } catch (AuthenticationException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
