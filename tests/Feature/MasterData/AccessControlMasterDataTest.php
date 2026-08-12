<?php

namespace Tests\Feature\MasterData;

use App\Models\Organization;
use App\Models\PriceBook;
use App\Models\Role;
use App\Models\Sku;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Database\Seeders\PhaseOneReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlMasterDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('platform.features.master_data', true);
        $this->seed(PhaseOneReferenceSeeder::class);
    }

    public function test_roles_and_scoped_assignments_are_dynamic_locked_and_audited(): void
    {
        $organization = $this->organization();
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $created = $this->forOrganization()->postJson('/api/v1/master-data/access-controls/roles', $this->rolePayload())
            ->assertCreated()->assertJsonPath('data.code', 'MASTER-OPERATOR')->assertJsonPath('data.permissions.1', 'master_data.manage');
        $roleId = $created->json('data.id');
        $branch = $this->forOrganization()->getJson('/api/v1/master-data/access-controls')->assertOk()->json('data.branches.0.public_id');
        $assignment = $this->forOrganization()->postJson('/api/v1/master-data/access-controls/assignments', ['user_public_id' => $user->public_id, 'role_public_id' => $roleId, 'data_scope' => 'branches', 'branch_public_ids' => [$branch]])
            ->assertCreated()->assertJsonPath('data.user.email', $user->email)->assertJsonPath('data.data_scope', 'branches')->assertJsonPath('data.branch_public_ids.0', $branch);
        $this->forOrganization()->patchJson("/api/v1/master-data/access-controls/roles/{$roleId}/archive", ['version' => 1, 'reason' => 'Retire role'])
            ->assertConflict()->assertJsonPath('code', 'role_has_assignments');
        $this->forOrganization()->patchJson('/api/v1/master-data/access-controls/assignments/'.$assignment->json('data.id').'/revoke', ['version' => 1, 'reason' => 'Responsibility changed'])
            ->assertOk()->assertJsonPath('data.status', 'revoked');
        $this->forOrganization()->patchJson("/api/v1/master-data/access-controls/roles/{$roleId}/archive", ['version' => 1, 'reason' => 'Retire role'])
            ->assertOk()->assertJsonPath('data.status', 'archived');
        $this->assertDatabaseHas('audit_events', ['action' => 'master_data.role_assignment.revoked', 'entity_public_id' => $assignment->json('data.id')]);
    }

    public function test_permission_middleware_denies_unauthenticated_and_underprivileged_access(): void
    {
        config()->set('platform.features.authentication', true);
        $organization = $this->organization();
        $viewer = User::factory()->create(['organization_id' => $organization->id]);
        $role = Role::query()->create(['organization_id' => $organization->id, 'code' => 'VIEWER', 'name_en' => 'Viewer', 'permissions' => ['master_data.view'], 'status' => 'active']);
        UserRoleAssignment::query()->create(['organization_id' => $organization->id, 'user_id' => $viewer->id, 'role_id' => $role->id, 'data_scope' => 'organization', 'status' => 'active']);

        $this->forOrganization()->getJson('/api/v1/master-data/foundation-masters/banks')->assertUnauthorized();
        $this->actingAs($viewer)->forOrganization()->getJson('/api/v1/master-data/foundation-masters/banks')->assertOk();
        $this->actingAs($viewer)->forOrganization()->postJson('/api/v1/master-data/foundation-masters/banks', $this->masterPayload())->assertForbidden()->assertJsonPath('permission', 'master_data.manage');
        $this->actingAs($viewer)->forOrganization()->get('/api/v1/master-data/foundation-masters/banks/export')->assertForbidden();
        $this->actingAs($viewer)->forOrganization()->postJson('/api/v1/master-data/access-controls/roles', $this->rolePayload())->assertForbidden();
    }

    public function test_manager_permissions_allow_mutation_but_preserve_organization_boundary(): void
    {
        config()->set('platform.features.authentication', true);
        $organization = $this->organization();
        $manager = User::factory()->create(['organization_id' => $organization->id]);
        $role = Role::query()->create(['organization_id' => $organization->id, 'code' => 'MANAGER', 'name_en' => 'Manager', 'permissions' => ['master_data.view', 'master_data.manage', 'master_data.export'], 'status' => 'active']);
        UserRoleAssignment::query()->create(['organization_id' => $organization->id, 'user_id' => $manager->id, 'role_id' => $role->id, 'data_scope' => 'organization', 'status' => 'active']);

        $this->actingAs($manager)->forOrganization()->postJson('/api/v1/master-data/foundation-masters/banks', $this->masterPayload())
            ->assertCreated()->assertJsonPath('data.code', 'AYA');
        $this->actingAs($manager)->forOrganization()->get('/api/v1/master-data/foundation-masters/banks/export')->assertOk();

        $other = Organization::query()->create(['code' => 'OTHER', 'name' => 'Other', 'currency' => 'MMK', 'timezone' => 'Asia/Yangon', 'status' => 'active']);
        $this->actingAs($manager)->withHeader('X-Organization-ID', $other->public_id)->getJson('/api/v1/master-data/foundation-masters/banks')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'AYA');
    }

    public function test_approval_permission_and_monetary_threshold_are_both_enforced(): void
    {
        $organization = $this->organization();
        $book = PriceBook::query()->where('organization_id', $organization->id)->where('code', 'SPECIAL-DEFAULT')->firstOrFail();
        $sku = Sku::query()->where('organization_id', $organization->id)->firstOrFail();
        $unit = UnitOfMeasure::query()->where('organization_id', $organization->id)->where('code', 'BTL')->firstOrFail();
        $price = $this->forOrganization()->postJson('/api/v1/master-data/prices', ['price_book_public_id' => $book->public_id, 'sku_public_id' => $sku->public_id, 'uom_public_id' => $unit->public_id, 'unit_price_minor' => 750, 'minimum_quantity' => 1, 'effective_from' => '2026-08-01', 'effective_to' => null, 'status' => 'active'])->assertCreated();
        $approver = User::factory()->create(['organization_id' => $organization->id]);
        $role = Role::query()->create(['organization_id' => $organization->id, 'code' => 'LIMITED-APPROVER', 'name_en' => 'Limited Approver', 'permissions' => ['master_data.prices.approve'], 'approval_limit_minor' => 500, 'status' => 'active']);
        UserRoleAssignment::query()->create(['organization_id' => $organization->id, 'user_id' => $approver->id, 'role_id' => $role->id, 'data_scope' => 'organization', 'status' => 'active']);
        config()->set('platform.features.authentication', true);

        $this->actingAs($approver)->forOrganization()->patchJson('/api/v1/master-data/prices/'.$price->json('data.id').'/approve', ['version' => 1, 'reason' => 'Review complete'])
            ->assertForbidden()->assertJsonPath('code', 'approval_limit_exceeded');
        $role->update(['approval_limit_minor' => 1000]);
        $this->actingAs($approver)->forOrganization()->patchJson('/api/v1/master-data/prices/'.$price->json('data.id').'/approve', ['version' => 1, 'reason' => 'Review complete'])
            ->assertOk()->assertJsonPath('data.approval_status', 'approved');
    }

    private function rolePayload(): array
    {
        return ['code' => ' master-operator ', 'name_en' => 'Master Operator', 'name_my' => null, 'permissions' => ['master_data.view', 'master_data.manage', 'master_data.import', 'master_data.export'], 'approval_limit_minor' => null, 'status' => 'active'];
    }

    private function masterPayload(): array
    {
        return ['code' => 'AYA', 'name_en' => 'AYA Bank', 'name_my' => null, 'classification' => 'commercial', 'branch_public_id' => null, 'area_public_id' => null, 'way_public_id' => null, 'price_book_public_id' => null, 'parent_public_id' => null, 'phone' => null, 'email' => null, 'address' => null, 'registration_number' => null, 'metadata' => null, 'sort_order' => 0, 'status' => 'active'];
    }

    private function organization(): Organization
    {
        return Organization::query()->where('code', 'VALLEY')->firstOrFail();
    }

    private function forOrganization(): static
    {
        return $this->withHeader('X-Organization-ID', $this->organization()->public_id);
    }
}
