<?php

namespace Tests\Feature\MasterData;

use App\Models\Branch;
use App\Models\Organization;
use Database\Seeders\PhaseOneReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationControlMasterDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('platform.features.master_data', true);
        $this->seed(PhaseOneReferenceSeeder::class);
    }

    public function test_dashboard_exposes_only_selected_organization_configuration(): void
    {
        $organization = $this->organization();
        $this->actingFor($organization)->getJson('/api/v1/master-data/organization-controls')
            ->assertOk()
            ->assertJsonPath('data.organization.code', 'VALLEY')
            ->assertJsonPath('data.organization.version', 1)
            ->assertJsonCount(1, 'data.branches')
            ->assertJsonCount(0, 'data.calendars')
            ->assertJsonCount(0, 'data.periods')
            ->assertJsonCount(0, 'data.sequences');
    }

    public function test_company_settings_update_is_audited_and_rejects_a_stale_version(): void
    {
        $organization = $this->organization();
        $payload = ['code' => ' valley ', 'name' => 'Valley Water Company', 'legal_name' => 'Valley Water Distribution', 'registration_number' => 'REG-001', 'tax_identifier' => 'TAX-001', 'phone' => '081-100000', 'email' => 'office@example.test', 'address' => 'Taunggyi', 'default_locale' => 'my-MM', 'document_locale' => 'my-MM', 'currency' => ' mmk ', 'inventory_valuation_method' => 'weighted_average', 'timezone' => 'Asia/Yangon', 'version' => 1];

        $this->actingFor($organization)->putJson('/api/v1/master-data/organization-controls/organization', $payload)
            ->assertOk()->assertJsonPath('data.code', 'VALLEY')->assertJsonPath('data.currency', 'MMK')->assertJsonPath('data.inventory_valuation_method', 'weighted_average')->assertJsonPath('data.version', 2);
        $this->assertDatabaseHas('audit_events', ['action' => 'master_data.organization.updated', 'entity_public_id' => $organization->public_id]);
        $this->actingFor($organization)->putJson('/api/v1/master-data/organization-controls/organization', $payload)
            ->assertConflict()->assertJsonPath('code', 'stale_version');
    }

    public function test_business_calendar_saves_branch_scope_and_day_overrides(): void
    {
        $organization = $this->organization();
        $created = $this->actingFor($organization)->postJson('/api/v1/master-data/organization-controls/calendars', $this->calendarPayload())
            ->assertCreated()->assertJsonPath('data.code', 'TGI-OPS')->assertJsonPath('data.branch.code', 'TGI')
            ->assertJsonPath('data.weekend_days.0', 'sun')->assertJsonPath('data.dates.0.day_type', 'holiday');

        $calendarId = $created->json('data.id');
        $this->assertDatabaseHas('business_calendar_dates', ['day_type' => 'holiday', 'name_en' => 'New Year Day']);
        $this->assertDatabaseHas('audit_events', ['action' => 'master_data.business_calendar.created', 'entity_public_id' => $calendarId]);
        $this->actingFor($organization)->putJson("/api/v1/master-data/organization-controls/calendars/{$calendarId}", [...$this->calendarPayload(), 'version' => 1, 'name_en' => 'Taunggyi Service Calendar'])
            ->assertOk()->assertJsonPath('data.version', 2);
        $this->actingFor($organization)->putJson("/api/v1/master-data/organization-controls/calendars/{$calendarId}", [...$this->calendarPayload(), 'version' => 1])
            ->assertConflict()->assertJsonPath('code', 'stale_version');
    }

    public function test_calendar_exceptions_must_fall_inside_the_effective_range(): void
    {
        $organization = $this->organization();
        $payload = $this->calendarPayload();
        $payload['dates'][0]['date'] = '2027-01-01';

        $this->actingFor($organization)->postJson('/api/v1/master-data/organization-controls/calendars', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('dates.0.date');
        $this->assertDatabaseCount('business_calendars', 0);
    }

    public function test_fiscal_period_overlap_is_rejected(): void
    {
        $organization = $this->organization();
        $this->actingFor($organization)->postJson('/api/v1/master-data/organization-controls/periods', ['code' => 'FY26', 'name' => 'Fiscal Year 2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'status' => 'open'])
            ->assertCreated()->assertJsonPath('data.status', 'open');
        $this->actingFor($organization)->postJson('/api/v1/master-data/organization-controls/periods', ['code' => 'FY26-Q4', 'name' => 'Overlapping Quarter', 'starts_on' => '2026-10-01', 'ends_on' => '2026-12-31', 'status' => 'open'])
            ->assertConflict()->assertJsonPath('code', 'fiscal_period_overlap');
        $this->assertDatabaseCount('fiscal_periods', 1);
    }

    public function test_document_sequence_scope_is_unique_and_archive_is_audited(): void
    {
        $organization = $this->organization();
        $payload = $this->sequencePayload();
        $created = $this->actingFor($organization)->postJson('/api/v1/master-data/organization-controls/sequences', $payload)
            ->assertCreated()->assertJsonPath('data.document_type', 'ORDER')->assertJsonPath('data.preview', 'TGI-000001');
        $sequenceId = $created->json('data.id');

        $this->actingFor($organization)->postJson('/api/v1/master-data/organization-controls/sequences', $payload)
            ->assertConflict()->assertJsonPath('code', 'duplicate_document_sequence');
        $this->actingFor($organization)->patchJson("/api/v1/master-data/organization-controls/sequences/{$sequenceId}/archive", ['version' => 1, 'reason' => 'Number format superseded'])
            ->assertOk()->assertJsonPath('data.status', 'archived')->assertJsonPath('data.version', 2);
        $this->assertDatabaseHas('audit_events', ['action' => 'master_data.document_sequence.archived', 'entity_public_id' => $sequenceId, 'reason' => 'Number format superseded']);
    }

    private function organization(): Organization
    {
        return Organization::query()->where('code', 'VALLEY')->firstOrFail();
    }

    private function actingFor(Organization $organization): static
    {
        return $this->withHeader('X-Organization-ID', $organization->public_id);
    }

    private function calendarPayload(): array
    {
        $organization = $this->organization();
        $branch = Branch::query()->where('organization_id', $organization->id)->where('code', 'TGI')->firstOrFail();

        return ['branch_public_id' => $branch->public_id, 'code' => ' tgi-ops ', 'name_en' => 'Taunggyi Operations Calendar', 'name_my' => null, 'weekend_days' => ['sun'], 'effective_from' => '2026-01-01', 'effective_to' => '2026-12-31', 'status' => 'active', 'dates' => [['date' => '2026-01-01', 'day_type' => 'holiday', 'name_en' => 'New Year Day', 'name_my' => null], ['date' => '2026-04-18', 'day_type' => 'working_override', 'name_en' => 'Recovery service day', 'name_my' => null]]];
    }

    private function sequencePayload(): array
    {
        $organization = $this->organization();
        $branch = Branch::query()->where('organization_id', $organization->id)->where('code', 'TGI')->firstOrFail();

        return ['branch_public_id' => $branch->public_id, 'document_type' => ' order ', 'name' => 'Taunggyi Order Number', 'prefix' => 'TGI-', 'suffix' => null, 'padding' => 6, 'next_number' => 1, 'reset_policy' => 'yearly', 'status' => 'active'];
    }
}
