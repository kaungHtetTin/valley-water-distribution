<?php

namespace App\Http\Controllers\Api\V1\MasterData;

use App\Domain\MasterData\Exceptions\MasterDataConflictException;
use App\Domain\MasterData\OrganizationControlService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MasterData\ArchiveLocationRequest;
use App\Http\Requests\Api\V1\MasterData\SaveBusinessCalendarRequest;
use App\Http\Requests\Api\V1\MasterData\SaveDocumentSequenceRequest;
use App\Http\Requests\Api\V1\MasterData\SaveFiscalPeriodRequest;
use App\Http\Requests\Api\V1\MasterData\UpdateOrganizationSettingsRequest;
use App\Models\BusinessCalendar;
use App\Models\DocumentSequence;
use App\Models\FiscalPeriod;
use App\Models\Organization;
use App\Support\Tenancy\OrganizationContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationControlController extends Controller
{
    public function __construct(private readonly OrganizationControlService $controls, private readonly OrganizationContext $organization) {}

    public function index(): JsonResponse
    {
        $data = $this->controls->dashboard($this->organization->id());

        return response()->json(['data' => [
            'organization' => $this->organizationData($data['organization']),
            'calendars' => $data['calendars']->map(fn (BusinessCalendar $calendar) => $this->calendarData($calendar)),
            'periods' => $data['periods']->map(fn (FiscalPeriod $period) => $this->periodData($period)),
            'sequences' => $data['sequences']->map(fn (DocumentSequence $sequence) => $this->sequenceData($sequence)),
            'branches' => $data['branches'],
        ]]);
    }

    public function updateOrganization(UpdateOrganizationSettingsRequest $request): JsonResponse
    {
        try {
            $record = $this->controls->updateOrganization($this->organization->id(), $request->validated(), $this->auditContext($request));

            return response()->json(['data' => $this->organizationData($record)]);
        } catch (MasterDataConflictException $exception) {
            return $this->conflict($request, $exception);
        }
    }

    public function storeCalendar(SaveBusinessCalendarRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->calendarData($this->controls->saveCalendar($this->organization->id(), null, $request->validated(), $this->auditContext($request)))], 201);
    }

    public function updateCalendar(SaveBusinessCalendarRequest $request, string $calendar): JsonResponse
    {
        try {
            return response()->json(['data' => $this->calendarData($this->controls->saveCalendar($this->organization->id(), $calendar, $request->validated(), $this->auditContext($request)))]);
        } catch (MasterDataConflictException $exception) {
            return $this->conflict($request, $exception);
        }
    }

    public function storePeriod(SaveFiscalPeriodRequest $request): JsonResponse
    {
        try {
            return response()->json(['data' => $this->periodData($this->controls->saveFiscalPeriod($this->organization->id(), null, $request->validated(), $this->auditContext($request)))], 201);
        } catch (MasterDataConflictException $exception) {
            return $this->conflict($request, $exception);
        }
    }

    public function updatePeriod(SaveFiscalPeriodRequest $request, string $period): JsonResponse
    {
        try {
            return response()->json(['data' => $this->periodData($this->controls->saveFiscalPeriod($this->organization->id(), $period, $request->validated(), $this->auditContext($request)))]);
        } catch (MasterDataConflictException $exception) {
            return $this->conflict($request, $exception);
        }
    }

    public function storeSequence(SaveDocumentSequenceRequest $request): JsonResponse
    {
        try {
            return response()->json(['data' => $this->sequenceData($this->controls->saveSequence($this->organization->id(), null, $request->validated(), $this->auditContext($request)))], 201);
        } catch (MasterDataConflictException $exception) {
            return $this->conflict($request, $exception);
        }
    }

    public function updateSequence(SaveDocumentSequenceRequest $request, string $sequence): JsonResponse
    {
        try {
            return response()->json(['data' => $this->sequenceData($this->controls->saveSequence($this->organization->id(), $sequence, $request->validated(), $this->auditContext($request)))]);
        } catch (MasterDataConflictException $exception) {
            return $this->conflict($request, $exception);
        }
    }

    public function archive(ArchiveLocationRequest $request, string $type, string $record): JsonResponse
    {
        try {
            $archived = $this->controls->archive($this->organization->id(), $type, $record, (int) $request->validated('version'), $request->validated('reason'), $this->auditContext($request));
            $data = match ($type) {
                'calendars' => $this->calendarData($archived),
                'periods' => $this->periodData($archived),
                'sequences' => $this->sequenceData($archived),
                default => abort(404),
            };

            return response()->json(['data' => $data]);
        } catch (MasterDataConflictException $exception) {
            return $this->conflict($request, $exception);
        }
    }

    private function organizationData(Organization $record): array
    {
        return ['id' => $record->public_id, 'code' => $record->code, 'name' => $record->name, 'legal_name' => $record->legal_name, 'registration_number' => $record->registration_number, 'tax_identifier' => $record->tax_identifier, 'phone' => $record->phone, 'email' => $record->email, 'address' => $record->address, 'default_locale' => $record->default_locale, 'document_locale' => $record->document_locale, 'currency' => $record->currency, 'inventory_valuation_method' => $record->inventory_valuation_method, 'timezone' => $record->timezone, 'version' => $record->lock_version];
    }

    private function calendarData(Model $record): array
    {
        return ['id' => $record->public_id, 'code' => $record->code, 'name' => ['en' => $record->name_en, 'my-MM' => $record->name_my], 'branch' => $record->branch ? ['id' => $record->branch->public_id, 'code' => $record->branch->code, 'name' => ['en' => $record->branch->name_en, 'my-MM' => $record->branch->name_my]] : null, 'weekend_days' => $record->weekend_days, 'effective_from' => $record->effective_from?->toDateString(), 'effective_to' => $record->effective_to?->toDateString(), 'dates' => $record->dates->map(fn ($date) => ['date' => $date->calendar_date->toDateString(), 'day_type' => $date->day_type, 'name' => ['en' => $date->name_en, 'my-MM' => $date->name_my]]), 'status' => $record->status, 'version' => $record->lock_version];
    }

    private function periodData(Model $record): array
    {
        return ['id' => $record->public_id, 'code' => $record->code, 'name' => $record->name, 'starts_on' => $record->starts_on?->toDateString(), 'ends_on' => $record->ends_on?->toDateString(), 'status' => $record->status, 'version' => $record->lock_version];
    }

    private function sequenceData(Model $record): array
    {
        return ['id' => $record->public_id, 'document_type' => $record->document_type, 'name' => $record->name, 'branch' => $record->branch ? ['id' => $record->branch->public_id, 'code' => $record->branch->code, 'name' => ['en' => $record->branch->name_en, 'my-MM' => $record->branch->name_my]] : null, 'prefix' => $record->prefix, 'suffix' => $record->suffix, 'padding' => $record->padding, 'next_number' => $record->next_number, 'reset_policy' => $record->reset_policy, 'preview' => ($record->prefix ?? '').str_pad((string) $record->next_number, $record->padding, '0', STR_PAD_LEFT).($record->suffix ?? ''), 'status' => $record->status, 'version' => $record->lock_version];
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
