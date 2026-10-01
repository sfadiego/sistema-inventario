<?php

namespace Tests\Feature\Adeudos;

use App\Enums\RoleEnum;
use App\Models\Cliente;
use App\Models\HistorialAdeudo;
use App\Models\Venta;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\TestCase;

class AdeudosTest extends TestCase
{
    /**
     * Cliente con dos ventas a crédito de $100 y $200 (los adeudos se guardan en negativo).
     *
     * @return array{0: Cliente, 1: HistorialAdeudo, 2: HistorialAdeudo}
     */
    private function clienteConAdeudos(): array
    {
        $cliente = Cliente::factory()->create(['adeudo' => -300]);
        $crear = fn (float $total) => HistorialAdeudo::create([
            'cliente_id' => $cliente->id,
            'venta_id' => Venta::factory()->create(['cliente_id' => $cliente->id])->id,
            'total_adeudo' => $total,
            'pagado' => false,
        ]);

        return [$cliente, $crear(-100), $crear(-200)];
    }

    public function test_liquidar_un_adeudo(): void
    {
        $this->loginAdmin();
        [$cliente, $primero, $segundo] = $this->clienteConAdeudos();

        $response = $this->putJson("/api/adeudos/{$primero->id}/liquidar");

        $response->assertStatus(200);
        $this->assertTrue((bool) $primero->fresh()->pagado);
        $this->assertFalse((bool) $segundo->fresh()->pagado);
        $this->assertEquals(-200, $cliente->fresh()->adeudo);
    }

    public function test_liquidar_adeudo_ya_pagado(): void
    {
        $this->loginAdmin();
        [$cliente, $primero] = $this->clienteConAdeudos();
        $this->putJson("/api/adeudos/{$primero->id}/liquidar")->assertStatus(200);

        $response = $this->putJson("/api/adeudos/{$primero->id}/liquidar");

        $response->assertStatus(422);
        // no se descuenta dos veces
        $this->assertEquals(-200, $cliente->fresh()->adeudo);
    }

    public function test_liquidar_todos_los_adeudos_del_cliente(): void
    {
        $this->loginAdmin();
        [$cliente, $primero, $segundo] = $this->clienteConAdeudos();
        $otro = Cliente::factory()->create(['adeudo' => -50]);

        $response = $this->putJson("/api/adeudos/{$cliente->id}/liquidar-todos");

        $response->assertStatus(200);
        $this->assertEquals(0, $cliente->fresh()->adeudo);
        $this->assertTrue((bool) $primero->fresh()->pagado);
        $this->assertTrue((bool) $segundo->fresh()->pagado);
        // otros clientes no se tocan
        $this->assertEquals(-50, $otro->fresh()->adeudo);
    }

    public function test_empleado_no_puede_liquidar_adeudos(): void
    {
        $this->createUser(RoleEnum::User);
        [$cliente, $primero] = $this->clienteConAdeudos();

        $this->expectException(AuthorizationException::class);
        $this->putJson("/api/adeudos/{$primero->id}/liquidar");
    }
}
