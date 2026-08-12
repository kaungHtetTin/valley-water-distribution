<?php

namespace App\Domain\MasterData;

use App\Domain\MasterData\Exceptions\MasterDataConflictException;
use App\Models\AuditEvent;
use App\Models\Branch;
use App\Models\CashLocation;
use App\Models\Sku;
use App\Models\Warehouse;
use App\Models\WarehouseBin;
use App\Models\WarehouseSkuPolicy;
use App\Models\WarehouseZone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ControlledLocationService
{
    public function dashboard(int $organizationId): array
    {
        return [
            'zones' => WarehouseZone::query()->with('warehouse:id,public_id,code,name_en,name_my')->withCount(['bins' => fn ($query) => $query->where('status', '!=', 'archived')])->where('organization_id', $organizationId)->orderBy('warehouse_id')->orderBy('sort_order')->get(),
            'bins' => WarehouseBin::query()->with(['warehouse:id,public_id,code,name_en,name_my', 'zone:id,public_id,code,name_en,name_my'])->where('organization_id', $organizationId)->orderBy('warehouse_id')->orderBy('sort_order')->get(),
            'replenishment' => WarehouseSkuPolicy::query()->with(['warehouse:id,public_id,code,name_en,name_my', 'sku:id,public_id,base_uom_id,code,name_en,name_my', 'sku.baseUom:id,public_id,code,symbol'])->where('organization_id', $organizationId)->orderBy('warehouse_id')->get(),
            'cash' => CashLocation::query()->with('branch:id,public_id,code,name_en,name_my')->where('organization_id', $organizationId)->orderBy('code')->get(),
            'warehouses' => Warehouse::query()->where('organization_id', $organizationId)->where('status', 'active')->orderBy('code')->get(['public_id', 'code', 'name_en', 'name_my']),
            'skus' => Sku::query()->with('baseUom:id,public_id,code,symbol')->where('organization_id', $organizationId)->where('status', 'active')->orderBy('code')->get(['id', 'public_id', 'base_uom_id', 'code', 'name_en', 'name_my']),
            'branches' => Branch::query()->where('organization_id', $organizationId)->where('status', 'active')->orderBy('code')->get(['public_id', 'code', 'name_en', 'name_my']),
        ];
    }

    public function save(int $organizationId, string $type, ?string $publicId, array $attributes, array $auditContext): Model
    {
        return DB::transaction(function () use ($organizationId, $type, $publicId, $attributes, $auditContext): Model {
            $record = $publicId ? $this->query($type, $organizationId)->where('public_id', $publicId)->lockForUpdate()->firstOrFail() : null;
            if ($record) {
                $this->assertVersion($record, (int) $attributes['version']);
            }
            [$model, $values] = $this->resolveValues($organizationId, $type, $attributes);
            $this->assertUnique($organizationId, $type, $values, $record);
            if ($record) {
                $before = $this->snapshot($record);
                unset($values['version']);
                $record->fill([...$values, 'lock_version' => $record->lock_version + 1])->save();
                $verb = 'updated';
            } else {
                unset($values['version']);
                $record = $model::query()->create([...$values, 'organization_id' => $organizationId]);
                $before = null;
                $verb = 'created';
            }
            $record = $this->load($type, $record);
            $this->audit("master_data.{$this->actionName($type)}.{$verb}", $record, $before, $this->snapshot($record), $auditContext);

            return $record;
        });
    }

    public function archive(int $organizationId, string $type, string $publicId, int $version, string $reason, array $auditContext): Model
    {
        return DB::transaction(function () use ($organizationId, $type, $publicId, $version, $reason, $auditContext): Model {
            $record = $this->query($type, $organizationId)->where('public_id', $publicId)->lockForUpdate()->firstOrFail();
            $this->assertVersion($record, $version);
            if ($type === 'zones' && $record->bins()->where('status', '!=', 'archived')->exists()) {
                throw new MasterDataConflictException('Archive every active Bin in this Zone first.', 'zone_has_bins');
            }
            $before = $this->snapshot($record);
            $record->update(['status' => 'archived', 'lock_version' => $record->lock_version + 1]);
            $auditContext['reason'] = $reason;
            $this->audit("master_data.{$this->actionName($type)}.archived", $record, $before, $this->snapshot($record), $auditContext);

            return $this->load($type, $record);
        });
    }

    private function resolveValues(int $organizationId, string $type, array $attributes): array
    {
        return match ($type) {
            'zones' => [WarehouseZone::class, [...Arr::except($attributes, ['warehouse_public_id']), 'warehouse_id' => $this->id(Warehouse::class, $organizationId, $attributes['warehouse_public_id'])]],
            'bins' => $this->binValues($organizationId, $attributes),
            'replenishment' => [WarehouseSkuPolicy::class, [...Arr::except($attributes, ['warehouse_public_id', 'sku_public_id']), 'warehouse_id' => $this->id(Warehouse::class, $organizationId, $attributes['warehouse_public_id']), 'sku_id' => $this->id(Sku::class, $organizationId, $attributes['sku_public_id'])]],
            'cash' => [CashLocation::class, [...Arr::except($attributes, ['branch_public_id']), 'branch_id' => ($attributes['branch_public_id'] ?? null) ? $this->id(Branch::class, $organizationId, $attributes['branch_public_id']) : null]],
            default => abort(404),
        };
    }

    private function binValues(int $organizationId, array $attributes): array
    {
        $zone = WarehouseZone::query()->where('organization_id', $organizationId)->where('public_id', $attributes['zone_public_id'])->where('status', 'active')->firstOrFail();

        return [WarehouseBin::class, [...Arr::except($attributes, ['zone_public_id']), 'warehouse_id' => $zone->warehouse_id, 'warehouse_zone_id' => $zone->id]];
    }

    private function assertUnique(int $organizationId, string $type, array $values, ?Model $record): void
    {
        $query = $this->query($type, $organizationId)->when($record, fn ($query) => $query->whereKeyNot($record->id));
        $duplicate = match ($type) {
            'zones', 'bins' => $query->where('warehouse_id', $values['warehouse_id'])->where('code', $values['code'])->exists(),
            'replenishment' => $query->where('warehouse_id', $values['warehouse_id'])->where('sku_id', $values['sku_id'])->exists(),
            'cash' => $query->where('code', $values['code'])->exists(), default => false,
        };
        if ($duplicate) {
            throw new MasterDataConflictException('This controlled-location record already exists in the selected scope.', 'duplicate_controlled_location');
        }
    }

    private function query(string $type, int $organizationId)
    {
        $model = ['zones' => WarehouseZone::class, 'bins' => WarehouseBin::class, 'replenishment' => WarehouseSkuPolicy::class, 'cash' => CashLocation::class][$type] ?? abort(404);

        return $model::query()->where('organization_id', $organizationId);
    }

    private function id(string $model, int $organizationId, string $publicId): int
    {
        $id = $model::query()->where('organization_id', $organizationId)->where('public_id', $publicId)->where('status', 'active')->value('id');
        abort_unless($id, 404);

        return (int) $id;
    }

    private function load(string $type, Model $record): Model
    {
        return match ($type) {
            'zones' => $record->load('warehouse:id,public_id,code,name_en,name_my')->loadCount(['bins' => fn ($query) => $query->where('status', '!=', 'archived')]),
            'bins' => $record->load(['warehouse:id,public_id,code,name_en,name_my', 'zone:id,public_id,code,name_en,name_my']),
            'replenishment' => $record->load(['warehouse:id,public_id,code,name_en,name_my', 'sku:id,public_id,base_uom_id,code,name_en,name_my', 'sku.baseUom:id,public_id,code,symbol']),
            'cash' => $record->load('branch:id,public_id,code,name_en,name_my'), default => $record,
        };
    }

    private function assertVersion(Model $record, int $version): void
    {
        if ((int) $record->lock_version !== $version) {
            throw new MasterDataConflictException('This configuration changed after it was opened. Refresh and review the latest values.');
        }
    }

    private function actionName(string $type): string
    {
        return ['zones' => 'warehouse_zone', 'bins' => 'warehouse_bin', 'replenishment' => 'warehouse_sku_policy', 'cash' => 'cash_location'][$type];
    }

    private function snapshot(Model $record): array
    {
        return Arr::except($record->fresh()->toArray(), ['id', 'organization_id', 'warehouse_id', 'warehouse_zone_id', 'sku_id', 'branch_id']);
    }

    private function audit(string $action, Model $record, ?array $before, array $after, array $context): void
    {
        AuditEvent::query()->create(['organization_id' => $record->organization_id, 'actor_user_id' => $context['actor_user_id'] ?? null, 'action' => $action, 'entity_type' => $record::class, 'entity_public_id' => $record->public_id, 'before_state' => $before, 'after_state' => $after, 'reason' => $context['reason'] ?? null, 'correlation_id' => $context['correlation_id'] ?? null, 'ip_address' => $context['ip_address'] ?? null]);
    }
}
