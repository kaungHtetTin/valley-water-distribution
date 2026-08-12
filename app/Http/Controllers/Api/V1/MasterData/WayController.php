<?php

namespace App\Http\Controllers\Api\V1\MasterData;

use App\Domain\MasterData\Exceptions\MasterDataConflictException;
use App\Domain\MasterData\WayService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MasterData\ArchiveWayRequest;
use App\Http\Requests\Api\V1\MasterData\StoreWayRequest;
use App\Http\Requests\Api\V1\MasterData\UpdateWayRequest;
use App\Http\Resources\Api\V1\WayResource;
use App\Support\Tenancy\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WayController extends Controller
{
    public function __construct(private readonly WayService $ways, private readonly OrganizationContext $organization) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', 'in:active,inactive,archived'],
            'area' => ['nullable', 'string', 'size:26'],
            'sort' => ['nullable', 'in:code,name_en,name_my,status,updated_at'],
            'direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return WayResource::collection($this->ways->paginate($this->organization->id(), $filters));
    }

    public function store(StoreWayRequest $request): JsonResponse
    {
        return (new WayResource($this->ways->create($this->organization->id(), $request->validated(), $this->auditContext($request))))->response()->setStatusCode(201);
    }

    public function update(UpdateWayRequest $request, string $way): WayResource|JsonResponse
    {
        try {
            return new WayResource($this->ways->update($this->organization->id(), $way, $request->validated(), $this->auditContext($request)));
        } catch (MasterDataConflictException $exception) {
            return $this->conflict($request, $exception);
        }
    }

    public function archive(ArchiveWayRequest $request, string $way): WayResource|JsonResponse
    {
        try {
            return new WayResource($this->ways->archive($this->organization->id(), $way, (int) $request->validated('version'), $request->validated('reason'), $this->auditContext($request)));
        } catch (MasterDataConflictException $exception) {
            return $this->conflict($request, $exception);
        }
    }

    public function options(): JsonResponse
    {
        return response()->json(['data' => $this->ways->options($this->organization->id())]);
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
