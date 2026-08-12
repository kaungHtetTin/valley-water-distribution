<?php

namespace App\Domain\MasterData;

use App\Domain\MasterData\Exceptions\MasterDataConflictException;
use App\Models\Area;
use App\Models\AuditEvent;
use App\Models\Warehouse;
use App\Models\Way;
use App\Models\WayVersion;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class WayService
{
    public function paginate(int $organizationId, array $filters): LengthAwarePaginator
    {
        $sort = in_array($filters['sort'] ?? null, ['code', 'name_en', 'name_my', 'status', 'updated_at'], true) ? $filters['sort'] : 'code';
        $direction = ($filters['direction'] ?? null) === 'desc' ? 'desc' : 'asc';

        return Way::query()
            ->with(['currentVersion.area:id,public_id,code,name_en,name_my', 'currentVersion.defaultWarehouse:id,public_id,code,name_en,name_my'])
            ->where('organization_id', $organizationId)
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $term = '%'.trim($search).'%';
                $query->where(fn ($query) => $query->where('code', 'like', $term)->orWhere('name_en', 'like', $term)->orWhere('name_my', 'like', $term));
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['area'] ?? null, fn ($query, string $area) => $query->whereHas('currentVersion.area', fn ($query) => $query->where('public_id', $area)))
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate(min(max((int) ($filters['per_page'] ?? 20), 5), 100))
            ->withQueryString();
    }

    public function create(int $organizationId, array $attributes, array $auditContext): Way
    {
        return DB::transaction(function () use ($organizationId, $attributes, $auditContext): Way {
            [$areaId, $warehouseId] = $this->resolveReferences($organizationId, $attributes);
            $way = Way::query()->create([
                'organization_id' => $organizationId,
                'code' => $attributes['code'],
                'name_en' => $attributes['name_en'],
                'name_my' => $attributes['name_my'] ?? null,
                'description' => $attributes['description'] ?? null,
                'status' => $attributes['status'],
            ]);
            $version = $this->createVersion($way, $areaId, $warehouseId, $attributes, 1);
            $this->recordAudit('master_data.way.created', $way, null, $this->snapshot($way, $version), $auditContext);

            return $this->load($way);
        });
    }

    public function update(int $organizationId, string $publicId, array $attributes, array $auditContext): Way
    {
        return DB::transaction(function () use ($organizationId, $publicId, $attributes, $auditContext): Way {
            $way = Way::query()->where('organization_id', $organizationId)->where('public_id', $publicId)->lockForUpdate()->firstOrFail();
            $this->assertVersion($way, (int) $attributes['version']);
            $current = WayVersion::query()->where('way_id', $way->id)->orderByDesc('version')->lockForUpdate()->firstOrFail();
            [$areaId, $warehouseId] = $this->resolveReferences($organizationId, $attributes);
            $before = $this->snapshot($way, $current);
            $policyChanged = $this->policyChanged($current, $areaId, $warehouseId, $attributes);

            if ($policyChanged) {
                $effectiveFrom = CarbonImmutable::parse($attributes['effective_from'])->startOfDay();
                if ($effectiveFrom->lessThanOrEqualTo($current->effective_from)) {
                    throw new MasterDataConflictException('A Way policy revision must start after the current effective version.', 'way_version_date_conflict');
                }
                $current->update(['effective_to' => $effectiveFrom->subDay()->toDateString(), 'status' => 'superseded']);
                $current = $this->createVersion($way, $areaId, $warehouseId, $attributes, $current->version + 1);
            }

            $way->update([
                'code' => $attributes['code'],
                'name_en' => $attributes['name_en'],
                'name_my' => $attributes['name_my'] ?? null,
                'description' => $attributes['description'] ?? null,
                'status' => $attributes['status'],
                'lock_version' => $way->lock_version + 1,
            ]);
            $auditContext['reason'] = $attributes['change_reason'];
            $this->recordAudit('master_data.way.updated', $way, $before, $this->snapshot($way, $current), $auditContext);

            return $this->load($way);
        });
    }

    public function archive(int $organizationId, string $publicId, int $version, string $reason, array $auditContext): Way
    {
        return DB::transaction(function () use ($organizationId, $publicId, $version, $reason, $auditContext): Way {
            $way = Way::query()->where('organization_id', $organizationId)->where('public_id', $publicId)->lockForUpdate()->firstOrFail();
            $this->assertVersion($way, $version);
            if ($way->routeTemplates()->where('route_templates.status', '!=', 'archived')->exists()) {
                throw new MasterDataConflictException('Remove this Way from active Route Templates first.', 'way_has_route_templates');
            }
            $current = WayVersion::query()->where('way_id', $way->id)->orderByDesc('version')->lockForUpdate()->firstOrFail();
            $before = $this->snapshot($way, $current);
            $businessToday = CarbonImmutable::today(config('platform.business_timezone'));
            $endDate = $businessToday->greaterThan($current->effective_from) ? $businessToday : CarbonImmutable::parse($current->effective_from);
            $current->update(['effective_to' => $endDate->toDateString(), 'status' => 'archived', 'change_reason' => $reason]);
            $way->update(['status' => 'archived', 'lock_version' => $way->lock_version + 1]);
            $auditContext['reason'] = $reason;
            $this->recordAudit('master_data.way.archived', $way, $before, $this->snapshot($way, $current), $auditContext);

            return $this->load($way);
        });
    }

    public function options(int $organizationId): array
    {
        return [
            'areas' => Area::query()->where('organization_id', $organizationId)->where('status', 'active')->orderBy('sort_order')->get(['public_id', 'code', 'name_en', 'name_my']),
            'warehouses' => Warehouse::query()->where('organization_id', $organizationId)->where('status', 'active')->orderBy('code')->get(['public_id', 'code', 'name_en', 'name_my']),
        ];
    }

    private function resolveReferences(int $organizationId, array $attributes): array
    {
        $areaId = Area::query()->where('organization_id', $organizationId)->where('public_id', $attributes['area_public_id'])->where('status', 'active')->value('id');
        abort_unless($areaId, 404);
        $warehouseId = null;
        if ($attributes['default_warehouse_public_id'] ?? null) {
            $warehouseId = Warehouse::query()->where('organization_id', $organizationId)->where('public_id', $attributes['default_warehouse_public_id'])->where('status', 'active')->value('id');
            abort_unless($warehouseId, 404);
        }

        return [(int) $areaId, $warehouseId ? (int) $warehouseId : null];
    }

    private function createVersion(Way $way, int $areaId, ?int $warehouseId, array $attributes, int $version): WayVersion
    {
        return WayVersion::query()->create([
            'organization_id' => $way->organization_id,
            'way_id' => $way->id,
            'area_id' => $areaId,
            'default_warehouse_id' => $warehouseId,
            'version' => $version,
            'boundary_description' => $attributes['boundary_description'] ?? null,
            'service_days' => $this->normalizedDays($attributes['service_days']),
            'delivery_window_start' => $attributes['delivery_window_start'] ?? null,
            'delivery_window_end' => $attributes['delivery_window_end'] ?? null,
            'effective_from' => $attributes['effective_from'],
            'change_reason' => $attributes['change_reason'] ?? null,
            'status' => 'active',
        ]);
    }

    private function policyChanged(WayVersion $current, int $areaId, ?int $warehouseId, array $attributes): bool
    {
        return $current->area_id !== $areaId
            || $current->default_warehouse_id !== $warehouseId
            || ($current->boundary_description ?? '') !== ($attributes['boundary_description'] ?? '')
            || $this->normalizedDays($current->service_days) !== $this->normalizedDays($attributes['service_days'])
            || substr((string) $current->delivery_window_start, 0, 5) !== ($attributes['delivery_window_start'] ?? '')
            || substr((string) $current->delivery_window_end, 0, 5) !== ($attributes['delivery_window_end'] ?? '');
    }

    private function normalizedDays(array $days): array
    {
        $order = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

        return array_values(array_intersect($order, array_unique($days)));
    }

    private function load(Way $way): Way
    {
        return $way->load(['currentVersion.area:id,public_id,code,name_en,name_my', 'currentVersion.defaultWarehouse:id,public_id,code,name_en,name_my']);
    }

    private function assertVersion(Way $way, int $version): void
    {
        if ($way->lock_version !== $version) {
            throw new MasterDataConflictException('This Way changed after it was opened. Refresh and review the latest values.');
        }
    }

    private function snapshot(Way $way, WayVersion $version): array
    {
        return ['way' => $way->only(['public_id', 'code', 'name_en', 'name_my', 'description', 'lock_version', 'status']), 'policy' => $version->only(['public_id', 'area_id', 'default_warehouse_id', 'version', 'boundary_description', 'service_days', 'delivery_window_start', 'delivery_window_end', 'effective_from', 'effective_to', 'status'])];
    }

    private function recordAudit(string $action, Way $way, ?array $before, array $after, array $context): void
    {
        AuditEvent::query()->create([
            'organization_id' => $way->organization_id,
            'actor_user_id' => $context['actor_user_id'] ?? null,
            'action' => $action,
            'entity_type' => Way::class,
            'entity_public_id' => $way->public_id,
            'before_state' => $before,
            'after_state' => $after,
            'reason' => $context['reason'] ?? null,
            'correlation_id' => $context['correlation_id'] ?? null,
            'ip_address' => $context['ip_address'] ?? null,
        ]);
    }
}
