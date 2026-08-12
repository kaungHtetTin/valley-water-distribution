<?php

namespace App\Domain\MasterData;

use App\Domain\MasterData\Exceptions\MasterDataConflictException;
use App\Models\Area;
use App\Models\AuditEvent;
use App\Models\Branch;
use App\Models\Warehouse;
use App\Models\WayVersion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class LocationMasterService
{
    public function paginateBranches(int $organizationId, array $filters): LengthAwarePaginator
    {
        return Branch::query()->withCount(['warehouses' => fn ($query) => $query->where('status', '!=', 'archived')])
            ->where('organization_id', $organizationId)
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $term = '%'.trim($search).'%';
                $query->where(fn ($query) => $query->where('code', 'like', $term)->orWhere('name_en', 'like', $term)->orWhere('name_my', 'like', $term));
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->orderBy('code')->paginate(min(max((int) ($filters['per_page'] ?? 20), 5), 100))->withQueryString();
    }

    public function paginateWarehouses(int $organizationId, array $filters): LengthAwarePaginator
    {
        return Warehouse::query()->with(['branch:id,public_id,code,name_en,name_my', 'area:id,public_id,code,name_en,name_my'])
            ->where('organization_id', $organizationId)
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $term = '%'.trim($search).'%';
                $query->where(fn ($query) => $query->where('code', 'like', $term)->orWhere('name_en', 'like', $term)->orWhere('name_my', 'like', $term)->orWhere('phone', 'like', $term));
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['branch'] ?? null, fn ($query, string $branch) => $query->whereHas('branch', fn ($query) => $query->where('public_id', $branch)))
            ->when($filters['area'] ?? null, fn ($query, string $area) => $query->whereHas('area', fn ($query) => $query->where('public_id', $area)))
            ->orderBy('code')->paginate(min(max((int) ($filters['per_page'] ?? 20), 5), 100))->withQueryString();
    }

    public function createBranch(int $organizationId, array $attributes, array $auditContext): Branch
    {
        return DB::transaction(function () use ($organizationId, $attributes, $auditContext): Branch {
            $branch = Branch::query()->create([...$attributes, 'organization_id' => $organizationId]);
            $this->recordAudit('master_data.branch.created', $branch, null, $this->branchSnapshot($branch), $auditContext);

            return $branch->loadCount(['warehouses' => fn ($query) => $query->where('status', '!=', 'archived')]);
        });
    }

    public function updateBranch(int $organizationId, string $publicId, array $attributes, array $auditContext): Branch
    {
        return DB::transaction(function () use ($organizationId, $publicId, $attributes, $auditContext): Branch {
            $branch = Branch::query()->where('organization_id', $organizationId)->where('public_id', $publicId)->lockForUpdate()->firstOrFail();
            $this->assertVersion($branch, (int) $attributes['version'], 'Branch');
            $before = $this->branchSnapshot($branch);
            unset($attributes['version']);
            $branch->fill([...$attributes, 'lock_version' => $branch->lock_version + 1])->save();
            $this->recordAudit('master_data.branch.updated', $branch, $before, $this->branchSnapshot($branch), $auditContext);

            return $branch->loadCount(['warehouses' => fn ($query) => $query->where('status', '!=', 'archived')]);
        });
    }

    public function archiveBranch(int $organizationId, string $publicId, int $version, string $reason, array $auditContext): Branch
    {
        return DB::transaction(function () use ($organizationId, $publicId, $version, $reason, $auditContext): Branch {
            $branch = Branch::query()->where('organization_id', $organizationId)->where('public_id', $publicId)->lockForUpdate()->firstOrFail();
            $this->assertVersion($branch, $version, 'Branch');
            if ($branch->warehouses()->where('status', '!=', 'archived')->exists()) {
                throw new MasterDataConflictException('Archive every Warehouse in this Branch first.', 'branch_has_warehouses');
            }
            if ($branch->businessCalendars()->where('status', '!=', 'archived')->exists() || $branch->documentSequences()->where('status', '!=', 'archived')->exists() || $branch->cashLocations()->where('status', '!=', 'archived')->exists() || $branch->routeTemplates()->where('status', '!=', 'archived')->exists()) {
                throw new MasterDataConflictException('Archive this Branch’s active calendars, sequences, cash locations, and route templates first.', 'branch_has_active_configuration');
            }
            $before = $this->branchSnapshot($branch);
            $branch->update(['status' => 'archived', 'lock_version' => $branch->lock_version + 1]);
            $auditContext['reason'] = $reason;
            $this->recordAudit('master_data.branch.archived', $branch, $before, $this->branchSnapshot($branch), $auditContext);

            return $branch->loadCount(['warehouses' => fn ($query) => $query->where('status', '!=', 'archived')]);
        });
    }

    public function createWarehouse(int $organizationId, array $attributes, array $auditContext): Warehouse
    {
        return DB::transaction(function () use ($organizationId, $attributes, $auditContext): Warehouse {
            [$branchId, $areaId] = $this->resolveWarehouseReferences($organizationId, $attributes);
            unset($attributes['branch_public_id'], $attributes['area_public_id']);
            $warehouse = Warehouse::query()->create([...$attributes, 'organization_id' => $organizationId, 'branch_id' => $branchId, 'area_id' => $areaId]);
            $this->recordAudit('master_data.warehouse.created', $warehouse, null, $this->warehouseSnapshot($warehouse), $auditContext);

            return $this->loadWarehouse($warehouse);
        });
    }

    public function updateWarehouse(int $organizationId, string $publicId, array $attributes, array $auditContext): Warehouse
    {
        return DB::transaction(function () use ($organizationId, $publicId, $attributes, $auditContext): Warehouse {
            $warehouse = Warehouse::query()->where('organization_id', $organizationId)->where('public_id', $publicId)->lockForUpdate()->firstOrFail();
            $this->assertVersion($warehouse, (int) $attributes['version'], 'Warehouse');
            [$branchId, $areaId] = $this->resolveWarehouseReferences($organizationId, $attributes);
            $before = $this->warehouseSnapshot($warehouse);
            unset($attributes['version'], $attributes['branch_public_id'], $attributes['area_public_id']);
            $warehouse->fill([...$attributes, 'branch_id' => $branchId, 'area_id' => $areaId, 'lock_version' => $warehouse->lock_version + 1])->save();
            $this->recordAudit('master_data.warehouse.updated', $warehouse, $before, $this->warehouseSnapshot($warehouse), $auditContext);

            return $this->loadWarehouse($warehouse);
        });
    }

    public function archiveWarehouse(int $organizationId, string $publicId, int $version, string $reason, array $auditContext): Warehouse
    {
        return DB::transaction(function () use ($organizationId, $publicId, $version, $reason, $auditContext): Warehouse {
            $warehouse = Warehouse::query()->where('organization_id', $organizationId)->where('public_id', $publicId)->lockForUpdate()->firstOrFail();
            $this->assertVersion($warehouse, $version, 'Warehouse');
            $isActiveWayDefault = WayVersion::query()->where('organization_id', $organizationId)->where('default_warehouse_id', $warehouse->id)->where('status', 'active')->whereHas('way', fn ($query) => $query->where('status', 'active'))->exists();
            if ($isActiveWayDefault) {
                throw new MasterDataConflictException('Assign another default Warehouse to active Ways first.', 'warehouse_has_active_ways');
            }
            if ($warehouse->zones()->where('status', '!=', 'archived')->exists() || $warehouse->replenishmentPolicies()->where('status', '!=', 'archived')->exists()) {
                throw new MasterDataConflictException('Archive this Warehouse’s zones and replenishment policies first.', 'warehouse_has_topology');
            }
            if ($warehouse->routeTemplates()->where('status', '!=', 'archived')->exists()) {
                throw new MasterDataConflictException('Archive active Route Templates using this Warehouse first.', 'warehouse_has_route_templates');
            }
            $before = $this->warehouseSnapshot($warehouse);
            $warehouse->update(['status' => 'archived', 'lock_version' => $warehouse->lock_version + 1]);
            $auditContext['reason'] = $reason;
            $this->recordAudit('master_data.warehouse.archived', $warehouse, $before, $this->warehouseSnapshot($warehouse), $auditContext);

            return $this->loadWarehouse($warehouse);
        });
    }

    public function options(int $organizationId): array
    {
        return [
            'branches' => Branch::query()->where('organization_id', $organizationId)->where('status', 'active')->orderBy('code')->get(['public_id', 'code', 'name_en', 'name_my']),
            'areas' => Area::query()->where('organization_id', $organizationId)->where('status', 'active')->orderBy('sort_order')->get(['public_id', 'code', 'name_en', 'name_my']),
        ];
    }

    private function resolveWarehouseReferences(int $organizationId, array $attributes): array
    {
        $branchId = Branch::query()->where('organization_id', $organizationId)->where('public_id', $attributes['branch_public_id'])->where('status', 'active')->value('id');
        abort_unless($branchId, 404);
        $areaId = null;
        if ($attributes['area_public_id'] ?? null) {
            $areaId = Area::query()->where('organization_id', $organizationId)->where('public_id', $attributes['area_public_id'])->where('status', 'active')->value('id');
            abort_unless($areaId, 404);
        }

        return [(int) $branchId, $areaId ? (int) $areaId : null];
    }

    private function loadWarehouse(Warehouse $warehouse): Warehouse
    {
        return $warehouse->load(['branch:id,public_id,code,name_en,name_my', 'area:id,public_id,code,name_en,name_my']);
    }

    private function assertVersion(Model $record, int $version, string $label): void
    {
        if ((int) $record->lock_version !== $version) {
            throw new MasterDataConflictException("This {$label} changed after it was opened. Refresh and review the latest values.");
        }
    }

    private function branchSnapshot(Branch $branch): array
    {
        return Arr::only($branch->fresh()->toArray(), ['public_id', 'code', 'name_en', 'name_my', 'phone', 'address', 'timezone', 'currency', 'business_day_start', 'lock_version', 'status']);
    }

    private function warehouseSnapshot(Warehouse $warehouse): array
    {
        return Arr::only($warehouse->fresh()->toArray(), ['public_id', 'branch_id', 'area_id', 'code', 'name_en', 'name_my', 'kind', 'address', 'contact_name', 'phone', 'latitude', 'longitude', 'order_cutoff_time', 'service_area_note', 'lock_version', 'status']);
    }

    private function recordAudit(string $action, Model $record, ?array $before, array $after, array $context): void
    {
        AuditEvent::query()->create([
            'organization_id' => $record->organization_id, 'actor_user_id' => $context['actor_user_id'] ?? null, 'action' => $action,
            'entity_type' => $record::class, 'entity_public_id' => $record->public_id, 'before_state' => $before, 'after_state' => $after,
            'reason' => $context['reason'] ?? null, 'correlation_id' => $context['correlation_id'] ?? null, 'ip_address' => $context['ip_address'] ?? null,
        ]);
    }
}
