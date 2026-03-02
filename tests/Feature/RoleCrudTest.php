<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleCrudTest extends TestCase
{
    use RefreshDatabase;

    /** Crea permisos i un admin amb tots els permisos de rols. */
    private function createAdmin(): User
    {
        foreach (['roles.manage', 'roles.delete', 'roles.view'] as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        $admin = User::factory()->create();
        $admin->givePermissionTo(['roles.manage', 'roles.delete', 'roles.view']);

        return $admin;
    }

    /** GET /api/roles sense token → 401 */
    public function test_guest_cannot_access_roles()
    {
        $this->getJson('/api/roles')->assertStatus(401);
    }

    /** Flux complet: crear, llistar, actualitzar, eliminar */
    public function test_admin_can_create_list_update_and_delete_role()
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'sanctum');

        // Create
        $resp = $this->postJson('/api/roles', ['name' => 'Editor']);
        $resp->assertStatus(201)->assertJsonFragment(['name' => 'Editor']);

        $roleId = $resp->json('data.id');
        $this->assertNotNull($roleId);

        // List
        $this->getJson('/api/roles')
            ->assertStatus(200)
            ->assertJsonStructure(['data']);

        // Update
        $this->putJson('/api/roles/' . $roleId, ['name' => 'Editor Updated'])
            ->assertStatus(200)
            ->assertJsonFragment(['name' => 'Editor Updated']);

        // Delete
        $this->deleteJson('/api/roles/' . $roleId)
            ->assertStatus(200)
            ->assertJson(['message' => 'Role deleted successfully']);

        $this->assertDatabaseMissing('roles', ['id' => $roleId]);
    }

    /** Usuari sense permisos no pot crear → 403 */
    public function test_regular_user_cannot_create_role()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/roles', ['name' => 'Hacker'])->assertStatus(403);
    }

    /** No es pot eliminar el rol Admin → 403 */
    public function test_cannot_delete_system_role()
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'sanctum');

        $adminRole = Role::firstOrCreate(['name' => 'Admin']);

        $this->deleteJson('/api/roles/' . $adminRole->id)->assertStatus(403);
    }

    /** No es pot modificar el rol Admin → 403 */
    public function test_cannot_update_system_role()
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'sanctum');

        $adminRole = Role::firstOrCreate(['name' => 'Admin']);

        $this->putJson('/api/roles/' . $adminRole->id, ['name' => 'Hacked'])->assertStatus(403);
    }

    /** Validació: nom duplicat → 422 */
    public function test_store_fails_with_duplicate_name()
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'sanctum');

        Role::firstOrCreate(['name' => 'Existing']);

        $this->postJson('/api/roles', ['name' => 'Existing'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /** Filtre per search funciona */
    public function test_index_filter_by_search()
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'sanctum');

        Role::firstOrCreate(['name' => 'SuperEditor']);
        Role::firstOrCreate(['name' => 'Viewer']);

        $resp = $this->getJson('/api/roles?search=super');
        $resp->assertStatus(200);

        $names = collect($resp->json('data'))->pluck('name')->toArray();
        $this->assertContains('SuperEditor', $names);
        $this->assertNotContains('Viewer', $names);
    }
}
