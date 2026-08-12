<?php

namespace App\Http\Controllers\Api\V1\MasterData;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Support\Tenancy\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditHistoryController extends Controller
{
    public function __construct(private readonly OrganizationContext $organization) {}

    public function __invoke(Request $request): JsonResponse
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:120'], 'action' => ['nullable', 'string', 'max:80'], 'entity_public_id' => ['nullable', 'string', 'size:26'], 'per_page' => ['nullable', 'integer', 'min:5', 'max:100']]);
        $page = AuditEvent::query()->where('organization_id', $this->organization->id())
            ->when($filters['search'] ?? null, fn ($query, $term) => $query->where(fn ($query) => $query->where('action', 'like', "%{$term}%")->orWhere('entity_type', 'like', "%{$term}%")->orWhere('reason', 'like', "%{$term}%")))
            ->when($filters['action'] ?? null, fn ($query, $action) => $query->where('action', $action))
            ->when($filters['entity_public_id'] ?? null, fn ($query, $id) => $query->where('entity_public_id', $id))
            ->latest('created_at')->paginate(min((int) ($filters['per_page'] ?? 25), 100));

        return response()->json(['data' => collect($page->items())->map(fn ($event) => ['id' => $event->public_id, 'action' => $event->action, 'entity_type' => class_basename($event->entity_type), 'entity_id' => $event->entity_public_id, 'before' => $event->before_state, 'after' => $event->after_state, 'reason' => $event->reason, 'correlation_id' => $event->correlation_id, 'created_at' => $event->created_at?->toIso8601String()]), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]]);
    }
}
