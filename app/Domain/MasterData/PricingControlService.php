<?php

namespace App\Domain\MasterData;

use App\Domain\MasterData\Exceptions\MasterDataConflictException;
use App\Models\AuditEvent;
use App\Models\FoundationMasterRecord;
use App\Models\PriceBook;
use App\Models\PriceBookAssignment;
use App\Models\PriceBookItem;
use App\Models\ProductCostHistory;
use App\Models\Sku;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use App\Models\Way;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PricingControlService
{
    public function dashboard(int $organizationId): array
    {
        return [
            'assignments' => PriceBookAssignment::query()->where('organization_id', $organizationId)->with('priceBook.priceType')->orderBy('target_type')->orderBy('priority')->get(),
            'costs' => ProductCostHistory::query()->where('organization_id', $organizationId)->with(['warehouse:id,public_id,code,name_en,name_my', 'sku:id,public_id,code,name_en,name_my'])->orderByDesc('effective_from')->get(),
            'price_books' => PriceBook::query()->where('organization_id', $organizationId)->where('status', 'active')->with('priceType:id,public_id,code,name_en,name_my')->orderBy('code')->get(),
            'customers' => FoundationMasterRecord::query()->where('organization_id', $organizationId)->where('type', 'customers')->where('status', 'active')->orderBy('code')->get(['public_id', 'code', 'name_en', 'name_my', 'classification']),
            'ways' => Way::query()->where('organization_id', $organizationId)->where('status', 'active')->orderBy('code')->get(['public_id', 'code', 'name_en', 'name_my']),
            'warehouses' => Warehouse::query()->where('organization_id', $organizationId)->where('status', 'active')->orderBy('code')->get(['public_id', 'code', 'name_en', 'name_my']),
            'skus' => Sku::query()->where('organization_id', $organizationId)->where('status', 'active')->orderBy('code')->get(['public_id', 'code', 'name_en', 'name_my']),
        ];
    }

    public function saveAssignment(int $organizationId, ?string $publicId, array $values, array $context): PriceBookAssignment
    {
        return DB::transaction(function () use ($organizationId, $publicId, $values, $context): PriceBookAssignment {
            $record = $publicId ? PriceBookAssignment::query()->where('organization_id', $organizationId)->where('public_id', $publicId)->lockForUpdate()->firstOrFail() : null;
            if ($record && (int) $record->lock_version !== (int) $values['version']) {
                throw new MasterDataConflictException('This price assignment changed after it was opened.');
            }
            $bookId = PriceBook::query()->where('organization_id', $organizationId)->where('public_id', $values['price_book_public_id'])->where('status', 'active')->value('id') ?: abort(404);
            $targetKey = $this->targetKey($organizationId, $values['target_type'], $values['target_key']);
            $end = $values['effective_to'] ?? '9999-12-31';
            $overlap = PriceBookAssignment::query()->where('organization_id', $organizationId)->where('target_type', $values['target_type'])->where('target_key', $targetKey)->where('price_book_id', $bookId)->where('status', 'active')->when($record, fn ($query) => $query->whereKeyNot($record->id))->whereDate('effective_from', '<=', $end)->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $values['effective_from']))->exists();
            if ($overlap) {
                throw new MasterDataConflictException('This assignment overlaps an active assignment.', 'price_assignment_overlap');
            }
            $before = $record ? $this->snapshot($record) : null;
            $attributes = [...Arr::except($values, ['version', 'price_book_public_id']), 'price_book_id' => $bookId, 'target_key' => $targetKey];
            if ($record) {
                $record->update([...$attributes, 'lock_version' => $record->lock_version + 1]);
                $verb = 'updated';
            } else {
                $record = PriceBookAssignment::query()->create([...$attributes, 'organization_id' => $organizationId]);
                $verb = 'created';
            }
            $this->audit("master_data.price_assignment.{$verb}", $record, $before, $this->snapshot($record), $context);

            return $record->load('priceBook.priceType');
        });
    }

    public function createCost(int $organizationId, array $values, array $context): ProductCostHistory
    {
        return DB::transaction(function () use ($organizationId, $values, $context): ProductCostHistory {
            $warehouseId = Warehouse::query()->where('organization_id', $organizationId)->where('public_id', $values['warehouse_public_id'])->where('status', 'active')->value('id') ?: abort(404);
            $skuId = Sku::query()->where('organization_id', $organizationId)->where('public_id', $values['sku_public_id'])->where('status', 'active')->value('id') ?: abort(404);
            $end = $values['effective_to'] ?? '9999-12-31';
            if (ProductCostHistory::query()->where('organization_id', $organizationId)->where('warehouse_id', $warehouseId)->where('sku_id', $skuId)->where('status', 'active')->whereDate('effective_from', '<=', $end)->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $values['effective_from']))->exists()) {
                throw new MasterDataConflictException('This cost overlaps an active cost period.', 'cost_date_overlap');
            }
            $record = ProductCostHistory::query()->create([...Arr::except($values, ['warehouse_public_id', 'sku_public_id']), 'organization_id' => $organizationId, 'warehouse_id' => $warehouseId, 'sku_id' => $skuId, 'approval_status' => 'pending', 'status' => 'active']);
            $this->audit('master_data.product_cost.created', $record, null, $this->snapshot($record), $context);

            return $record->load(['warehouse:id,public_id,code,name_en,name_my', 'sku:id,public_id,code,name_en,name_my']);
        });
    }

    public function archiveAssignment(int $organizationId, string $publicId, int $version, string $reason, array $context): PriceBookAssignment
    {
        return DB::transaction(function () use ($organizationId, $publicId, $version, $reason, $context): PriceBookAssignment {
            $record = PriceBookAssignment::query()->where('organization_id', $organizationId)->where('public_id', $publicId)->lockForUpdate()->firstOrFail();
            if ((int) $record->lock_version !== $version) {
                throw new MasterDataConflictException('This price assignment changed after it was opened.');
            }
            $before = $this->snapshot($record);
            $record->update(['status' => 'archived', 'lock_version' => $record->lock_version + 1]);
            $context['reason'] = $reason;
            $this->audit('master_data.price_assignment.archived', $record, $before, $this->snapshot($record), $context);

            return $record->load('priceBook.priceType');
        });
    }

    public function approveCost(int $organizationId, string $publicId, int $version, string $reason, array $context): ProductCostHistory
    {
        return DB::transaction(function () use ($organizationId, $publicId, $version, $reason, $context): ProductCostHistory {
            $record = ProductCostHistory::query()->where('organization_id', $organizationId)->where('public_id', $publicId)->lockForUpdate()->firstOrFail();
            if ((int) $record->lock_version !== $version) {
                throw new MasterDataConflictException('This cost changed after it was opened.');
            }
            if ($record->approval_status !== 'pending') {
                throw new MasterDataConflictException('Only pending costs can be approved.', 'cost_not_pending');
            }
            $before = $this->snapshot($record);
            $record->update(['approval_status' => 'approved', 'lock_version' => $record->lock_version + 1, 'reason' => $reason]);
            $context['reason'] = $reason;
            $this->audit('master_data.product_cost.approved', $record, $before, $this->snapshot($record), $context);

            return $record->load(['warehouse:id,public_id,code,name_en,name_my', 'sku:id,public_id,code,name_en,name_my']);
        });
    }

    public function resolve(int $organizationId, string $customerPublicId, string $skuPublicId, string $uomPublicId, float $quantity, string $date): ?PriceBookItem
    {
        $customer = FoundationMasterRecord::query()->where('organization_id', $organizationId)->where('type', 'customers')->where('public_id', $customerPublicId)->where('status', 'active')->firstOrFail();
        $sku = Sku::query()->where('organization_id', $organizationId)->where('public_id', $skuPublicId)->where('status', 'active')->firstOrFail();
        $uom = UnitOfMeasure::query()->where('organization_id', $organizationId)->where('public_id', $uomPublicId)->where('status', 'active')->firstOrFail();
        $on = Carbon::parse($date)->toDateString();
        $ranks = [];
        $assignments = PriceBookAssignment::query()->where('organization_id', $organizationId)->where('status', 'active')->whereDate('effective_from', '<=', $on)->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $on))->get();
        foreach ($assignments as $assignment) {
            $rank = match (true) {
                $assignment->target_type === 'customer' && $assignment->target_key === $customer->public_id => 10,
                $assignment->target_type === 'way' && $customer->way_id && $assignment->target_key === $customer->way?->public_id => 20,
                $assignment->target_type === 'customer_classification' && $assignment->target_key === $customer->classification => 30,
                default => null,
            };
            if ($rank !== null) {
                $ranks[$assignment->price_book_id] = min($ranks[$assignment->price_book_id] ?? 9999, $rank + $assignment->priority);
            }
        }
        PriceBook::query()->where('organization_id', $organizationId)->where('status', 'active')->whereDate('effective_from', '<=', $on)->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $on))->get()->each(function ($book) use (&$ranks, $customer): void {
            if ($book->scope_type === 'branch_default' && $book->branch_id === $customer->branch_id) {
                $ranks[$book->id] ??= 400;
            }
            if ($book->scope_type === 'organization_default') {
                $ranks[$book->id] ??= 500;
            }
        });
        if (! $ranks) {
            return null;
        }

        return PriceBookItem::query()->with(['priceBook.priceType', 'sku.product', 'uom'])->where('organization_id', $organizationId)->whereIn('price_book_id', array_keys($ranks))->where('sku_id', $sku->id)->where('uom_id', $uom->id)->where('status', 'active')->where('approval_status', 'approved')->where('minimum_quantity', '<=', $quantity)->whereDate('effective_from', '<=', $on)->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $on))->get()->sortBy(fn ($item) => sprintf('%05d-%05d-%020.3f', $ranks[$item->price_book_id], $item->priceBook->priceType->precedence, 999999999 - (float) $item->minimum_quantity))->first();
    }

    private function targetKey(int $organizationId, string $type, string $key): string
    {
        return match ($type) {
            'customer' => FoundationMasterRecord::query()->where('organization_id', $organizationId)->where('type', 'customers')->where('public_id', $key)->where('status', 'active')->value('public_id') ?: abort(404),
            'way' => Way::query()->where('organization_id', $organizationId)->where('public_id', $key)->where('status', 'active')->value('public_id') ?: abort(404),
            'customer_classification' => strtolower(trim($key)), default => abort(404),
        };
    }

    private function snapshot($model): array
    {
        return Arr::except($model->fresh()->toArray(), ['id', 'organization_id', 'warehouse_id', 'sku_id', 'price_book_id']);
    }

    private function audit(string $action, $model, ?array $before, array $after, array $context): void
    {
        AuditEvent::query()->create(['organization_id' => $model->organization_id, 'actor_user_id' => $context['actor_user_id'] ?? null, 'action' => $action, 'entity_type' => $model::class, 'entity_public_id' => $model->public_id, 'before_state' => $before, 'after_state' => $after, 'reason' => $context['reason'] ?? null, 'correlation_id' => $context['correlation_id'] ?? null, 'ip_address' => $context['ip_address'] ?? null]);
    }
}
