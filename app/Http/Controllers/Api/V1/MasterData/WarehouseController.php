<?php

namespace App\Http\Controllers\Api\V1\MasterData;

use App\Domain\MasterData\Exceptions\MasterDataConflictException;
use App\Domain\MasterData\LocationMasterService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MasterData\ArchiveLocationRequest;
use App\Http\Requests\Api\V1\MasterData\StoreWarehouseRequest;
use App\Http\Requests\Api\V1\MasterData\UpdateWarehouseRequest;
use App\Http\Resources\Api\V1\WarehouseResource;
use App\Support\Tenancy\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WarehouseController extends Controller
{
    public function __construct(private readonly LocationMasterService $locations, private readonly OrganizationContext $organization) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', 'in:active,inactive,archived'],
            'branch' => ['nullable', 'string', 'size:26'],
            'area' => ['nullable', 'string', 'size:26'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return WarehouseResource::collection($this->locations->paginateWarehouses($this->organization->id(), $filters));
    }

    public function store(StoreWarehouseRequest $request): JsonResponse
    {
        return (new WarehouseResource($this->locations->createWarehouse($this->organization->id(), $request->validated(), $this->auditContext($request))))->response()->setStatusCode(201);
    }

    public function update(UpdateWarehouseRequest $request, string $warehouse): WarehouseResource|JsonResponse
    {
        try {
            return new WarehouseResource($this->locations->updateWarehouse($this->organization->id(), $warehouse, $request->validated(), $this->auditContext($request)));
        } catch (MasterDataConflictException $exception) {
            return $this->conflict($request, $exception);
        }
    }

    public function archive(ArchiveLocationRequest $request, string $warehouse): WarehouseResource|JsonResponse
    {
        try {
            return new WarehouseResource($this->locations->archiveWarehouse($this->organization->id(), $warehouse, (int) $request->validated('version'), $request->validated('reason'), $this->auditContext($request)));
        } catch (MasterDataConflictException $exception) {
            return $this->conflict($request, $exception);
        }
    }

    public function options(): JsonResponse
    {
        return response()->json(['data' => $this->locations->options($this->organization->id())]);
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
