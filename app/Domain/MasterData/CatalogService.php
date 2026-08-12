<?php

namespace App\Domain\MasterData;

use App\Domain\MasterData\Exceptions\MasterDataConflictException;
use App\Models\AuditEvent;
use App\Models\PriceBook;
use App\Models\Product;
use App\Models\Sku;
use App\Models\SkuUomConversion;
use App\Models\UnitOfMeasure;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CatalogService
{
    private const SNAPSHOT_FIELDS = [
        'public_id', 'product_id', 'base_uom_id', 'code', 'name_en', 'name_my', 'size_label', 'barcode',
        'volume_ml', 'weight_grams', 'shelf_life_days', 'track_lot', 'track_expiry', 'is_returnable',
        'minimum_order_quantity', 'order_step_quantity', 'minimum_delivery_quantity', 'sale_status',
        'active_from', 'active_to', 'lock_version', 'status', 'created_at', 'updated_at',
    ];

    public function paginate(int $organizationId, array $filters): LengthAwarePaginator
    {
        $sort = in_array($filters['sort'] ?? null, ['code', 'name_en', 'size_label', 'status', 'updated_at'], true)
            ? $filters['sort']
            : 'code';
        $direction = ($filters['direction'] ?? null) === 'desc' ? 'desc' : 'asc';
        $perPage = min(max((int) ($filters['per_page'] ?? 20), 5), 100);

        return Sku::query()
            ->with([
                'product:id,public_id,brand_id,code,name_en,name_my',
                'product.brand:id,public_id,code,name_en,name_my',
                'baseUom:id,public_id,code,name_en,name_my,symbol',
                'conversions' => fn ($query) => $query->where('status', 'active')->with('uom:id,public_id,code,name_en,name_my,symbol'),
                'priceItems' => fn ($query) => $query->where('status', 'active')
                    ->with(['priceBook:id,public_id,price_type_id,code,name_en,name_my,currency', 'priceBook.priceType:id,public_id,code,name_en,name_my', 'uom:id,public_id,code,symbol'])
                    ->orderByDesc('effective_from'),
            ])
            ->where('organization_id', $organizationId)
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $term = '%'.trim($search).'%';
                $query->where(fn ($query) => $query
                    ->where('code', 'like', $term)
                    ->orWhere('name_en', 'like', $term)
                    ->orWhere('name_my', 'like', $term)
                    ->orWhere('barcode', 'like', $term));
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(int $organizationId, array $attributes, array $auditContext): Sku
    {
        return DB::transaction(function () use ($organizationId, $attributes, $auditContext): Sku {
            $product = Product::query()
                ->where('organization_id', $organizationId)
                ->where('public_id', $attributes['product_public_id'])
                ->where('status', 'active')
                ->firstOrFail();
            $baseUom = UnitOfMeasure::query()
                ->where('organization_id', $organizationId)
                ->where('public_id', $attributes['base_uom_public_id'])
                ->where('status', 'active')
                ->firstOrFail();

            unset($attributes['product_public_id'], $attributes['base_uom_public_id']);
            $sku = Sku::query()->create([
                ...$attributes,
                'organization_id' => $organizationId,
                'product_id' => $product->id,
                'base_uom_id' => $baseUom->id,
            ]);

            SkuUomConversion::query()->create([
                'organization_id' => $organizationId,
                'sku_id' => $sku->id,
                'uom_id' => $baseUom->id,
                'factor_to_base' => 1,
                'version' => 1,
                'is_selling_unit' => true,
                'is_kpi_base' => true,
                'effective_from' => $attributes['active_from'] ?? now()->toDateString(),
                'status' => 'active',
            ]);

            $this->recordAudit('master_data.sku.created', $sku, null, $this->snapshot($sku), $auditContext);

            return $this->load($sku->refresh());
        });
    }

    public function update(int $organizationId, string $publicId, array $attributes, array $auditContext): Sku
    {
        return DB::transaction(function () use ($organizationId, $publicId, $attributes, $auditContext): Sku {
            $sku = Sku::query()
                ->where('organization_id', $organizationId)
                ->where('public_id', $publicId)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertVersion($sku, (int) $attributes['version']);
            $before = $this->snapshot($sku);
            unset($attributes['version']);
            $attributes['lock_version'] = $sku->lock_version + 1;
            $sku->fill($attributes)->save();
            $this->recordAudit('master_data.sku.updated', $sku, $before, $this->snapshot($sku), $auditContext);

            return $this->load($sku);
        });
    }

    public function archive(int $organizationId, string $publicId, int $version, string $reason, array $auditContext): Sku
    {
        return DB::transaction(function () use ($organizationId, $publicId, $version, $reason, $auditContext): Sku {
            $sku = Sku::query()
                ->where('organization_id', $organizationId)
                ->where('public_id', $publicId)
                ->lockForUpdate()
                ->firstOrFail();
            $this->assertVersion($sku, $version);

            if ($sku->status === 'archived') {
                return $this->load($sku);
            }

            $before = $this->snapshot($sku);
            $sku->update(['status' => 'archived', 'sale_status' => 'not_for_sale', 'lock_version' => $sku->lock_version + 1]);
            $sku->conversions()->where('status', 'active')->update(['status' => 'archived', 'effective_to' => now()->toDateString()]);
            $sku->priceItems()->where('status', 'active')->update(['status' => 'archived']);
            $sku->replenishmentPolicies()->where('status', 'active')->update(['status' => 'archived']);
            $auditContext['reason'] = $reason;
            $this->recordAudit('master_data.sku.archived', $sku, $before, $this->snapshot($sku), $auditContext);

            return $this->load($sku);
        });
    }

    public function reviseConversion(int $organizationId, string $publicId, array $attributes, array $auditContext): Sku
    {
        return DB::transaction(function () use ($organizationId, $publicId, $attributes, $auditContext): Sku {
            $sku = Sku::query()->where('organization_id', $organizationId)->where('public_id', $publicId)->lockForUpdate()->firstOrFail();
            $this->assertVersion($sku, (int) $attributes['version']);
            $uom = UnitOfMeasure::query()->where('organization_id', $organizationId)->where('public_id', $attributes['uom_public_id'])->where('status', 'active')->firstOrFail();

            if ($uom->id === $sku->base_uom_id && (float) $attributes['factor_to_base'] !== 1.0) {
                throw new MasterDataConflictException('The base inventory unit must always have a conversion factor of exactly 1.', 'invalid_base_conversion');
            }

            $current = SkuUomConversion::query()->where('organization_id', $organizationId)->where('sku_id', $sku->id)->where('uom_id', $uom->id)->where('status', 'active')->lockForUpdate()->first();
            $effectiveFrom = CarbonImmutable::parse($attributes['effective_from'])->startOfDay();
            if ($current && $effectiveFrom->lessThanOrEqualTo($current->effective_from)) {
                throw new MasterDataConflictException('A conversion revision must start after the current conversion version.', 'conversion_date_conflict');
            }

            $before = $current?->toArray();
            if ($current) {
                $current->update(['effective_to' => $effectiveFrom->subDay()->toDateString(), 'status' => 'superseded']);
            }
            if ($attributes['is_kpi_base']) {
                SkuUomConversion::query()->where('sku_id', $sku->id)->where('status', 'active')->update(['is_kpi_base' => false]);
            }

            $conversion = SkuUomConversion::query()->create([
                'organization_id' => $organizationId,
                'sku_id' => $sku->id,
                'uom_id' => $uom->id,
                'factor_to_base' => $attributes['factor_to_base'],
                'version' => ($current?->version ?? 0) + 1,
                'is_selling_unit' => $attributes['is_selling_unit'],
                'is_kpi_base' => $attributes['is_kpi_base'],
                'effective_from' => $effectiveFrom->toDateString(),
                'status' => 'active',
            ]);
            $hasKpiBase = SkuUomConversion::query()->where('sku_id', $sku->id)->where('status', 'active')->where('is_kpi_base', true)->exists();
            if (! $hasKpiBase) {
                throw new MasterDataConflictException('Every SKU must retain exactly one active KPI base unit.', 'missing_kpi_base');
            }
            $sku->update(['lock_version' => $sku->lock_version + 1]);
            $this->recordAudit('master_data.sku.conversion_revised', $sku, $before, $conversion->toArray(), $auditContext);

            return $this->load($sku);
        });
    }

    public function options(int $organizationId): array
    {
        return [
            'products' => Product::query()->with('brand:id,public_id,code,name_en,name_my')
                ->where('organization_id', $organizationId)->where('status', 'active')->orderBy('name_en')
                ->get(['id', 'public_id', 'brand_id', 'code', 'name_en', 'name_my']),
            'units' => UnitOfMeasure::query()->where('organization_id', $organizationId)->where('status', 'active')
                ->orderBy('code')->get(['public_id', 'code', 'name_en', 'name_my', 'symbol', 'decimal_places']),
            'price_books' => PriceBook::query()->with('priceType:id,public_id,code,name_en,name_my')
                ->where('organization_id', $organizationId)->where('status', 'active')->orderBy('code')
                ->get(['id', 'public_id', 'price_type_id', 'code', 'name_en', 'name_my', 'currency']),
        ];
    }

    private function load(Sku $sku): Sku
    {
        return $sku->load([
            'product:id,public_id,brand_id,code,name_en,name_my', 'product.brand:id,public_id,code,name_en,name_my',
            'baseUom:id,public_id,code,name_en,name_my,symbol',
            'conversions' => fn ($query) => $query->where('status', 'active')->with('uom:id,public_id,code,name_en,name_my,symbol'),
            'priceItems' => fn ($query) => $query->where('status', 'active')->with(['priceBook.priceType', 'uom']),
        ]);
    }

    private function assertVersion(Sku $sku, int $version): void
    {
        if ($sku->lock_version !== $version) {
            throw new MasterDataConflictException('This SKU changed after it was opened. Refresh and review the latest values.');
        }
    }

    private function snapshot(Sku $sku): array
    {
        return Arr::only($sku->fresh()->toArray(), self::SNAPSHOT_FIELDS);
    }

    private function recordAudit(string $action, Sku $sku, ?array $before, array $after, array $context): void
    {
        AuditEvent::query()->create([
            'organization_id' => $sku->organization_id,
            'actor_user_id' => $context['actor_user_id'] ?? null,
            'action' => $action,
            'entity_type' => Sku::class,
            'entity_public_id' => $sku->public_id,
            'before_state' => $before,
            'after_state' => $after,
            'reason' => $context['reason'] ?? null,
            'correlation_id' => $context['correlation_id'] ?? null,
            'ip_address' => $context['ip_address'] ?? null,
        ]);
    }
}
