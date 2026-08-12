<?php

namespace App\Domain\MasterData;

use App\Domain\MasterData\Exceptions\MasterDataConflictException;
use App\Models\AuditEvent;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\PriceBook;
use App\Models\PriceType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CatalogSetupService
{
    public function dashboard(int $organizationId): array
    {
        return [
            'categories' => ProductCategory::query()->where('organization_id', $organizationId)->withCount(['products' => fn ($query) => $query->where('status', '!=', 'archived')])->orderBy('code')->get(),
            'brands' => Brand::query()->where('organization_id', $organizationId)->withCount(['products' => fn ($query) => $query->where('status', '!=', 'archived')])->orderBy('code')->get(),
            'products' => Product::query()->where('organization_id', $organizationId)->with(['brand:id,public_id,code,name_en,name_my', 'category:id,public_id,code,name_en,name_my'])->withCount(['skus' => fn ($query) => $query->where('status', '!=', 'archived')])->orderBy('code')->get(),
            'units' => UnitOfMeasure::query()->where('organization_id', $organizationId)->withCount([
                'baseSkus as base_skus_count' => fn ($query) => $query->where('status', '!=', 'archived'),
                'conversions as conversions_count' => fn ($query) => $query->where('status', '!=', 'archived'),
                'priceItems as price_items_count' => fn ($query) => $query->where('status', '!=', 'archived'),
            ])->orderBy('code')->get(),
            'price_types' => PriceType::query()->where('organization_id', $organizationId)->withCount(['priceBooks' => fn ($query) => $query->where('status', '!=', 'archived')])->orderBy('precedence')->orderBy('code')->get(),
            'price_books' => PriceBook::query()->where('organization_id', $organizationId)->with(['priceType:id,public_id,code,name_en,name_my', 'branch:id,public_id,code,name_en,name_my'])->withCount(['items' => fn ($query) => $query->where('status', '!=', 'archived')])->orderBy('code')->get(),
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
            $this->assertArchivable($type, $record);

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
            'categories' => [ProductCategory::class, $attributes],
            'brands' => [Brand::class, $attributes],
            'products' => [Product::class, [
                ...Arr::except($attributes, ['brand_public_id', 'category_public_id']),
                'brand_id' => $this->id(Brand::class, $organizationId, $attributes['brand_public_id']),
                'product_category_id' => ($attributes['category_public_id'] ?? null) ? $this->id(ProductCategory::class, $organizationId, $attributes['category_public_id']) : null,
            ]],
            'units' => [UnitOfMeasure::class, $attributes],
            'price-types' => [PriceType::class, $attributes],
            'price-books' => [PriceBook::class, [
                ...Arr::except($attributes, ['branch_public_id', 'price_type_public_id']),
                'branch_id' => ($attributes['branch_public_id'] ?? null) ? $this->id(Branch::class, $organizationId, $attributes['branch_public_id']) : null,
                'price_type_id' => $this->id(PriceType::class, $organizationId, $attributes['price_type_public_id']),
            ]],
            default => abort(404),
        };
    }

    private function assertUnique(int $organizationId, string $type, array $values, ?Model $record): void
    {
        if ($this->query($type, $organizationId)->when($record, fn ($query) => $query->whereKeyNot($record->id))->where('code', $values['code'])->exists()) {
            throw new MasterDataConflictException('This code already exists in the organization.', 'duplicate_catalog_setup_code');
        }
    }

    private function assertArchivable(string $type, Model $record): void
    {
        $dependency = match ($type) {
            'categories', 'brands' => $record->products()->where('status', '!=', 'archived')->exists(),
            'products' => $record->skus()->where('status', '!=', 'archived')->exists(),
            'units' => $record->baseSkus()->where('status', '!=', 'archived')->exists()
                || $record->conversions()->where('status', '!=', 'archived')->exists()
                || $record->priceItems()->where('status', '!=', 'archived')->exists(),
            'price-types' => $record->priceBooks()->where('status', '!=', 'archived')->exists(),
            'price-books' => $record->items()->where('status', '!=', 'archived')->exists(),
            default => false,
        };

        if ($dependency) {
            $codes = [
                'categories' => 'category_has_products', 'brands' => 'brand_has_products', 'products' => 'product_has_skus',
                'units' => 'unit_has_usage', 'price-types' => 'price_type_has_books', 'price-books' => 'price_book_has_items',
            ];
            throw new MasterDataConflictException('Archive dependent active records before archiving this catalog setup record.', $codes[$type]);
        }
    }

    private function query(string $type, int $organizationId)
    {
        $model = [
            'categories' => ProductCategory::class, 'brands' => Brand::class, 'products' => Product::class,
            'units' => UnitOfMeasure::class, 'price-types' => PriceType::class, 'price-books' => PriceBook::class,
        ][$type] ?? abort(404);

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
        $record->refresh();

        return match ($type) {
            'categories', 'brands' => $record->loadCount(['products' => fn ($query) => $query->where('status', '!=', 'archived')]),
            'products' => $record->load(['brand:id,public_id,code,name_en,name_my', 'category:id,public_id,code,name_en,name_my'])->loadCount(['skus' => fn ($query) => $query->where('status', '!=', 'archived')]),
            'units' => $record->loadCount([
                'baseSkus as base_skus_count' => fn ($query) => $query->where('status', '!=', 'archived'),
                'conversions as conversions_count' => fn ($query) => $query->where('status', '!=', 'archived'),
                'priceItems as price_items_count' => fn ($query) => $query->where('status', '!=', 'archived'),
            ]),
            'price-types' => $record->loadCount(['priceBooks' => fn ($query) => $query->where('status', '!=', 'archived')]),
            'price-books' => $record->load(['priceType:id,public_id,code,name_en,name_my', 'branch:id,public_id,code,name_en,name_my'])->loadCount(['items' => fn ($query) => $query->where('status', '!=', 'archived')]),
            default => $record,
        };
    }

    private function assertVersion(Model $record, int $version): void
    {
        if ((int) $record->lock_version !== $version) {
            throw new MasterDataConflictException('This record changed after it was opened. Refresh and review the latest values.');
        }
    }

    private function actionName(string $type): string
    {
        return [
            'categories' => 'product_category', 'brands' => 'brand', 'products' => 'product',
            'units' => 'unit_of_measure', 'price-types' => 'price_type', 'price-books' => 'price_book',
        ][$type];
    }

    private function snapshot(Model $record): array
    {
        return Arr::except($record->fresh()->toArray(), ['id', 'organization_id', 'brand_id', 'product_category_id', 'branch_id', 'price_type_id']);
    }

    private function audit(string $action, Model $record, ?array $before, array $after, array $context): void
    {
        AuditEvent::query()->create([
            'organization_id' => $record->organization_id, 'actor_user_id' => $context['actor_user_id'] ?? null,
            'action' => $action, 'entity_type' => $record::class, 'entity_public_id' => $record->public_id,
            'before_state' => $before, 'after_state' => $after, 'reason' => $context['reason'] ?? null,
            'correlation_id' => $context['correlation_id'] ?? null, 'ip_address' => $context['ip_address'] ?? null,
        ]);
    }
}
