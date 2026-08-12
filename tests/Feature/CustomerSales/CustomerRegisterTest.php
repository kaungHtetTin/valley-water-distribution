<?php

namespace Tests\Feature\CustomerSales;

use App\Models\Area;
use App\Models\AuditEvent;
use App\Models\ClientAccount;
use App\Models\Organization;
use App\Models\OutletWayAssignment;
use App\Models\Way;
use App\Models\WayVersion;
use Database\Seeders\PhaseOneReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerRegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('platform.features.customer_sales', true);
        $this->seed(PhaseOneReferenceSeeder::class);
        $this->createWay('TGI-NORTH', 'TGI');
        $this->createWay('ATY-CENTRAL', 'ATY');
    }

    public function test_customer_onboarding_creates_account_shop_contact_address_cod_policy_way_history_and_audit(): void
    {
        $response = $this->postCustomer($this->payload(['code' => ' shop-001 ']))
            ->assertCreated()
            ->assertJsonPath('data.code', 'SHOP-001')
            ->assertJsonPath('data.primary_outlet.code', 'SHOP-001-TGI')
            ->assertJsonPath('data.primary_contact.phone', '09 420 123 456')
            ->assertJsonPath('data.primary_address.area.code', 'TGI')
            ->assertJsonPath('data.way_membership.way.code', 'TGI-NORTH')
            ->assertJsonPath('data.settlement_policy', 'COD_CASH')
            ->assertJsonPath('data.credit_hold', false);

        $this->assertDatabaseHas('client_accounts', ['public_id' => $response->json('data.id'), 'lifecycle_status' => 'pending_verification', 'settlement_policy' => 'COD_CASH']);
        $this->assertDatabaseCount('client_outlets', 1);
        $this->assertDatabaseCount('client_contacts', 1);
        $this->assertDatabaseCount('client_outlet_addresses', 1);
        $this->assertDatabaseHas('outlet_way_assignments', ['effective_from' => '2026-08-12 00:00:00', 'status' => 'active']);
        $this->assertDatabaseHas('audit_events', ['action' => 'customer.account.created', 'entity_public_id' => $response->json('data.id')]);
    }

    public function test_customer_search_is_organization_scoped_and_supports_phone_and_way_filters(): void
    {
        $created = $this->postCustomer($this->payload())->assertCreated();
        $way = Way::query()->where('code', 'TGI-NORTH')->firstOrFail();

        $this->getCustomers('?search=420123456&way='.$way->public_id)
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $created->json('data.id'));

        $other = Organization::query()->create(['code' => 'OTHER', 'name' => 'Other', 'default_locale' => 'en', 'currency' => 'MMK', 'timezone' => 'Asia/Yangon', 'status' => 'active']);
        ClientAccount::query()->create(['organization_id' => $other->id, 'code' => 'OTHER-001', 'name_en' => 'Other Customer', 'preferred_language' => 'en', 'settlement_policy' => 'COD_CASH', 'lifecycle_status' => 'active']);
        $this->getCustomers()->assertJsonMissing(['code' => 'OTHER-001']);
    }

    public function test_duplicate_candidates_are_reported_without_discarding_the_provisional_record(): void
    {
        $this->postCustomer($this->payload())->assertCreated();
        $this->postCustomer($this->payload(['code' => 'SHOP-002', 'outlet' => ['code' => 'SHOP-002-TGI', 'name_en' => 'Second outlet', 'name_my' => null]]))
            ->assertCreated()->assertJsonCount(1, 'meta.duplicate_matches')->assertJsonPath('meta.duplicate_matches.0.code', 'SHOP-001');
        $this->assertDatabaseCount('client_accounts', 2);
    }

    public function test_way_switch_preserves_segments_and_stale_writes_are_rejected(): void
    {
        $created = $this->postCustomer($this->payload())->assertCreated();
        $customer = $created->json('data.id');
        $aty = Area::query()->where('code', 'ATY')->firstOrFail();
        $atyWay = Way::query()->where('code', 'ATY-CENTRAL')->firstOrFail();
        $update = $this->payload([
            'version' => 1,
            'address' => [...$this->payload()['address'], 'area_id' => $aty->public_id, 'township' => 'Aye Thar Yar'],
            'way_id' => $atyWay->public_id,
            'way_effective_from' => '2026-09-01',
            'change_reason' => 'Shop moved to a new service territory',
        ]);

        $this->putCustomer($customer, $update)->assertOk()->assertJsonPath('data.version', 2)->assertJsonPath('data.way_membership.way.code', 'ATY-CENTRAL');
        $this->assertDatabaseHas('outlet_way_assignments', ['effective_to' => '2026-08-31 00:00:00', 'status' => 'superseded']);
        $this->assertDatabaseHas('outlet_way_assignments', ['way_id' => $atyWay->id, 'effective_from' => '2026-09-01 00:00:00', 'status' => 'active']);

        $this->putCustomer($customer, $update)->assertConflict()->assertJsonPath('code', 'stale_version');
    }

    public function test_way_must_match_address_area_and_closure_requires_reason(): void
    {
        $atyWay = Way::query()->where('code', 'ATY-CENTRAL')->firstOrFail();
        $this->postCustomer($this->payload(['way_id' => $atyWay->public_id]))->assertConflict()->assertJsonPath('code', 'outlet_way_area_mismatch');
        $created = $this->postCustomer($this->payload())->assertCreated();
        $customer = $created->json('data.id');
        $this->withOrganization()->patchJson("/api/v1/customer-sales/customers/{$customer}/archive", ['version' => 1])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->withOrganization()->patchJson("/api/v1/customer-sales/customers/{$customer}/archive", ['version' => 1, 'reason' => 'Customer permanently stopped trading'])->assertOk()->assertJsonPath('data.lifecycle_status', 'closed');
        $this->assertSame('Customer permanently stopped trading', AuditEvent::query()->where('action', 'customer.account.closed')->firstOrFail()->reason);
        $this->assertSame('closed', OutletWayAssignment::query()->latest('id')->firstOrFail()->status);
    }

    private function payload(array $overrides = []): array
    {
        $area = Area::query()->where('code', 'TGI')->firstOrFail();
        $way = Way::query()->where('code', 'TGI-NORTH')->firstOrFail();

        return [
            'code' => 'SHOP-001', 'name_en' => 'Golden Lake Store', 'name_my' => null, 'legal_name' => null, 'searchable_alias' => 'Golden Lake', 'category' => 'Retailer',
            'preferred_language' => 'my-MM', 'acquisition_source' => 'Sales visit', 'lifecycle_status' => 'pending_verification', 'price_book_id' => null, 'acquiring_sales_profile_id' => null,
            'outlet' => ['code' => 'SHOP-001-TGI', 'name_en' => 'Golden Lake Store - Taunggyi', 'name_my' => null],
            'contact' => ['name' => 'Daw Mya', 'phone' => '09 420 123 456', 'email' => null],
            'address' => ['area_id' => $area->public_id, 'label' => 'Primary delivery', 'township' => 'Taunggyi', 'ward_village' => 'Kan Shae', 'street_address' => 'No. 12, Bogyoke Road', 'landmark' => 'Near market', 'delivery_note' => null, 'latitude' => 20.7891, 'longitude' => 97.0378, 'service_window_start' => '09:00', 'service_window_end' => '16:00'],
            'way_id' => $way->public_id, 'way_effective_from' => '2026-08-12', 'change_reason' => null,
            ...$overrides,
        ];
    }

    private function createWay(string $code, string $areaCode): void
    {
        $organization = $this->organization();
        $area = Area::query()->where('organization_id', $organization->id)->where('code', $areaCode)->firstOrFail();
        $way = Way::query()->create(['organization_id' => $organization->id, 'code' => $code, 'name_en' => str_replace('-', ' ', $code), 'status' => 'active']);
        WayVersion::query()->create(['organization_id' => $organization->id, 'way_id' => $way->id, 'area_id' => $area->id, 'version' => 1, 'service_days' => ['mon'], 'effective_from' => '2026-01-01', 'status' => 'active']);
    }

    private function organization(): Organization
    {
        return Organization::query()->where('code', 'VALLEY')->firstOrFail();
    }

    private function withOrganization()
    {
        return $this->withHeader('X-Organization-ID', $this->organization()->public_id);
    }

    private function postCustomer(array $payload)
    {
        return $this->withOrganization()->postJson('/api/v1/customer-sales/customers', $payload);
    }

    private function putCustomer(string $id, array $payload)
    {
        return $this->withOrganization()->putJson("/api/v1/customer-sales/customers/{$id}", $payload);
    }

    private function getCustomers(string $query = '')
    {
        return $this->withOrganization()->getJson('/api/v1/customer-sales/customers'.$query);
    }
}
