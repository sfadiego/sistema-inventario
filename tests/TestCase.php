<?php

namespace Tests;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    use DatabaseTransactions,  WithFaker;

    public static bool $migrated = false;

    /**
     * La base se migra y siembra una sola vez por ejecución, al crear la primera aplicación
     * y antes de que DatabaseTransactions abra su transacción: así ningún test (ni el primero)
     * deja datos sin revertir.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        if (! self::$migrated) {
            self::$migrated = true;
            $kernel = $app->make(Kernel::class);
            $kernel->call('migrate:fresh');
            $kernel->call('db:seed');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutExceptionHandling();
    }

    public function loginAdmin(): User
    {
        return $this->createUser(RoleEnum::Admin);
    }

    public function createUser(RoleEnum $role): User
    {
        $roleKey = strtolower($role->value);
        $user = User::factory()->create(['role_id' => $role->value]);
        Sanctum::actingAs($user);
        // implementa permisos por roles
        // Authxolote::actionsFake(array_values(config("actions.roles.$roleKey")));

        return $user;
    }
}
