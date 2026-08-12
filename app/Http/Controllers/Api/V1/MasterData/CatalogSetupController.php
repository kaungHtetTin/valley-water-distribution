<?php

namespace App\Http\Controllers\Api\V1\MasterData;

use App\Domain\MasterData\CatalogSetupService;
use App\Domain\MasterData\Exceptions\MasterDataConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MasterData\ArchiveLocationRequest;
use App\Http\Requests\Api\V1\MasterData\SaveCatalogSetupRequest;
use App\Support\Tenancy\OrganizationContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogSetupController extends Controller
{
    public function __construct(private readonly CatalogSetupService $catalog, private readonly OrganizationContext $organization) {}

    public function index(): JsonResponse
    {
        $dashboard = $this->catalog->dashboard($this->organization->id());

        return response()->json(['data' => [
            'categories' => $dashboard['categories']->map(fn ($record) => $this->recordData('categories', $record)),
            'brands' => $dashboard['brands']->map(fn ($record) => $this->recordData('brands', $record)),
            'products' => $dashboard['products']->map(fn ($record) => $this->recordData('products', $record)),
            'units' => $dashboard['units']->map(fn ($record) => $this->recordData('units', $record)),
            'price_types' => $dashboard['price_types']->map(fn ($record) => $this->recordData('price-types', $record)),
            'price_books' => $dashboard['price_books']->map(fn ($record) => $this->recordData('price-books', $record)),
            'branches' => $dashboard['branches'],
        ]]);
    }

    public function store(SaveCatalogSetupRequest $request, string $type): JsonResponse
    {
        try {
            $record = $this->catalog->save($this->organization->id(), $type, null, $request->validated(), $this->auditContext($request));

            return response()->json(['data' => $this->recordData($type, $record)], 201);
        } catch (MasterDataConflictException $exception) {
            return $this->conflict($request, $exception);
        }
    }

    public function update(SaveCatalogSetupRequest $request, string $type, string $record): JsonResponse
    {
        try {
            $saved = $this->catalog->save($this->organization->id(), $type, $record, $request->validated(), $this->auditContext($request));

            return response()->json(['data' => $this->recordData($type, $saved)]);
        } catch (MasterDataConflictException $exception) {
            return $this->conflict($request, $exception);
        }
    }

    public function archive(ArchiveLocationRequest $request, string $type, string $record): JsonResponse
    {
        try {
            $archived = $this->catalog->archive($this->organization->id(), $type, $record, (int) $request->validated('version'), $request->validated('reason'), $this->auditContext($request));

            return response()->json(['data' => $this->recordData($type, $archived)]);
        } catch (MasterDataConflictException $exception) {
            return $this->conflict($request, $exception);
        }
    }

    private function recordData(string $type, Model $record): array
    {
        $base = ['id' => $record->public_id, 'code' => $record->code, 'name' => ['en' => $record->name_en, 'my-MM' => $record->name_my], 'status' => $record->status, 'version' => $record->lock_version];
        $reference = fn ($item) => $item ? ['id' => $item->public_id, 'code' => $item->code, 'name' => ['en' => $item->name_en, 'my-MM' => $item->name_my]] : null;

        return match ($type) {
            'categories', 'brands' => [...$base, 'products_count' => $record->products_count],
            'products' => [...$base, 'brand' => $reference($record->brand), 'category' => $reference($record->category), 'description' => $record->description, 'active_from' => $record->active_from?->toDateString(), 'active_to' => $record->active_to?->toDateString(), 'skus_count' => $record->skus_count],
            'units' => [...$base, 'symbol' => $record->symbol, 'dimension' => $record->dimension, 'decimal_places' => $record->decimal_places, 'usage_count' => $record->base_skus_count + $record->conversions_count + $record->price_items_count],
            'price-types' => [...$base, 'precedence' => $record->precedence, 'requires_approval' => $record->requires_approval, 'price_books_count' => $record->price_books_count],
            'price-books' => [...$base, 'branch' => $reference($record->branch), 'price_type' => $reference($record->priceType), 'currency' => $record->currency, 'scope_type' => $record->scope_type, 'effective_from' => $record->effective_from?->toDateString(), 'effective_to' => $record->effective_to?->toDateString(), 'items_count' => $record->items_count],
            default => abort(404),
        };
    }

    private function auditContext(Request $request): array
    {
        return ['actor_user_id' => $request->user()?->id, 'correlation_id' => $request->attributes->get('correlation_id'), 'ip_address' => $request->ip()];
    }

    private function conflict(Request $request, MasterDataConflictException $exception): JsonResponse
    {
        return response()->json(['message' => $exception->getMessage(), 'code' => $exception->conflictCode, 'correlation_id' => $request->attributes->get('correlation_id')], 409);
    }
}
