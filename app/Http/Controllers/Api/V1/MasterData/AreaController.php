<?php

namespace App\Http\Controllers\Api\V1\MasterData;

use App\Domain\MasterData\AreaService;
use App\Domain\MasterData\Exceptions\MasterDataConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MasterData\ArchiveAreaRequest;
use App\Http\Requests\Api\V1\MasterData\StoreAreaRequest;
use App\Http\Requests\Api\V1\MasterData\UpdateAreaRequest;
use App\Http\Resources\Api\V1\AreaResource;
use App\Support\Tenancy\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AreaController extends Controller
{
    public function __construct(
        private readonly AreaService $areas,
        private readonly OrganizationContext $organization,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', 'in:active,inactive,archived'],
            'sort' => ['nullable', 'in:code,name_en,name_my,sort_order,status,updated_at'],
            'direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return AreaResource::collection($this->areas->paginate($this->organization->id(), $filters));
    }

    public function store(StoreAreaRequest $request): JsonResponse
    {
        $area = $this->areas->create(
            $this->organization->id(),
            $request->validated(),
            $this->auditContext($request),
        );

        return (new AreaResource($area))->response()->setStatusCode(201);
    }

    public function show(string $area): AreaResource
    {
        return new AreaResource($this->areas->find($this->organization->id(), $area));
    }

    public function update(UpdateAreaRequest $request, string $area): AreaResource|JsonResponse
    {
        try {
            return new AreaResource($this->areas->update(
                $this->organization->id(),
                $area,
                $request->validated(),
                $this->auditContext($request),
            ));
        } catch (MasterDataConflictException $exception) {
            return $this->conflict($request, $exception);
        }
    }

    public function archive(ArchiveAreaRequest $request, string $area): AreaResource|JsonResponse
    {
        try {
            return new AreaResource($this->areas->archive(
                $this->organization->id(),
                $area,
                (int) $request->validated('version'),
                $request->validated('reason'),
                $this->auditContext($request),
            ));
        } catch (MasterDataConflictException $exception) {
            return $this->conflict($request, $exception);
        }
    }

    private function auditContext(Request $request): array
    {
        return [
            'actor_user_id' => $request->user()?->id,
            'correlation_id' => $request->attributes->get('correlation_id'),
            'ip_address' => $request->ip(),
        ];
    }

    private function conflict(Request $request, MasterDataConflictException $exception): JsonResponse
    {
        return response()->json([
            'message' => $exception->getMessage(),
            'code' => $exception->conflictCode,
            'correlation_id' => $request->attributes->get('correlation_id'),
        ], 409);
    }
}
