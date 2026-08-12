<?php

namespace App\Http\Controllers\Api\V1\MasterData;

use App\Domain\MasterData\Exceptions\MasterDataConflictException;
use App\Domain\MasterData\PricingControlService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PriceBookItemResource;
use App\Models\PriceBookAssignment;
use App\Models\ProductCostHistory;
use App\Support\Tenancy\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PricingControlController extends Controller
{
    public function __construct(private readonly PricingControlService $pricing, private readonly OrganizationContext $organization) {}

    public function index(): JsonResponse
    {
        $data = $this->pricing->dashboard($this->organization->id());

        return response()->json(['data' => ['assignments' => $data['assignments']->map($this->assignmentData(...)), 'costs' => $data['costs']->map($this->costData(...)), 'price_books' => $data['price_books'], 'customers' => $data['customers'], 'ways' => $data['ways'], 'warehouses' => $data['warehouses'], 'skus' => $data['skus']]]);
    }

    public function storeAssignment(Request $request): JsonResponse
    {
        return $this->assignmentResponse($request, null, 201);
    }

    public function updateAssignment(Request $request, string $assignment): JsonResponse
    {
        return $this->assignmentResponse($request, $assignment);
    }

    public function archiveAssignment(Request $request, string $assignment): JsonResponse
    {
        $values = $request->validate(['version' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'min:3', 'max:500']]);
        try {
            return response()->json(['data' => $this->assignmentData($this->pricing->archiveAssignment($this->organization->id(), $assignment, $values['version'], $values['reason'], $this->context($request)))]);
        } catch (MasterDataConflictException $exception) {
            return $this->conflict($exception);
        }
    }

    public function storeCost(Request $request): JsonResponse
    {
        $organizationId = $this->organization->id();
        $values = $request->validate([
            'warehouse_public_id' => ['required', Rule::exists('warehouses', 'public_id')->where(fn ($query) => $query->where('organization_id', $organizationId)->where('status', 'active'))],
            'sku_public_id' => ['required', Rule::exists('skus', 'public_id')->where(fn ($query) => $query->where('organization_id', $organizationId)->where('status', 'active'))],
            'unit_cost_minor' => ['required', 'integer', 'min:0'], 'currency' => ['required', 'string', 'size:3'],
            'valuation_method' => ['required', 'in:weighted_average'], 'effective_from' => ['required', 'date'], 'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'], 'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);
        try {
            return response()->json(['data' => $this->costData($this->pricing->createCost($organizationId, $values, $this->context($request)))], 201);
        } catch (MasterDataConflictException $exception) {
            return $this->conflict($exception);
        }
    }

    public function approveCost(Request $request, string $cost): JsonResponse
    {
        $values = $request->validate(['version' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'min:3', 'max:500']]);
        $limit = $request->attributes->get('approval_limit_minor');
        if ($limit !== null && ProductCostHistory::query()->where('organization_id', $this->organization->id())->where('public_id', $cost)->where('unit_cost_minor', '>', $limit)->exists()) {
            return response()->json(['message' => 'The cost exceeds your approval threshold.', 'code' => 'approval_limit_exceeded'], 403);
        }
        try {
            return response()->json(['data' => $this->costData($this->pricing->approveCost($this->organization->id(), $cost, $values['version'], $values['reason'], $this->context($request)))]);
        } catch (MasterDataConflictException $exception) {
            return $this->conflict($exception);
        }
    }

    public function resolve(Request $request): JsonResponse
    {
        $values = $request->validate(['customer' => ['required', 'string', 'size:26'], 'sku' => ['required', 'string', 'size:26'], 'uom' => ['required', 'string', 'size:26'], 'quantity' => ['required', 'numeric', 'gt:0'], 'date' => ['required', 'date']]);
        $price = $this->pricing->resolve($this->organization->id(), $values['customer'], $values['sku'], $values['uom'], (float) $values['quantity'], $values['date']);

        return $price ? (new PriceBookItemResource($price))->response() : response()->json(['data' => null, 'message' => 'No approved effective price matched.']);
    }

    private function assignmentResponse(Request $request, ?string $publicId, int $status = 200): JsonResponse
    {
        $values = $request->validate(['price_book_public_id' => ['required', 'string', 'size:26'], 'target_type' => ['required', 'in:customer,customer_classification,way'], 'target_key' => ['required', 'string', 'max:80'], 'priority' => ['required', 'integer', 'min:0', 'max:9999'], 'effective_from' => ['required', 'date'], 'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'], 'status' => ['required', 'in:active,inactive'], 'version' => [Rule::requiredIf($publicId !== null), 'nullable', 'integer', 'min:1']]);
        try {
            return response()->json(['data' => $this->assignmentData($this->pricing->saveAssignment($this->organization->id(), $publicId, $values, $this->context($request)))], $status);
        } catch (MasterDataConflictException $exception) {
            return $this->conflict($exception);
        }
    }

    private function assignmentData(PriceBookAssignment $record): array
    {
        return ['id' => $record->public_id, 'price_book' => ['id' => $record->priceBook->public_id, 'code' => $record->priceBook->code, 'name' => ['en' => $record->priceBook->name_en, 'my-MM' => $record->priceBook->name_my], 'price_type' => $record->priceBook->priceType->code], 'target_type' => $record->target_type, 'target_key' => $record->target_key, 'priority' => $record->priority, 'effective_from' => $record->effective_from?->toDateString(), 'effective_to' => $record->effective_to?->toDateString(), 'status' => $record->status, 'version' => $record->lock_version];
    }

    private function costData(ProductCostHistory $record): array
    {
        $reference = fn ($item) => ['id' => $item->public_id, 'code' => $item->code, 'name' => ['en' => $item->name_en, 'my-MM' => $item->name_my]];

        return ['id' => $record->public_id, 'warehouse' => $reference($record->warehouse), 'sku' => $reference($record->sku), 'unit_cost_minor' => $record->unit_cost_minor, 'currency' => $record->currency, 'valuation_method' => $record->valuation_method, 'effective_from' => $record->effective_from?->toDateString(), 'effective_to' => $record->effective_to?->toDateString(), 'approval_status' => $record->approval_status, 'reason' => $record->reason, 'status' => $record->status, 'version' => $record->lock_version];
    }

    private function context(Request $request): array
    {
        return ['actor_user_id' => $request->user()?->id, 'correlation_id' => $request->attributes->get('correlation_id'), 'ip_address' => $request->ip()];
    }

    private function conflict(MasterDataConflictException $exception): JsonResponse
    {
        return response()->json(['message' => $exception->getMessage(), 'code' => $exception->conflictCode], 409);
    }
}
