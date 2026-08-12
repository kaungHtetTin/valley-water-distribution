<?php

namespace App\Http\Controllers\Api\V1\MasterData;

use App\Domain\MasterData\ControlledLocationService;
use App\Domain\MasterData\Exceptions\MasterDataConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MasterData\ArchiveLocationRequest;
use App\Http\Requests\Api\V1\MasterData\SaveControlledLocationRequest;
use App\Support\Tenancy\OrganizationContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ControlledLocationController extends Controller
{
    public function __construct(private readonly ControlledLocationService $locations, private readonly OrganizationContext $organization) {}

    public function index(): JsonResponse
    {
        $dashboard = $this->locations->dashboard($this->organization->id());

        return response()->json(['data' => [
            'zones' => $dashboard['zones']->map(fn ($record) => $this->recordData('zones', $record)),
            'bins' => $dashboard['bins']->map(fn ($record) => $this->recordData('bins', $record)),
            'replenishment' => $dashboard['replenishment']->map(fn ($record) => $this->recordData('replenishment', $record)),
            'cash' => $dashboard['cash']->map(fn ($record) => $this->recordData('cash', $record)),
            'warehouses' => $dashboard['warehouses'], 'skus' => $dashboard['skus'], 'branches' => $dashboard['branches'],
        ]]);
    }

    public function store(SaveControlledLocationRequest $request, string $type): JsonResponse
    {
        try {
            $record = $this->locations->save($this->organization->id(), $type, null, $request->validated(), $this->auditContext($request));

            return response()->json(['data' => $this->recordData($type, $record)], 201);
        } catch (MasterDataConflictException $exception) {
            return $this->conflict($request, $exception);
        }
    }

    public function update(SaveControlledLocationRequest $request, string $type, string $record): JsonResponse
    {
        try {
            $saved = $this->locations->save($this->organization->id(), $type, $record, $request->validated(), $this->auditContext($request));

            return response()->json(['data' => $this->recordData($type, $saved)]);
        } catch (MasterDataConflictException $exception) {
            return $this->conflict($request, $exception);
        }
    }

    public function archive(ArchiveLocationRequest $request, string $type, string $record): JsonResponse
    {
        try {
            $archived = $this->locations->archive($this->organization->id(), $type, $record, (int) $request->validated('version'), $request->validated('reason'), $this->auditContext($request));

            return response()->json(['data' => $this->recordData($type, $archived)]);
        } catch (MasterDataConflictException $exception) {
            return $this->conflict($request, $exception);
        }
    }

    private function recordData(string $type, Model $record): array
    {
        $base = ['id' => $record->public_id, 'status' => $record->status, 'version' => $record->lock_version];
        $reference = fn ($item) => $item ? ['id' => $item->public_id, 'code' => $item->code, 'name' => ['en' => $item->name_en, 'my-MM' => $item->name_my]] : null;

        return match ($type) {
            'zones' => [...$base, 'warehouse' => $reference($record->warehouse), 'code' => $record->code, 'name' => ['en' => $record->name_en, 'my-MM' => $record->name_my], 'zone_type' => $record->zone_type, 'temperature_class' => $record->temperature_class, 'sort_order' => $record->sort_order, 'bins_count' => $record->bins_count],
            'bins' => [...$base, 'warehouse' => $reference($record->warehouse), 'zone' => $reference($record->zone), 'code' => $record->code, 'label' => $record->label, 'bin_type' => $record->bin_type, 'capacity_units' => $record->capacity_units, 'sort_order' => $record->sort_order],
            'replenishment' => [...$base, 'warehouse' => $reference($record->warehouse), 'sku' => [...$reference($record->sku), 'base_uom' => ['code' => $record->sku->baseUom->code, 'symbol' => $record->sku->baseUom->symbol]], 'safety_stock' => $record->safety_stock, 'reorder_point' => $record->reorder_point, 'target_stock' => $record->target_stock, 'replenishment_lead_days' => $record->replenishment_lead_days],
            'cash' => [...$base, 'branch' => $reference($record->branch), 'code' => $record->code, 'name' => ['en' => $record->name_en, 'my-MM' => $record->name_my], 'location_type' => $record->location_type, 'currency' => $record->currency, 'description' => $record->description],
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
