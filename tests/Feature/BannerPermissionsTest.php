<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\LandingBanner;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class BannerPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);
        Empresa::query()->create(['nombre' => 'Empresa de prueba']);
    }

    public function test_owner_can_open_create_route_without_it_being_resolved_as_a_banner(): void
    {
        $owner = User::query()->where('is_owner', true)->firstOrFail();

        $this->actingAs($owner)
            ->get(route('admin.banners.create'))
            ->assertOk()
            ->assertViewIs('admin.banners.create');
    }

    public function test_view_only_staff_cannot_see_or_open_banner_mutations(): void
    {
        $viewer = $this->staffWithPermission('banners.view');
        $banner = $this->banner();

        $this->actingAs($viewer)
            ->get(route('admin.banners.index'))
            ->assertOk()
            ->assertDontSee('+ Nuevo Banner');

        $this->actingAs($viewer)
            ->get(route('admin.banners.show', $banner))
            ->assertOk()
            ->assertDontSee('Editar Banner')
            ->assertDontSee('Eliminar');

        $this->actingAs($viewer)
            ->get(route('admin.banners.create'))
            ->assertForbidden();
    }

    public function test_create_only_staff_can_create_without_accessing_the_index(): void
    {
        $creator = $this->staffWithPermission('banners.create');

        $this->actingAs($creator)
            ->get(route('admin.banners.create'))
            ->assertOk();

        $this->actingAs($creator)
            ->get(route('dashboard'))
            ->assertRedirect(route('admin.banners.create'));

        $this->actingAs($creator)
            ->get(route('admin.banners.index'))
            ->assertForbidden();

        $this->actingAs($creator)
            ->post(route('admin.banners.store'), [
                'titulo' => 'Banner creado por empleado',
                'orden' => 1,
                'activo' => 1,
            ])
            ->assertRedirect(route('admin.banners.create'));

        $this->assertDatabaseHas('landing_banners', [
            'titulo' => 'Banner creado por empleado',
        ]);
    }

    public function test_update_and_delete_permissions_are_independent(): void
    {
        $banner = $this->banner();
        $updater = $this->staffWithPermission('banners.update');

        $this->actingAs($updater)
            ->put(route('admin.banners.update', $banner), [
                'titulo' => 'Banner actualizado',
                'orden' => 2,
                'activo' => 1,
            ])
            ->assertRedirect(route('admin.banners.edit', $banner));

        $this->assertDatabaseHas('landing_banners', [
            'id' => $banner->id,
            'titulo' => 'Banner actualizado',
        ]);

        $this->actingAs($updater)
            ->delete(route('admin.banners.destroy', $banner))
            ->assertForbidden();

        $deleter = $this->staffWithPermission('banners.delete');
        $this->actingAs($deleter)
            ->delete(route('admin.banners.destroy', $banner))
            ->assertRedirect(route('home'));

        $this->assertDatabaseMissing('landing_banners', ['id' => $banner->id]);
    }

    public function test_staff_without_banner_permissions_cannot_access_the_module(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('empleado');

        $this->actingAs($staff)->get(route('admin.banners.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.banners.create'))->assertForbidden();
    }

    public function test_legacy_manage_permission_is_migrated_without_losing_access(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('empleado');
        Permission::query()->create(['name' => 'banners.manage', 'guard_name' => 'web']);
        $staff->givePermissionTo('banners.manage');

        $migration = require database_path('migrations/2026_08_28_000100_split_banner_management_permission.php');
        $migration->up();

        $staff = $staff->fresh();

        $this->assertDatabaseMissing('permissions', ['name' => 'banners.manage']);
        $this->assertTrue($staff->hasDirectPermission('banners.create'));
        $this->assertTrue($staff->hasDirectPermission('banners.update'));
        $this->assertTrue($staff->hasDirectPermission('banners.delete'));
    }

    private function staffWithPermission(string $permission): User
    {
        $staff = User::factory()->create();
        $staff->assignRole('empleado');
        $staff->givePermissionTo($permission);

        return $staff;
    }

    private function banner(): LandingBanner
    {
        return LandingBanner::query()->create([
            'empresa_id' => Empresa::query()->value('id'),
            'titulo' => 'Banner inicial',
            'orden' => 0,
            'activo' => true,
        ]);
    }
}
