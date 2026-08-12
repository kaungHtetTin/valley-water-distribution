<?php

namespace App\Http\Controllers\Api\V1\MasterData;

use App\Domain\MasterData\CatalogService;
use App\Domain\MasterData\Exceptions\MasterDataConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MasterData\ArchiveCatalogRecordRequest;
use App\Http\Requests\Api\V1\MasterData\ReviseSkuConversionRequest;
use App\Http\Requests\Api\V1\MasterData\StoreSkuRequest;
use App\Http\Requests\Api\V1\MasterData\UpdateSkuRequest;
use App\Http\Resources\Api\V1\SkuResource;
use App\Support\Tenancy\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SkuController extends Controller
{
    public function __construct(private readonly CatalogService $catalog, private readonly OrganizationContext $organization) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:180'],
            'status' => ['nullable', 'in:active,inactive,archived'],
            'sort' => ['nullable', 'in:code,name_en,size_label,status,updated_at'],
            'direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return SkuResource::collection($this->catalog->paginate($this->organization->id(), $filters));
    }

    public function store(StoreSkuRequest $request): JsonResponse
    {
        return (new SkuResource($this->catalog->create($this->organization->id(), $request->validated(), $this->auditContext($request))))
            ->response()->setStatusCode(201);
    }

    public function update(UpdateSkuRequest $request, string $sku): SkuResource|JsonResponse
    {
        try {
            return new SkuResource($this->catalog->update($this->organization->id(), $sku, $request->validated(), $this->auditContext($request)));
        } catch (MasterDataConflictException $exception) {
            return $this->conflict($request, $exception);
        }
    }

    public function archive(ArchiveCatalogRecordRequest $request, string $sku): SkuResource|JsonResponse
    {
        try {
            return new SkuResource($this->catalog->archive($this->organization->id(), $sku, (int) $request->validated('version'), $request->validated('reason'), $this->auditContext($request)));
        } catch (MasterDataConflictException $exception) {
            return $this->conflict($request, $exception);
        }
    }

    public function options(): JsonResponse
    {
        return response()->json(['data' => $this->catalog->options($this->organization->id())]);
    }

    public function reviseConversion(ReviseSkuConversionRequest $request, string $sku): SkuResource|JsonResponse
    {
        try {
            return new SkuResource($this->catalog->reviseConversion($this->organization->id(), $sku, $request->validated(), $this->auditContext($request)));
        } catch (MasterDataConflictException $exception) {
            return $this->conflict($request, $exception);
        }
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
