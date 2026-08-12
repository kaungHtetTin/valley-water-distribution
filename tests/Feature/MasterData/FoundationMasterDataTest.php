<?php

namespace Tests\Feature\MasterData;

use App\Models\Area;
use App\Models\Branch;
use App\Models\FoundationMasterRecord;
use App\Models\Organization;
use App\Models\PriceBook;
use Database\Seeders\PhaseOneReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoundationMasterDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('platform.features.master_data', true);
        $this->seed(PhaseOneReferenceSeeder::class);
    }

    public function test_all_required_foundation_master_types_are_dynamic_and_available(): void
    {
        $response = $this->actingFor()->getJson('/api/v1/master-data/foundation-masters/customers')->assertOk();
        $response->assertJsonCount(21, 'types')->assertJsonCount(0, 'data')->assertJsonCount(1, 'options.branches')->assertJsonCount(3, 'options.areas')->assertJsonCount(3, 'options.price_books');
        $this->assertContains('suppliers', $response->json('types'));
        $this->assertContains('employees', $response->json('types'));
        $this->assertContains('vehicles', $response->json('types'));
        $this->assertContains('gl-accounts', $response->json('types'));
        $this->assertContains('allowance-types', $response->json('types'));
    }

    public function test_customer_lifecycle_is_scoped_referenced_locked_and_audited(): void
    {
        $organization = $this->organization();
        $branch = Branch::query()->where('organization_id', $organization->id)->firstOrFail();
        $area = Area::query()->where('organization_id', $organization->id)->where('code', 'TGI')->firstOrFail();
        $book = PriceBook::query()->where('organization_id', $organization->id)->where('code', 'RETAIL-DEFAULT')->firstOrFail();
        $payload = ['code' => ' tgi-shop-001 ', 'name_en' => 'TGI Shop 001', 'name_my' => null, 'classification' => 'retail', 'branch_public_id' => $branch->public_id, 'area_public_id' => $area->public_id, 'way_public_id' => null, 'price_book_public_id' => $book->public_id, 'parent_public_id' => null, 'phone' => '0912345678', 'email' => null, 'address' => 'Taunggyi', 'registration_number' => null, 'metadata' => ['credit_mode' => 'cod'], 'sort_order' => 10, 'status' => 'active'];

        $created = $this->actingFor()->postJson('/api/v1/master-data/foundation-masters/customers', $payload)
            ->assertCreated()->assertJsonPath('data.code', 'TGI-SHOP-001')->assertJsonPath('data.branch.code', 'TGI')->assertJsonPath('data.area.code', 'TGI')->assertJsonPath('data.price_book.code', 'RETAIL-DEFAULT');
        $id = $created->json('data.id');
        $this->actingFor()->putJson("/api/v1/master-data/foundation-masters/customers/{$id}", [...$payload, 'code' => 'TGI-SHOP-001', 'name_en' => 'TGI Shop One', 'version' => 1])
            ->assertOk()->assertJsonPath('data.version', 2);
        $this->actingFor()->putJson("/api/v1/master-data/foundation-masters/customers/{$id}", [...$payload, 'code' => 'TGI-SHOP-001', 'version' => 1])
            ->assertConflict()->assertJsonPath('code', 'stale_version');
        $this->assertDatabaseHas('audit_events', ['action' => 'master_data.foundation_master.created', 'entity_public_id' => $id]);
        $this->assertDatabaseHas('audit_events', ['action' => 'master_data.foundation_master.updated', 'entity_public_id' => $id]);
    }

    public function test_parent_dependencies_and_cycles_protect_hierarchical_masters(): void
    {
        $parent = $this->createRecord('departments', 'OPS', 'Operations');
        $child = $this->actingFor()->postJson('/api/v1/master-data/foundation-masters/departments', [...$this->payload('DELIVERY', 'Delivery'), 'parent_public_id' => $parent->json('data.id')])->assertCreated();
        $this->actingFor()->putJson('/api/v1/master-data/foundation-masters/departments/'.$parent->json('data.id'), [...$this->payload('OPS', 'Operations'), 'parent_public_id' => $child->json('data.id'), 'version' => 1])
            ->assertConflict()->assertJsonPath('code', 'foundation_master_parent_cycle');
        $this->actingFor()->patchJson('/api/v1/master-data/foundation-masters/departments/'.$parent->json('data.id').'/archive', ['version' => 1, 'reason' => 'Restructure'])
            ->assertConflict()->assertJsonPath('code', 'foundation_master_has_children');
    }

    public function test_import_preview_commit_is_atomic_audited_and_duplicate_safe(): void
    {
        $preview = $this->actingFor()->postJson('/api/v1/master-data/foundation-masters/expense-types/imports/preview', ['source_name' => 'expense-types.csv', 'rows' => [
            ['code' => ' fuel ', 'name_en' => 'Fuel', 'name_my' => null, 'classification' => 'vehicle'],
            ['code' => 'MEALS', 'name_en' => 'Meals', 'name_my' => null, 'classification' => 'trip'],
        ]])->assertCreated()->assertJsonPath('data.status', 'previewed')->assertJsonPath('data.valid_rows', 2);
        $this->assertDatabaseMissing('foundation_master_records', ['type' => 'expense-types', 'code' => 'FUEL']);
        $this->actingFor()->postJson('/api/v1/master-data/foundation-masters/imports/'.$preview->json('data.id').'/commit')
            ->assertOk()->assertJsonPath('data.status', 'committed');
        $this->assertDatabaseHas('foundation_master_records', ['type' => 'expense-types', 'code' => 'FUEL']);
        $this->assertDatabaseHas('foundation_master_records', ['type' => 'expense-types', 'code' => 'MEALS']);
        $this->assertDatabaseHas('audit_events', ['action' => 'master_data.foundation_import.committed', 'entity_public_id' => $preview->json('data.id')]);
    }

    public function test_invalid_import_cannot_commit_or_partially_write(): void
    {
        $preview = $this->actingFor()->postJson('/api/v1/master-data/foundation-masters/vehicles/imports/preview', ['source_name' => 'vehicles.csv', 'rows' => [
            ['code' => 'TRUCK 1', 'name_en' => 'Truck 1'], ['code' => 'TRUCK 1', 'name_en' => 'Duplicate Truck'],
        ]])->assertCreated()->assertJsonPath('data.status', 'invalid')->assertJsonPath('data.invalid_rows', 2);
        $this->actingFor()->postJson('/api/v1/master-data/foundation-masters/imports/'.$preview->json('data.id').'/commit')
            ->assertConflict()->assertJsonPath('code', 'invalid_import_batch');
        $this->assertDatabaseCount('foundation_master_records', 0);
    }

    public function test_search_export_and_organization_isolation_do_not_leak_records(): void
    {
        $this->createRecord('banks', 'AYA', 'AYA Bank');
        $other = Organization::query()->create(['code' => 'OTHER', 'name' => 'Other', 'currency' => 'MMK', 'timezone' => 'Asia/Yangon', 'status' => 'active']);
        FoundationMasterRecord::query()->create(['organization_id' => $other->id, 'type' => 'banks', 'code' => 'SECRET', 'name_en' => 'Secret Bank', 'sort_order' => 0, 'status' => 'active']);

        $this->actingFor()->getJson('/api/v1/master-data/foundation-masters/banks?search=AYA')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'AYA');
        $export = $this->actingFor()->get('/api/v1/master-data/foundation-masters/banks/export')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('AYA Bank', $export->streamedContent());
        $this->assertStringNotContainsString('Secret Bank', $export->streamedContent());
    }

    private function createRecord(string $type, string $code, string $name)
    {
        return $this->actingFor()->postJson("/api/v1/master-data/foundation-masters/{$type}", $this->payload($code, $name))->assertCreated();
    }

    private function payload(string $code, string $name): array
    {
        return ['code' => $code, 'name_en' => $name, 'name_my' => null, 'classification' => null, 'branch_public_id' => null, 'area_public_id' => null, 'way_public_id' => null, 'price_book_public_id' => null, 'parent_public_id' => null, 'phone' => null, 'email' => null, 'address' => null, 'registration_number' => null, 'metadata' => null, 'sort_order' => 0, 'status' => 'active'];
    }

    private function organization(): Organization
    {
        return Organization::query()->where('code', 'VALLEY')->firstOrFail();
    }

    private function actingFor(): static
    {
        return $this->withHeader('X-Organization-ID', $this->organization()->public_id);
    }
}
