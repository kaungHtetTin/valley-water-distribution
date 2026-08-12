<?php

namespace App\Http\Controllers\Api\V1\MasterData;

use App\Domain\MasterData\Exceptions\MasterDataConflictException;
use App\Domain\MasterData\FoundationMasterService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MasterData\ArchiveLocationRequest;
use App\Http\Requests\Api\V1\MasterData\PreviewFoundationImportRequest;
use App\Http\Requests\Api\V1\MasterData\SaveFoundationMasterRequest;
use App\Models\FoundationMasterRecord;
use App\Models\MasterImportBatch;
use App\Support\Tenancy\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FoundationMasterController extends Controller
{
    public function __construct(private readonly FoundationMasterService $masters, private readonly OrganizationContext $organization) {}

    public function index(Request $request, string $type): JsonResponse
    {
        $request->validate(['search' => ['nullable', 'string', 'max:120'], 'status' => ['nullable', 'in:active,inactive,archived'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $listing = $this->masters->listing($this->organization->id(), $type, $request->only(['search', 'status', 'per_page']));
        $page = $listing['records'];

        return response()->json(['data' => collect($page->items())->map($this->recordData(...)), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()], 'options' => $listing['options'], 'types' => FoundationMasterRecord::TYPES]);
    }

    public function store(SaveFoundationMasterRequest $request, string $type): JsonResponse
    {
        return $this->saveResponse($request, fn () => $this->masters->save($this->organization->id(), $type, null, $request->validated(), $this->auditContext($request)), 201);
    }

    public function update(SaveFoundationMasterRequest $request, string $type, string $record): JsonResponse
    {
        return $this->saveResponse($request, fn () => $this->masters->save($this->organization->id(), $type, $record, $request->validated(), $this->auditContext($request)));
    }

    public function archive(ArchiveLocationRequest $request, string $type, string $record): JsonResponse
    {
        return $this->saveResponse($request, fn () => $this->masters->archive($this->organization->id(), $type, $record, (int) $request->validated('version'), $request->validated('reason'), $this->auditContext($request)));
    }

    public function previewImport(PreviewFoundationImportRequest $request, string $type): JsonResponse
    {
        $batch = $this->masters->previewImport($this->organization->id(), $type, $request->validated('source_name'), $request->validated('rows'), $this->auditContext($request));

        return response()->json(['data' => $this->batchData($batch)], 201);
    }

    public function commitImport(Request $request, string $batch): JsonResponse
    {
        try {
            return response()->json(['data' => $this->batchData($this->masters->commitImport($this->organization->id(), $batch, $this->auditContext($request)))]);
        } catch (MasterDataConflictException $exception) {
            return $this->conflict($request, $exception);
        }
    }

    public function export(string $type): StreamedResponse
    {
        return response()->streamDownload(function () use ($type): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['code', 'name_en', 'name_my', 'classification', 'phone', 'email', 'registration_number', 'status']);
            foreach ($this->masters->exportRows($this->organization->id(), $type) as $record) {
                fputcsv($output, [$record->code, $record->name_en, $record->name_my, $record->classification, $record->phone, $record->email, $record->registration_number, $record->status]);
            }
            fclose($output);
        }, "{$type}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function recordData(FoundationMasterRecord $record): array
    {
        $reference = fn ($item) => $item ? ['id' => $item->public_id, 'code' => $item->code, 'name' => ['en' => $item->name_en, 'my-MM' => $item->name_my]] : null;

        return ['id' => $record->public_id, 'type' => $record->type, 'code' => $record->code, 'name' => ['en' => $record->name_en, 'my-MM' => $record->name_my], 'classification' => $record->classification, 'branch' => $reference($record->branch), 'area' => $reference($record->area), 'way' => $reference($record->way), 'price_book' => $reference($record->priceBook), 'parent' => $reference($record->parent), 'phone' => $record->phone, 'email' => $record->email, 'address' => $record->address, 'registration_number' => $record->registration_number, 'metadata' => $record->metadata, 'sort_order' => $record->sort_order, 'children_count' => $record->children_count, 'status' => $record->status, 'version' => $record->lock_version];
    }

    private function batchData(MasterImportBatch $batch): array
    {
        return ['id' => $batch->public_id, 'type' => $batch->master_type, 'source_name' => $batch->source_name, 'status' => $batch->status, 'total_rows' => $batch->total_rows, 'valid_rows' => $batch->valid_rows, 'invalid_rows' => $batch->invalid_rows, 'errors' => $batch->errors, 'committed_at' => $batch->committed_at?->toIso8601String()];
    }

    private function saveResponse(Request $request, callable $callback, int $status = 200): JsonResponse
    {
        try {
            return response()->json(['data' => $this->recordData($callback())], $status);
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
