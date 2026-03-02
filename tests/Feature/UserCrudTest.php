<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class UserCrudTest extends TestCase
{
    use RefreshDatabase;

    /** Crea permisos i un admin amb tots els permisos d'usuaris. */
    private function createAdmin(): User
    {
        foreach (['users.manage', 'users.delete', 'users.restore', 'users.view'] as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        $admin = User::factory()->create();
        $admin->givePermissionTo(['users.manage', 'users.delete', 'users.restore', 'users.view']);

        return $admin;
    }

    /** GET /api/users sense token → 401 */
    public function test_guest_cannot_access_users()
    {
        $this->getJson('/api/users')->assertStatus(401);
    }

    /** Flux complet: crear, llistar, actualitzar, esborrar i restaurar */
    public function test_admin_can_create_list_update_delete_and_restore_user()
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'sanctum');

        // Create
        $createResp = $this->postJson('/api/users', [
            'name'                  => 'Test User',
            'username'              => 'testuser',
            'email'                 => 'test@example.com',
            'password'              => 'password12345',
            'password_confirmation' => 'password12345',
        ]);
        $createResp->assertStatus(201)->assertJsonFragment(['email' => 'test@example.com']);

        $userId = $createResp->json('data.id');
        $this->assertNotNull($userId, 'Created user id missing from response');

        // List
        $this->getJson('/api/users')
            ->assertStatus(200)
            ->assertJsonStructure(['data']);

        // Update
        $this->putJson('/api/users/' . $userId, ['name' => 'Updated Name'])
            ->assertStatus(200)
            ->assertJsonFragment(['name' => 'Updated Name']);

        // Delete
        $this->deleteJson('/api/users/' . $userId)
            ->assertStatus(200)
            ->assertJson(['message' => 'User deleted successfully']);

        $this->assertSoftDeleted('users', ['id' => $userId]);

        // Restore
        $this->postJson('/api/users/' . $userId . '/restore')
            ->assertStatus(200)
            ->assertJsonFragment(['id' => $userId]);

        $this->assertDatabaseHas('users', ['id' => $userId, 'deleted_at' => null]);
    }

    /** Usuari sense permisos no pot crear → 403 */
    public function test_regular_user_cannot_create_user()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/users', [
            'name'                  => 'X',
            'username'              => 'x',
            'email'                 => 'x@example.com',
            'password'              => 'password12345',
            'password_confirmation' => 'password12345',
        ])->assertStatus(403);
    }

    /** Validació: email duplicat retorna 422 */
    public function test_store_fails_with_duplicate_email()
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'sanctum');

        $existing = User::factory()->create(['email' => 'dup@example.com']);

        $this->postJson('/api/users', [
            'name'                  => 'Dup',
            'username'              => 'dup',
            'email'                 => 'dup@example.com',
            'password'              => 'password12345',
            'password_confirmation' => 'password12345',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    /** Validació: password massa curt retorna 422 */
    public function test_store_fails_with_short_password()
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'sanctum');

        $this->postJson('/api/users', [
            'name'                  => 'Short',
            'username'              => 'short',
            'email'                 => 'short@example.com',
            'password'              => '123',
            'password_confirmation' => '123',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    /** Filtre per search retorna resultats correctes */
    public function test_index_filter_by_search()
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'sanctum');

        User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        User::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);

        $resp = $this->getJson('/api/users?search=john');
        $resp->assertStatus(200);

        $emails = collect($resp->json('data'))->pluck('email')->toArray();
        $this->assertContains('john@example.com', $emails);
        $this->assertNotContains('jane@example.com', $emails);
    }

    /** Un usuari pot veure i actualitzar el seu propi perfil sense permisos addicionals */
    public function test_user_can_view_and_update_own_profile()
    {
        $user = User::factory()->create(['name' => 'Original']);
        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/users/' . $user->id)->assertStatus(200);

        $this->putJson('/api/users/' . $user->id, ['name' => 'Changed'])
            ->assertStatus(200)
            ->assertJsonFragment(['name' => 'Changed']);
    }

    /** Un admin no es pot eliminar a ell mateix → 403 */
    public function test_admin_cannot_delete_themselves()
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'sanctum');

        $this->deleteJson('/api/users/' . $admin->id)->assertStatus(403);
    }
}
