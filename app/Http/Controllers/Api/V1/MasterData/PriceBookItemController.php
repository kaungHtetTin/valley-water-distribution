<?php

namespace App\Http\Controllers\Api\V1\MasterData;

use App\Domain\MasterData\Exceptions\MasterDataConflictException;
use App\Domain\MasterData\PriceBookItemService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MasterData\ArchiveCatalogRecordRequest;
use App\Http\Requests\Api\V1\MasterData\StorePriceBookItemRequest;
use App\Http\Requests\Api\V1\MasterData\UpdatePriceBookItemRequest;
use App\Http\Resources\Api\V1\PriceBookItemResource;
use App\Models\PriceBookItem;
use App\Support\Tenancy\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PriceBookItemController extends Controller
{
    public function __construct(private readonly PriceBookItemService $prices, private readonly OrganizationContext $organization) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:180'],
            'status' => ['nullable', 'in:active,inactive,archived'],
            'price_book' => ['nullable', 'string', 'size:26'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return PriceBookItemResource::collection($this->prices->paginate($this->organization->id(), $filters));
    }

    public function store(StorePriceBookItemRequest $request): PriceBookItemResource|JsonResponse
    {
        try {
            return (new PriceBookItemResource($this->prices->create($this->organization->id(), $request->validated(), $this->auditContext($request))))
                ->response()->setStatusCode(201);
        } catch (MasterDataConflictException $exception) {
            return $this->conflict($request, $exception);
        }
    }

    public function update(UpdatePriceBookItemRequest $request, string $price): PriceBookItemResource|JsonResponse
    {
        try {
            return new PriceBookItemResource($this->prices->update($this->organization->id(), $price, $request->validated(), $this->auditContext($request)));
        } catch (MasterDataConflictException $exception) {
            return $this->conflict($request, $exception);
        }
    }

    public function archive(ArchiveCatalogRecordRequest $request, string $price): PriceBookItemResource|JsonResponse
    {
        try {
            return new PriceBookItemResource($this->prices->archive($this->organization->id(), $price, (int) $request->validated('version'), $request->validated('reason'), $this->auditContext($request)));
        } catch (MasterDataConflictException $exception) {
            return $this->conflict($request, $exception);
        }
    }

    public function approve(ArchiveCatalogRecordRequest $request, string $price): PriceBookItemResource|JsonResponse
    {
        $limit = $request->attributes->get('approval_limit_minor');
        if ($limit !== null && PriceBookItem::query()->where('organization_id', $this->organization->id())->where('public_id', $price)->where('unit_price_minor', '>', $limit)->exists()) {
            return response()->json(['message' => 'The price exceeds your approval threshold.', 'code' => 'approval_limit_exceeded'], 403);
        }
        try {
            return new PriceBookItemResource($this->prices->approve($this->organization->id(), $price, (int) $request->validated('version'), $request->validated('reason'), $this->auditContext($request)));
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
