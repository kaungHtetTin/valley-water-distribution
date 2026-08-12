<?php

namespace App\Domain\MasterData;

use App\Domain\MasterData\Exceptions\MasterDataConflictException;
use App\Models\AuditEvent;
use App\Models\Branch;
use App\Models\BusinessCalendar;
use App\Models\DocumentSequence;
use App\Models\FiscalPeriod;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class OrganizationControlService
{
    public function dashboard(int $organizationId): array
    {
        return [
            'organization' => Organization::query()->findOrFail($organizationId),
            'calendars' => BusinessCalendar::query()->with(['branch:id,public_id,code,name_en,name_my', 'dates'])->where('organization_id', $organizationId)->orderBy('code')->get(),
            'periods' => FiscalPeriod::query()->where('organization_id', $organizationId)->orderBy('starts_on')->get(),
            'sequences' => DocumentSequence::query()->with('branch:id,public_id,code,name_en,name_my')->where('organization_id', $organizationId)->orderBy('document_type')->get(),
            'branches' => Branch::query()->where('organization_id', $organizationId)->where('status', 'active')->orderBy('code')->get(['public_id', 'code', 'name_en', 'name_my']),
        ];
    }

    public function updateOrganization(int $organizationId, array $attributes, array $auditContext): Organization
    {
        return DB::transaction(function () use ($organizationId, $attributes, $auditContext): Organization {
            $organization = Organization::query()->lockForUpdate()->findOrFail($organizationId);
            $this->assertVersion($organization, (int) $attributes['version'], 'Company settings');
            $duplicateCode = Organization::query()->where('code', $attributes['code'])->whereKeyNot($organizationId)->exists();
            if ($duplicateCode) {
                throw new MasterDataConflictException('This organization code is already in use.', 'duplicate_organization_code');
            }
            $before = $this->snapshot($organization);
            unset($attributes['version']);
            $organization->fill([...$attributes, 'lock_version' => $organization->lock_version + 1])->save();
            $this->audit('master_data.organization.updated', $organization, $before, $this->snapshot($organization), $auditContext);

            return $organization->refresh();
        });
    }

    public function saveCalendar(int $organizationId, ?string $publicId, array $attributes, array $auditContext): BusinessCalendar
    {
        return DB::transaction(function () use ($organizationId, $publicId, $attributes, $auditContext): BusinessCalendar {
            $branchId = $this->branchId($organizationId, $attributes['branch_public_id'] ?? null);
            $dates = $attributes['dates'];
            unset($attributes['branch_public_id'], $attributes['dates']);
            if ($publicId) {
                $calendar = BusinessCalendar::query()->where('organization_id', $organizationId)->where('public_id', $publicId)->lockForUpdate()->firstOrFail();
                $this->assertVersion($calendar, (int) $attributes['version'], 'Business calendar');
                $before = $this->calendarSnapshot($calendar);
                unset($attributes['version']);
                $calendar->fill([...$attributes, 'branch_id' => $branchId, 'lock_version' => $calendar->lock_version + 1])->save();
                $action = 'master_data.business_calendar.updated';
            } else {
                unset($attributes['version']);
                $calendar = BusinessCalendar::query()->create([...$attributes, 'organization_id' => $organizationId, 'branch_id' => $branchId]);
                $before = null;
                $action = 'master_data.business_calendar.created';
            }
            $this->syncCalendarDates($calendar, $dates);
            $calendar = $this->loadCalendar($calendar);
            $this->audit($action, $calendar, $before, $this->calendarSnapshot($calendar), $auditContext);

            return $calendar;
        });
    }

    public function saveFiscalPeriod(int $organizationId, ?string $publicId, array $attributes, array $auditContext): FiscalPeriod
    {
        return DB::transaction(function () use ($organizationId, $publicId, $attributes, $auditContext): FiscalPeriod {
            $period = $publicId ? FiscalPeriod::query()->where('organization_id', $organizationId)->where('public_id', $publicId)->lockForUpdate()->firstOrFail() : null;
            if ($period) {
                $this->assertVersion($period, (int) $attributes['version'], 'Fiscal period');
            }
            $overlaps = FiscalPeriod::query()->where('organization_id', $organizationId)->where('status', '!=', 'archived')
                ->when($period, fn ($query) => $query->whereKeyNot($period->id))
                ->whereDate('starts_on', '<=', $attributes['ends_on'])->whereDate('ends_on', '>=', $attributes['starts_on'])->exists();
            if ($overlaps) {
                throw new MasterDataConflictException('Fiscal periods cannot overlap.', 'fiscal_period_overlap');
            }
            if ($period) {
                $before = $this->snapshot($period);
                unset($attributes['version']);
                $period->fill([...$attributes, 'lock_version' => $period->lock_version + 1])->save();
                $action = 'master_data.fiscal_period.updated';
            } else {
                unset($attributes['version']);
                $period = FiscalPeriod::query()->create([...$attributes, 'organization_id' => $organizationId]);
                $before = null;
                $action = 'master_data.fiscal_period.created';
            }
            $this->audit($action, $period, $before, $this->snapshot($period), $auditContext);

            return $period->refresh();
        });
    }

    public function saveSequence(int $organizationId, ?string $publicId, array $attributes, array $auditContext): DocumentSequence
    {
        return DB::transaction(function () use ($organizationId, $publicId, $attributes, $auditContext): DocumentSequence {
            $branchId = $this->branchId($organizationId, $attributes['branch_public_id'] ?? null);
            $scopeKey = $branchId ? 'BRANCH:'.$branchId : 'ORG';
            $sequence = $publicId ? DocumentSequence::query()->where('organization_id', $organizationId)->where('public_id', $publicId)->lockForUpdate()->firstOrFail() : null;
            if ($sequence) {
                $this->assertVersion($sequence, (int) $attributes['version'], 'Document sequence');
            }
            $duplicate = DocumentSequence::query()->where('organization_id', $organizationId)->where('scope_key', $scopeKey)->where('document_type', $attributes['document_type'])
                ->when($sequence, fn ($query) => $query->whereKeyNot($sequence->id))->exists();
            if ($duplicate) {
                throw new MasterDataConflictException('This document type already has a sequence in the selected scope.', 'duplicate_document_sequence');
            }
            unset($attributes['branch_public_id']);
            if ($sequence) {
                $before = $this->snapshot($sequence);
                unset($attributes['version']);
                $sequence->fill([...$attributes, 'branch_id' => $branchId, 'scope_key' => $scopeKey, 'lock_version' => $sequence->lock_version + 1])->save();
                $action = 'master_data.document_sequence.updated';
            } else {
                unset($attributes['version']);
                $sequence = DocumentSequence::query()->create([...$attributes, 'organization_id' => $organizationId, 'branch_id' => $branchId, 'scope_key' => $scopeKey]);
                $before = null;
                $action = 'master_data.document_sequence.created';
            }
            $sequence->load('branch:id,public_id,code,name_en,name_my');
            $this->audit($action, $sequence, $before, $this->snapshot($sequence), $auditContext);

            return $sequence;
        });
    }

    public function archive(int $organizationId, string $type, string $publicId, int $version, string $reason, array $auditContext): Model
    {
        $models = ['calendars' => BusinessCalendar::class, 'periods' => FiscalPeriod::class, 'sequences' => DocumentSequence::class];
        abort_unless(isset($models[$type]), 404);

        return DB::transaction(function () use ($organizationId, $type, $publicId, $version, $reason, $auditContext, $models): Model {
            $record = $models[$type]::query()->where('organization_id', $organizationId)->where('public_id', $publicId)->lockForUpdate()->firstOrFail();
            $this->assertVersion($record, $version, 'Configuration record');
            $before = $this->snapshot($record);
            $record->update(['status' => 'archived', 'lock_version' => $record->lock_version + 1]);
            $auditContext['reason'] = $reason;
            $actionType = ['calendars' => 'business_calendar', 'periods' => 'fiscal_period', 'sequences' => 'document_sequence'][$type];
            $this->audit('master_data.'.$actionType.'.archived', $record, $before, $this->snapshot($record), $auditContext);

            return $type === 'calendars' ? $this->loadCalendar($record) : ($type === 'sequences' ? $record->load('branch:id,public_id,code,name_en,name_my') : $record);
        });
    }

    private function branchId(int $organizationId, ?string $publicId): ?int
    {
        if (! $publicId) {
            return null;
        }
        $id = Branch::query()->where('organization_id', $organizationId)->where('public_id', $publicId)->where('status', 'active')->value('id');
        abort_unless($id, 404);

        return (int) $id;
    }

    private function syncCalendarDates(BusinessCalendar $calendar, array $dates): void
    {
        $calendar->dates()->delete();
        foreach ($dates as $date) {
            $calendar->dates()->create(['organization_id' => $calendar->organization_id, 'calendar_date' => $date['date'], 'day_type' => $date['day_type'], 'name_en' => $date['name_en'], 'name_my' => $date['name_my'] ?? null]);
        }
    }

    private function loadCalendar(BusinessCalendar $calendar): BusinessCalendar
    {
        return $calendar->load(['branch:id,public_id,code,name_en,name_my', 'dates']);
    }

    private function assertVersion(Model $record, int $version, string $label): void
    {
        if ((int) $record->lock_version !== $version) {
            throw new MasterDataConflictException("This {$label} changed after it was opened. Refresh and review the latest values.");
        }
    }

    private function calendarSnapshot(BusinessCalendar $calendar): array
    {
        return [...$this->snapshot($calendar), 'dates' => $calendar->dates()->orderBy('calendar_date')->get()->toArray()];
    }

    private function snapshot(Model $record): array
    {
        return Arr::except($record->fresh()->toArray(), ['id', 'organization_id', 'branch_id']);
    }

    private function audit(string $action, Model $record, ?array $before, array $after, array $context): void
    {
        AuditEvent::query()->create([
            'organization_id' => $record instanceof Organization ? $record->id : $record->organization_id,
            'actor_user_id' => $context['actor_user_id'] ?? null,
            'action' => $action,
            'entity_type' => $record::class,
            'entity_public_id' => $record->public_id,
            'before_state' => $before,
            'after_state' => $after,
            'reason' => $context['reason'] ?? null,
            'correlation_id' => $context['correlation_id'] ?? null,
            'ip_address' => $context['ip_address'] ?? null,
        ]);
    }
}
