<?php

namespace App\Http\Controllers\Api\V1\MasterData;

use App\Domain\MasterData\Exceptions\MasterDataConflictException;
use App\Domain\MasterData\RouteTemplateService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MasterData\ArchiveLocationRequest;
use App\Http\Requests\Api\V1\MasterData\SaveRouteTemplateRequest;
use App\Models\RouteTemplate;
use App\Support\Tenancy\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RouteTemplateController extends Controller
{
    public function __construct(private readonly RouteTemplateService $templates, private readonly OrganizationContext $organization) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:160'], 'status' => ['nullable', 'in:active,inactive,archived']]);
        $data = $this->templates->dashboard($this->organization->id(), $filters);

        return response()->json(['data' => [
            'templates' => $data['templates']->map(fn (RouteTemplate $template) => $this->templateData($template)),
            'branches' => $data['branches'],
            'warehouses' => $data['warehouses']->map(fn ($warehouse) => ['public_id' => $warehouse->public_id, 'branch_public_id' => $warehouse->branch->public_id, 'code' => $warehouse->code, 'name_en' => $warehouse->name_en, 'name_my' => $warehouse->name_my]),
            'ways' => $data['ways'],
        ]]);
    }

    public function store(SaveRouteTemplateRequest $request): JsonResponse
    {
        try {
            return response()->json(['data' => $this->templateData($this->templates->save($this->organization->id(), null, $request->validated(), $this->auditContext($request)))], 201);
        } catch (MasterDataConflictException $exception) {
            return $this->conflict($request, $exception);
        }
    }

    public function update(SaveRouteTemplateRequest $request, string $template): JsonResponse
    {
        try {
            return response()->json(['data' => $this->templateData($this->templates->save($this->organization->id(), $template, $request->validated(), $this->auditContext($request)))]);
        } catch (MasterDataConflictException $exception) {
            return $this->conflict($request, $exception);
        }
    }

    public function archive(ArchiveLocationRequest $request, string $template): JsonResponse
    {
        try {
            return response()->json(['data' => $this->templateData($this->templates->archive($this->organization->id(), $template, (int) $request->validated('version'), $request->validated('reason'), $this->auditContext($request)))]);
        } catch (MasterDataConflictException $exception) {
            return $this->conflict($request, $exception);
        }
    }

    private function templateData(RouteTemplate $template): array
    {
        $reference = fn ($item) => ['id' => $item->public_id, 'code' => $item->code, 'name' => ['en' => $item->name_en, 'my-MM' => $item->name_my]];

        return ['id' => $template->public_id, 'code' => $template->code, 'name' => ['en' => $template->name_en, 'my-MM' => $template->name_my], 'description' => $template->description, 'branch' => $reference($template->branch), 'source_warehouse' => $reference($template->sourceWarehouse), 'service_days' => $template->service_days, 'departure_time' => $template->departure_time ? substr($template->departure_time, 0, 5) : null, 'estimated_duration_minutes' => $template->estimated_duration_minutes, 'effective_from' => $template->effective_from?->toDateString(), 'effective_to' => $template->effective_to?->toDateString(), 'ways' => $template->ways->map(fn ($way) => [...$reference($way), 'sequence' => $way->pivot->sequence]), 'status' => $template->status, 'version' => $template->lock_version];
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
