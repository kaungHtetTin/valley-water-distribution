<?php

namespace App\Domain\MasterData;

use App\Domain\MasterData\Exceptions\MasterDataConflictException;
use App\Models\AuditEvent;
use App\Models\PriceBook;
use App\Models\PriceBookItem;
use App\Models\Sku;
use App\Models\SkuUomConversion;
use App\Models\UnitOfMeasure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class PriceBookItemService
{
    private const SNAPSHOT_FIELDS = ['public_id', 'price_book_id', 'sku_id', 'uom_id', 'unit_price_minor', 'minimum_quantity', 'effective_from', 'effective_to', 'approval_status', 'lock_version', 'status', 'created_at', 'updated_at'];

    public function paginate(int $organizationId, array $filters): LengthAwarePaginator
    {
        return PriceBookItem::query()
            ->with(['priceBook.priceType', 'sku.product', 'uom'])
            ->where('organization_id', $organizationId)
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $term = '%'.trim($search).'%';
                $query->whereHas('sku', fn ($query) => $query->where('code', 'like', $term)->orWhere('name_en', 'like', $term)->orWhere('name_my', 'like', $term));
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['price_book'] ?? null, fn ($query, string $book) => $query->whereHas('priceBook', fn ($query) => $query->where('public_id', $book)))
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->paginate(min(max((int) ($filters['per_page'] ?? 20), 5), 100))
            ->withQueryString();
    }

    public function create(int $organizationId, array $attributes, array $auditContext): PriceBookItem
    {
        return DB::transaction(function () use ($organizationId, $attributes, $auditContext): PriceBookItem {
            [$book, $sku, $uom] = $this->resolveReferences($organizationId, $attributes);
            $this->assertNoOverlap($organizationId, $book->id, $sku->id, $uom->id, $attributes);
            $item = PriceBookItem::query()->create([
                ...Arr::except($attributes, ['price_book_public_id', 'sku_public_id', 'uom_public_id']),
                'organization_id' => $organizationId,
                'price_book_id' => $book->id,
                'sku_id' => $sku->id,
                'uom_id' => $uom->id,
                'approval_status' => $book->priceType->requires_approval ? 'pending' : 'approved',
            ]);
            $this->recordAudit('master_data.price.created', $item, null, $this->snapshot($item), $auditContext);

            return $this->load($item);
        });
    }

    public function update(int $organizationId, string $publicId, array $attributes, array $auditContext): PriceBookItem
    {
        return DB::transaction(function () use ($organizationId, $publicId, $attributes, $auditContext): PriceBookItem {
            $item = PriceBookItem::query()->where('organization_id', $organizationId)->where('public_id', $publicId)->lockForUpdate()->firstOrFail();
            $this->assertVersion($item, (int) $attributes['version']);
            [$book, $sku, $uom] = $this->resolveReferences($organizationId, $attributes);
            $this->assertNoOverlap($organizationId, $book->id, $sku->id, $uom->id, $attributes, $item->id);
            $before = $this->snapshot($item);
            $item->fill([
                ...Arr::except($attributes, ['price_book_public_id', 'sku_public_id', 'uom_public_id', 'version']),
                'price_book_id' => $book->id,
                'sku_id' => $sku->id,
                'uom_id' => $uom->id,
                'approval_status' => $book->priceType->requires_approval ? 'pending' : 'approved',
                'lock_version' => $item->lock_version + 1,
            ])->save();
            $this->recordAudit('master_data.price.updated', $item, $before, $this->snapshot($item), $auditContext);

            return $this->load($item);
        });
    }

    public function archive(int $organizationId, string $publicId, int $version, string $reason, array $auditContext): PriceBookItem
    {
        return DB::transaction(function () use ($organizationId, $publicId, $version, $reason, $auditContext): PriceBookItem {
            $item = PriceBookItem::query()->where('organization_id', $organizationId)->where('public_id', $publicId)->lockForUpdate()->firstOrFail();
            $this->assertVersion($item, $version);
            $before = $this->snapshot($item);
            $item->update(['status' => 'archived', 'lock_version' => $item->lock_version + 1]);
            $auditContext['reason'] = $reason;
            $this->recordAudit('master_data.price.archived', $item, $before, $this->snapshot($item), $auditContext);

            return $this->load($item);
        });
    }

    public function approve(int $organizationId, string $publicId, int $version, string $reason, array $auditContext): PriceBookItem
    {
        return DB::transaction(function () use ($organizationId, $publicId, $version, $reason, $auditContext): PriceBookItem {
            $item = PriceBookItem::query()->where('organization_id', $organizationId)->where('public_id', $publicId)->lockForUpdate()->firstOrFail();
            $this->assertVersion($item, $version);
            if ($item->approval_status !== 'pending') {
                throw new MasterDataConflictException('Only pending prices can be approved.', 'price_not_pending');
            }
            $before = $this->snapshot($item);
            $item->update(['approval_status' => 'approved', 'lock_version' => $item->lock_version + 1]);
            $auditContext['reason'] = $reason;
            $this->recordAudit('master_data.price.approved', $item, $before, $this->snapshot($item), $auditContext);

            return $this->load($item);
        });
    }

    private function resolveReferences(int $organizationId, array $attributes): array
    {
        $book = PriceBook::query()->with('priceType')->where('organization_id', $organizationId)->where('public_id', $attributes['price_book_public_id'])->where('status', 'active')->firstOrFail();
        $sku = Sku::query()->where('organization_id', $organizationId)->where('public_id', $attributes['sku_public_id'])->where('status', 'active')->firstOrFail();
        $uom = UnitOfMeasure::query()->where('organization_id', $organizationId)->where('public_id', $attributes['uom_public_id'])->where('status', 'active')->firstOrFail();
        $hasConversion = SkuUomConversion::query()->where('organization_id', $organizationId)->where('sku_id', $sku->id)->where('uom_id', $uom->id)->where('status', 'active')->exists();
        if (! $hasConversion) {
            throw new MasterDataConflictException('The selected unit has no active conversion for this SKU.', 'missing_unit_conversion');
        }

        return [$book, $sku, $uom];
    }

    private function assertNoOverlap(int $organizationId, int $bookId, int $skuId, int $uomId, array $attributes, ?int $exceptId = null): void
    {
        $end = $attributes['effective_to'] ?? '9999-12-31';
        $overlap = PriceBookItem::query()
            ->where('organization_id', $organizationId)
            ->where('price_book_id', $bookId)
            ->where('sku_id', $skuId)
            ->where('uom_id', $uomId)
            ->where('minimum_quantity', $attributes['minimum_quantity'])
            ->where('status', 'active')
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->whereDate('effective_from', '<=', $end)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $attributes['effective_from']))
            ->exists();

        if ($overlap) {
            throw new MasterDataConflictException('This price overlaps an existing active price for the same book, SKU, unit, and quantity tier.', 'price_date_overlap');
        }
    }

    private function assertVersion(PriceBookItem $item, int $version): void
    {
        if ($item->lock_version !== $version) {
            throw new MasterDataConflictException('This price changed after it was opened. Refresh and review the latest values.');
        }
    }

    private function load(PriceBookItem $item): PriceBookItem
    {
        return $item->load(['priceBook.priceType', 'sku.product', 'uom']);
    }

    private function snapshot(PriceBookItem $item): array
    {
        return Arr::only($item->fresh()->toArray(), self::SNAPSHOT_FIELDS);
    }

    private function recordAudit(string $action, PriceBookItem $item, ?array $before, array $after, array $context): void
    {
        AuditEvent::query()->create([
            'organization_id' => $item->organization_id,
            'actor_user_id' => $context['actor_user_id'] ?? null,
            'action' => $action,
            'entity_type' => PriceBookItem::class,
            'entity_public_id' => $item->public_id,
            'before_state' => $before,
            'after_state' => $after,
            'reason' => $context['reason'] ?? null,
            'correlation_id' => $context['correlation_id'] ?? null,
            'ip_address' => $context['ip_address'] ?? null,
        ]);
    }
}
