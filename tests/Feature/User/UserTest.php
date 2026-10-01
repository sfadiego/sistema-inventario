<?php

namespace Tests\Feature\User;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UserTest extends TestCase
{
    private function payload(array $override = []): array
    {
        return array_merge([
            'name' => $this->faker->unique()->name,
            'email' => $this->faker->unique()->safeEmail,
            'role_id' => RoleEnum::User->value,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'activo' => true,
        ], $override);
    }

    public function test_index_users(): void
    {
        $this->loginAdmin();
        User::factory()->count(3)->create();

        $response = $this->get('/api/users');

        $response->assertStatus(206);
        $response->assertJsonStructure(['current_page', 'total', 'data' => ['*' => ['id', 'name', 'email']]]);
    }

    public function test_show_user(): void
    {
        $this->loginAdmin();
        $user = User::factory()->create();

        $this->get("/api/users/{$user->id}")
            ->assertStatus(200)
            ->assertJson(['data' => ['id' => $user->id, 'email' => $user->email]])
            ->assertJsonMissingPath('data.password');
    }

    public function test_store_user_guarda_password_hasheado(): void
    {
        $this->loginAdmin();
        $payload = $this->payload();

        $this->post('/api/users', $payload)->assertStatus(200);

        $user = User::where('email', $payload['email'])->firstOrFail();
        $this->assertNotEquals('password123', $user->password);
        $this->assertTrue(Hash::check('password123', $user->password));
        $this->assertEquals(RoleEnum::User->value, $user->role_id);
    }

    public function test_store_user_con_email_duplicado(): void
    {
        $this->loginAdmin();
        $existente = User::factory()->create();

        $this->expectException(ValidationException::class);
        $this->post('/api/users', $this->payload(['email' => $existente->email]));
    }

    public function test_store_user_con_password_sin_confirmar(): void
    {
        $this->loginAdmin();

        $this->expectException(ValidationException::class);
        $this->post('/api/users', $this->payload(['password_confirmation' => 'otra-cosa']));
    }

    public function test_store_user_con_rol_inexistente(): void
    {
        $this->loginAdmin();

        $this->expectException(ValidationException::class);
        $this->post('/api/users', $this->payload(['role_id' => 999]));
    }

    public function test_update_user(): void
    {
        $this->loginAdmin();
        $user = User::factory()->create();
        $payload = $this->payload(['role_id' => RoleEnum::Admin->value]);

        $this->put("/api/users/{$user->id}", $payload)->assertStatus(200);

        $user->refresh();
        $this->assertEquals($payload['email'], $user->email);
        $this->assertEquals(RoleEnum::Admin->value, $user->role_id);
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_delete_user(): void
    {
        $this->loginAdmin();
        $user = User::factory()->create();

        $this->delete("/api/users/{$user->id}")->assertStatus(200);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_update_user_conservando_nombre_y_correo(): void
    {
        $this->loginAdmin();
        $user = User::factory()->create();

        $this->put("/api/users/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'role_id' => RoleEnum::Admin->value,
        ])->assertStatus(200);

        $this->assertEquals(RoleEnum::Admin->value, $user->fresh()->role_id);
    }

    public function test_update_user_sin_password_conserva_el_actual(): void
    {
        $this->loginAdmin();
        $user = User::factory()->create();
        $hashAnterior = $user->password;

        $this->put("/api/users/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'role_id' => $user->role_id->value ?? $user->role_id,
        ])->assertStatus(200);

        $this->assertEquals($hashAnterior, $user->fresh()->password);
    }

    public function test_update_user_con_correo_de_otro_usuario(): void
    {
        $this->loginAdmin();
        $user = User::factory()->create();
        $otro = User::factory()->create();

        $this->expectException(ValidationException::class);
        $this->put("/api/users/{$user->id}", [
            'name' => $user->name,
            'email' => $otro->email,
            'role_id' => RoleEnum::User->value,
        ]);
    }
}
