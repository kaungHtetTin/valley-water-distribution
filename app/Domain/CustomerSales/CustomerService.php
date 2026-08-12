<?php

namespace App\Domain\CustomerSales;

use App\Domain\CustomerSales\Exceptions\CustomerConflictException;
use App\Models\Area;
use App\Models\AuditEvent;
use App\Models\ClientAccount;
use App\Models\ClientContact;
use App\Models\ClientOutlet;
use App\Models\ClientOutletAddress;
use App\Models\FoundationMasterRecord;
use App\Models\OutletWayAssignment;
use App\Models\PriceBook;
use App\Models\Way;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    public function paginate(int $organizationId, array $filters): LengthAwarePaginator
    {
        $sort = in_array($filters['sort'] ?? null, ['code', 'name_en', 'lifecycle_status', 'updated_at'], true) ? $filters['sort'] : 'code';
        $direction = ($filters['direction'] ?? null) === 'desc' ? 'desc' : 'asc';

        return ClientAccount::query()
            ->with($this->relations())
            ->where('organization_id', $organizationId)
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $term = '%'.trim($search).'%';
                $digits = $this->normalizePhone($search);
                $query->where(function ($query) use ($term, $digits): void {
                    $query->where('code', 'like', $term)->orWhere('name_en', 'like', $term)->orWhere('name_my', 'like', $term)->orWhere('searchable_alias', 'like', $term)
                        ->orWhereHas('outlets', fn ($query) => $query->where('code', 'like', $term)->orWhere('name_en', 'like', $term)->orWhere('name_my', 'like', $term));
                    if ($digits !== '') {
                        $query->orWhereHas('contacts', fn ($query) => $query->where('phone_normalized', 'like', '%'.$digits.'%'));
                    }
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('lifecycle_status', $status))
            ->when($filters['way'] ?? null, fn ($query, string $way) => $query->whereHas('outlets.currentWayAssignment.way', fn ($query) => $query->where('public_id', $way)))
            ->orderBy($sort, $direction)->orderBy('id')
            ->paginate(min(max((int) ($filters['per_page'] ?? 20), 5), 100))->withQueryString();
    }

    public function create(int $organizationId, array $attributes, array $auditContext): ClientAccount
    {
        return DB::transaction(function () use ($organizationId, $attributes, $auditContext): ClientAccount {
            $refs = $this->resolveReferences($organizationId, $attributes);
            $account = ClientAccount::query()->create($this->accountValues($organizationId, $attributes, $refs));
            $outlet = ClientOutlet::query()->create([
                'organization_id' => $organizationId, 'client_account_id' => $account->id, 'code' => $attributes['outlet']['code'],
                'name_en' => $attributes['outlet']['name_en'], 'name_my' => $attributes['outlet']['name_my'] ?? null, 'is_primary' => true, 'status' => 'active',
            ]);
            $this->saveContact($organizationId, $account, $outlet, $attributes['contact']);
            $this->saveAddress($organizationId, $outlet, $refs['area_id'], $attributes['address']);
            $this->createWayAssignment($organizationId, $outlet, $refs['way_id'], $attributes['way_effective_from'], $attributes['change_reason'] ?? 'Initial outlet assignment');
            $loaded = $this->load($account);
            $this->recordAudit('customer.account.created', $account, null, $this->snapshot($loaded), $auditContext);

            return $loaded;
        });
    }

    public function update(int $organizationId, string $publicId, array $attributes, array $auditContext): ClientAccount
    {
        return DB::transaction(function () use ($organizationId, $publicId, $attributes, $auditContext): ClientAccount {
            $account = ClientAccount::query()->where('organization_id', $organizationId)->where('public_id', $publicId)->lockForUpdate()->firstOrFail();
            if ($account->lock_version !== (int) $attributes['version']) {
                throw new CustomerConflictException('This Customer changed after it was opened. Refresh and review the latest values.');
            }
            $before = $this->snapshot($this->load($account));
            $refs = $this->resolveReferences($organizationId, $attributes);
            $account->update([...$this->accountValues($organizationId, $attributes, $refs), 'lock_version' => $account->lock_version + 1]);
            $outlet = ClientOutlet::query()->where('organization_id', $organizationId)->where('client_account_id', $account->id)->where('is_primary', true)->lockForUpdate()->firstOrFail();
            $outlet->update(['code' => $attributes['outlet']['code'], 'name_en' => $attributes['outlet']['name_en'], 'name_my' => $attributes['outlet']['name_my'] ?? null, 'lock_version' => $outlet->lock_version + 1]);
            $contact = ClientContact::query()->where('organization_id', $organizationId)->where('client_account_id', $account->id)->where('is_primary_ordering', true)->lockForUpdate()->firstOrFail();
            $contact->update($this->contactValues($attributes['contact']));
            $address = ClientOutletAddress::query()->where('organization_id', $organizationId)->where('client_outlet_id', $outlet->id)->where('is_primary', true)->lockForUpdate()->firstOrFail();
            $address->update($this->addressValues($refs['area_id'], $attributes['address']));
            $this->reviseWayAssignment($organizationId, $outlet, $refs['way_id'], $attributes['way_effective_from'], $attributes['change_reason']);
            $auditContext['reason'] = $attributes['change_reason'];
            $loaded = $this->load($account);
            $this->recordAudit('customer.account.updated', $account, $before, $this->snapshot($loaded), $auditContext);

            return $loaded;
        });
    }

    public function archive(int $organizationId, string $publicId, int $version, string $reason, array $auditContext): ClientAccount
    {
        return DB::transaction(function () use ($organizationId, $publicId, $version, $reason, $auditContext): ClientAccount {
            $account = ClientAccount::query()->where('organization_id', $organizationId)->where('public_id', $publicId)->lockForUpdate()->firstOrFail();
            if ($account->lock_version !== $version) {
                throw new CustomerConflictException('This Customer changed after it was opened. Refresh and review the latest values.');
            }
            $before = $this->snapshot($this->load($account));
            $account->update(['lifecycle_status' => 'closed', 'lock_version' => $account->lock_version + 1]);
            ClientOutlet::query()->where('client_account_id', $account->id)->update(['status' => 'archived']);
            $today = CarbonImmutable::today(config('platform.business_timezone'));
            OutletWayAssignment::query()->where('organization_id', $organizationId)->whereHas('outlet', fn ($query) => $query->where('client_account_id', $account->id))->where('status', 'active')->whereNull('effective_to')->update(['effective_to' => $today->toDateString(), 'status' => 'closed', 'change_reason' => $reason]);
            $auditContext['reason'] = $reason;
            $loaded = $this->load($account);
            $this->recordAudit('customer.account.closed', $account, $before, $this->snapshot($loaded), $auditContext);

            return $loaded;
        });
    }

    public function options(int $organizationId): array
    {
        return [
            'areas' => Area::query()->where('organization_id', $organizationId)->where('status', 'active')->orderBy('sort_order')->get(['public_id', 'code', 'name_en', 'name_my']),
            'ways' => Way::query()->with('currentVersion.area:id,public_id')->where('organization_id', $organizationId)->where('status', 'active')->orderBy('code')->get(['id', 'public_id', 'code', 'name_en', 'name_my'])->map(fn (Way $way) => ['id' => $way->public_id, 'code' => $way->code, 'name_en' => $way->name_en, 'name_my' => $way->name_my, 'area_id' => $way->currentVersion?->area?->public_id]),
            'price_books' => PriceBook::query()->where('organization_id', $organizationId)->where('status', 'active')->orderBy('code')->get(['public_id', 'code', 'name_en', 'name_my']),
            'sales_profiles' => FoundationMasterRecord::query()->where('organization_id', $organizationId)->where('type', 'sales-profiles')->where('status', 'active')->orderBy('code')->get(['public_id', 'code', 'name_en', 'name_my']),
        ];
    }

    public function duplicateMatches(int $organizationId, string $phone, string $name, ?int $exceptAccountId = null): array
    {
        $normalized = $this->normalizePhone($phone);

        return ClientAccount::query()->with('primaryContact')->where('organization_id', $organizationId)->when($exceptAccountId, fn ($query) => $query->whereKeyNot($exceptAccountId))
            ->where(fn ($query) => $query->whereRaw('LOWER(name_en) = ?', [mb_strtolower(trim($name))])->orWhereHas('contacts', fn ($query) => $query->where('phone_normalized', $normalized)))
            ->limit(5)->get()->map(fn (ClientAccount $account) => ['id' => $account->public_id, 'code' => $account->code, 'name' => $account->name_en, 'phone' => $account->primaryContact?->phone])->all();
    }

    private function resolveReferences(int $organizationId, array $attributes): array
    {
        $area = Area::query()->where('organization_id', $organizationId)->where('public_id', $attributes['address']['area_id'])->where('status', 'active')->firstOrFail();
        $way = Way::query()->with('currentVersion')->where('organization_id', $organizationId)->where('public_id', $attributes['way_id'])->where('status', 'active')->firstOrFail();
        if ($way->currentVersion?->area_id !== $area->id) {
            throw new CustomerConflictException('The selected Way does not serve the selected Area.', 'outlet_way_area_mismatch');
        }
        $priceBookId = isset($attributes['price_book_id']) ? PriceBook::query()->where('organization_id', $organizationId)->where('public_id', $attributes['price_book_id'])->where('status', 'active')->value('id') : null;
        $salesProfileId = isset($attributes['acquiring_sales_profile_id']) ? FoundationMasterRecord::query()->where('organization_id', $organizationId)->where('public_id', $attributes['acquiring_sales_profile_id'])->where('type', 'sales-profiles')->where('status', 'active')->value('id') : null;

        return ['area_id' => $area->id, 'way_id' => $way->id, 'price_book_id' => $priceBookId, 'sales_profile_id' => $salesProfileId];
    }

    private function accountValues(int $organizationId, array $a, array $refs): array
    {
        return ['organization_id' => $organizationId, 'price_book_id' => $refs['price_book_id'], 'acquiring_sales_profile_id' => $refs['sales_profile_id'], 'code' => $a['code'], 'name_en' => $a['name_en'], 'name_my' => $a['name_my'] ?? null, 'legal_name' => $a['legal_name'] ?? null, 'searchable_alias' => $a['searchable_alias'] ?? null, 'category' => $a['category'] ?? null, 'preferred_language' => $a['preferred_language'], 'acquisition_source' => $a['acquisition_source'] ?? null, 'settlement_policy' => 'COD_CASH', 'lifecycle_status' => $a['lifecycle_status'], 'credit_hold' => false];
    }

    private function saveContact(int $organizationId, ClientAccount $account, ClientOutlet $outlet, array $a): void
    {
        ClientContact::query()->create(['organization_id' => $organizationId, 'client_account_id' => $account->id, 'client_outlet_id' => $outlet->id, ...$this->contactValues($a), 'is_primary_ordering' => true, 'status' => 'active']);
    }

    private function contactValues(array $a): array
    {
        return ['name' => $a['name'], 'phone' => trim($a['phone']), 'phone_normalized' => $this->normalizePhone($a['phone']), 'email' => $a['email'] ?? null];
    }

    private function saveAddress(int $organizationId, ClientOutlet $outlet, int $areaId, array $a): void
    {
        ClientOutletAddress::query()->create(['organization_id' => $organizationId, 'client_outlet_id' => $outlet->id, ...$this->addressValues($areaId, $a), 'is_primary' => true, 'status' => 'active']);
    }

    private function addressValues(int $areaId, array $a): array
    {
        return ['area_id' => $areaId, 'label' => $a['label'] ?? 'Primary delivery', 'township' => $a['township'] ?? null, 'ward_village' => $a['ward_village'] ?? null, 'street_address' => $a['street_address'], 'landmark' => $a['landmark'] ?? null, 'delivery_note' => $a['delivery_note'] ?? null, 'latitude' => $a['latitude'] ?? null, 'longitude' => $a['longitude'] ?? null, 'service_window_start' => $a['service_window_start'] ?? null, 'service_window_end' => $a['service_window_end'] ?? null];
    }

    private function createWayAssignment(int $organizationId, ClientOutlet $outlet, int $wayId, string $from, ?string $reason): void
    {
        OutletWayAssignment::query()->create(['organization_id' => $organizationId, 'client_outlet_id' => $outlet->id, 'way_id' => $wayId, 'effective_from' => $from, 'role' => 'primary', 'change_reason' => $reason, 'status' => 'active']);
    }

    private function reviseWayAssignment(int $organizationId, ClientOutlet $outlet, int $wayId, string $from, string $reason): void
    {
        $current = OutletWayAssignment::query()->where('organization_id', $organizationId)->where('client_outlet_id', $outlet->id)->where('role', 'primary')->where('status', 'active')->whereNull('effective_to')->lockForUpdate()->latest('effective_from')->first();
        if ($current?->way_id === $wayId) {
            return;
        }
        $effectiveFrom = CarbonImmutable::parse($from)->startOfDay();
        if ($current && $effectiveFrom->lessThanOrEqualTo($current->effective_from)) {
            throw new CustomerConflictException('A new Way membership must start after the current membership.', 'outlet_way_date_conflict');
        }
        if ($current) {
            $current->update(['effective_to' => $effectiveFrom->subDay()->toDateString(), 'status' => 'superseded', 'change_reason' => $reason]);
        }
        $this->createWayAssignment($organizationId, $outlet, $wayId, $effectiveFrom->toDateString(), $reason);
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    private function relations(): array
    {
        return ['priceBook:id,public_id,code,name_en,name_my', 'acquiringSalesProfile:id,public_id,code,name_en,name_my', 'primaryContact', 'primaryOutlet.primaryAddress.area:id,public_id,code,name_en,name_my', 'primaryOutlet.currentWayAssignment.way:id,public_id,code,name_en,name_my'];
    }

    private function load(ClientAccount $account): ClientAccount
    {
        return $account->fresh($this->relations());
    }

    private function snapshot(ClientAccount $a): array
    {
        return ['account' => $a->only(['public_id', 'code', 'name_en', 'name_my', 'category', 'preferred_language', 'settlement_policy', 'lifecycle_status', 'lock_version']), 'outlet' => $a->primaryOutlet?->only(['public_id', 'code', 'name_en', 'name_my', 'status']), 'contact' => $a->primaryContact?->only(['public_id', 'name', 'phone', 'email']), 'address' => $a->primaryOutlet?->primaryAddress?->only(['public_id', 'area_id', 'street_address', 'landmark', 'latitude', 'longitude']), 'way_assignment' => $a->primaryOutlet?->currentWayAssignment?->only(['public_id', 'way_id', 'effective_from', 'effective_to', 'status'])];
    }

    private function recordAudit(string $action, ClientAccount $account, ?array $before, array $after, array $context): void
    {
        AuditEvent::query()->create(['organization_id' => $account->organization_id, 'actor_user_id' => $context['actor_user_id'] ?? null, 'action' => $action, 'entity_type' => ClientAccount::class, 'entity_public_id' => $account->public_id, 'before_state' => $before, 'after_state' => $after, 'reason' => $context['reason'] ?? null, 'correlation_id' => $context['correlation_id'] ?? null, 'ip_address' => $context['ip_address'] ?? null]);
    }
}
