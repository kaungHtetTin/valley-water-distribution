<?php

namespace App\Domain\MasterData;

use App\Domain\MasterData\Exceptions\MasterDataConflictException;
use App\Models\Area;
use App\Models\AuditEvent;
use App\Models\Branch;
use App\Models\FoundationMasterRecord;
use App\Models\MasterImportBatch;
use App\Models\PriceBook;
use App\Models\Way;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class FoundationMasterService
{
    public function listing(int $organizationId, string $type, array $filters): array
    {
        $query = $this->query($organizationId, $type)
            ->with(['branch:id,public_id,code,name_en,name_my', 'area:id,public_id,code,name_en,name_my', 'way:id,public_id,code,name_en,name_my', 'priceBook:id,public_id,code,name_en,name_my', 'parent:id,public_id,code,name_en,name_my'])
            ->withCount(['children' => fn ($query) => $query->where('status', '!=', 'archived')]);
        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(fn ($query) => $query->where('code', 'like', "%{$search}%")->orWhere('name_en', 'like', "%{$search}%")->orWhere('name_my', 'like', "%{$search}%"));
        }
        if ($status = $filters['status'] ?? null) {
            $query->where('status', $status);
        }

        return [
            'records' => $query->orderBy('sort_order')->orderBy('code')->paginate(min((int) ($filters['per_page'] ?? 25), 100)),
            'options' => $this->options($organizationId),
        ];
    }

    public function save(int $organizationId, string $type, ?string $publicId, array $attributes, array $auditContext): FoundationMasterRecord
    {
        return DB::transaction(function () use ($organizationId, $type, $publicId, $attributes, $auditContext): FoundationMasterRecord {
            $record = $publicId ? $this->query($organizationId, $type)->where('public_id', $publicId)->lockForUpdate()->firstOrFail() : null;
            if ($record && (int) $record->lock_version !== (int) $attributes['version']) {
                throw new MasterDataConflictException('This master record changed after it was opened.');
            }
            $values = $this->resolveReferences($organizationId, $type, $attributes);
            if ($this->query($organizationId, $type)->when($record, fn ($query) => $query->whereKeyNot($record->id))->where('code', $values['code'])->exists()) {
                throw new MasterDataConflictException('This code already exists for the selected master type.', 'duplicate_foundation_master');
            }
            if ($record && isset($values['parent_id'])) {
                $this->assertNoParentCycle($record, $values['parent_id']);
            }

            $before = $record ? $this->snapshot($record) : null;
            unset($values['version']);
            if ($record) {
                $record->fill([...$values, 'lock_version' => $record->lock_version + 1])->save();
                $verb = 'updated';
            } else {
                $record = FoundationMasterRecord::query()->create([...$values, 'organization_id' => $organizationId, 'type' => $type]);
                $verb = 'created';
            }
            $record = $this->load($record);
            $this->audit("master_data.foundation_master.{$verb}", $record, $before, $this->snapshot($record), $auditContext);

            return $record;
        });
    }

    public function archive(int $organizationId, string $type, string $publicId, int $version, string $reason, array $auditContext): FoundationMasterRecord
    {
        return DB::transaction(function () use ($organizationId, $type, $publicId, $version, $reason, $auditContext): FoundationMasterRecord {
            $record = $this->query($organizationId, $type)->where('public_id', $publicId)->lockForUpdate()->firstOrFail();
            if ((int) $record->lock_version !== $version) {
                throw new MasterDataConflictException('This master record changed after it was opened.');
            }
            if ($record->children()->where('status', '!=', 'archived')->exists()) {
                throw new MasterDataConflictException('Archive dependent child records first.', 'foundation_master_has_children');
            }
            $before = $this->snapshot($record);
            $record->update(['status' => 'archived', 'lock_version' => $record->lock_version + 1]);
            $auditContext['reason'] = $reason;
            $this->audit('master_data.foundation_master.archived', $record, $before, $this->snapshot($record), $auditContext);

            return $this->load($record);
        });
    }

    public function previewImport(int $organizationId, string $type, string $sourceName, array $rows, array $auditContext): MasterImportBatch
    {
        $seen = [];
        $normalized = [];
        $errors = [];
        foreach ($rows as $index => $row) {
            $row['code'] = strtoupper(trim($row['code']));
            $row['status'] = $row['status'] ?? 'active';
            $normalized[] = $row;
            if (! preg_match('/^[A-Z0-9_-]+$/', $row['code'])) {
                $errors[$index][] = 'Code may contain only letters, numbers, underscores, and hyphens.';
            }
            if (isset($seen[$row['code']])) {
                $errors[$index][] = 'Duplicate code in import file.';
            }
            $seen[$row['code']] = true;
            if ($this->query($organizationId, $type)->where('code', $row['code'])->exists()) {
                $errors[$index][] = 'Code already exists.';
            }
        }
        $batch = MasterImportBatch::query()->create([
            'organization_id' => $organizationId, 'actor_user_id' => $auditContext['actor_user_id'] ?? null, 'master_type' => $type,
            'source_name' => $sourceName, 'status' => count($errors) ? 'invalid' : 'previewed', 'total_rows' => count($rows),
            'valid_rows' => count($rows) - count($errors), 'invalid_rows' => count($errors), 'rows' => $normalized, 'errors' => $errors ?: null,
        ]);
        $this->audit('master_data.foundation_import.previewed', $batch, null, Arr::except($batch->toArray(), ['id', 'organization_id', 'rows']), $auditContext);

        return $batch;
    }

    public function commitImport(int $organizationId, string $batchPublicId, array $auditContext): MasterImportBatch
    {
        return DB::transaction(function () use ($organizationId, $batchPublicId, $auditContext): MasterImportBatch {
            $batch = MasterImportBatch::query()->where('organization_id', $organizationId)->where('public_id', $batchPublicId)->lockForUpdate()->firstOrFail();
            if ($batch->status !== 'previewed' || $batch->invalid_rows > 0) {
                throw new MasterDataConflictException('Only a valid preview can be committed.', 'invalid_import_batch');
            }
            foreach ($batch->rows as $row) {
                if ($this->query($organizationId, $batch->master_type)->where('code', $row['code'])->exists()) {
                    throw new MasterDataConflictException('Import data changed after preview. Preview the file again.', 'import_data_changed');
                }
                FoundationMasterRecord::query()->create([...$row, 'organization_id' => $organizationId, 'type' => $batch->master_type, 'sort_order' => 0]);
            }
            $batch->update(['status' => 'committed', 'committed_at' => now()]);
            $this->audit('master_data.foundation_import.committed', $batch, null, Arr::except($batch->fresh()->toArray(), ['id', 'organization_id', 'rows']), $auditContext);

            return $batch->fresh();
        });
    }

    public function exportRows(int $organizationId, string $type): iterable
    {
        return $this->query($organizationId, $type)->orderBy('code')->cursor();
    }

    private function resolveReferences(int $organizationId, string $type, array $attributes): array
    {
        return [
            ...Arr::except($attributes, ['branch_public_id', 'area_public_id', 'way_public_id', 'price_book_public_id', 'parent_public_id']),
            'branch_id' => $this->referenceId(Branch::class, $organizationId, $attributes['branch_public_id'] ?? null),
            'area_id' => $this->referenceId(Area::class, $organizationId, $attributes['area_public_id'] ?? null),
            'way_id' => $this->referenceId(Way::class, $organizationId, $attributes['way_public_id'] ?? null),
            'price_book_id' => $this->referenceId(PriceBook::class, $organizationId, $attributes['price_book_public_id'] ?? null),
            'parent_id' => ($attributes['parent_public_id'] ?? null) ? $this->query($organizationId, $type)->where('public_id', $attributes['parent_public_id'])->where('status', 'active')->value('id') : null,
        ];
    }

    private function options(int $organizationId): array
    {
        $select = ['public_id', 'code', 'name_en', 'name_my'];

        return [
            'branches' => Branch::query()->where('organization_id', $organizationId)->where('status', 'active')->orderBy('code')->get($select),
            'areas' => Area::query()->where('organization_id', $organizationId)->where('status', 'active')->orderBy('code')->get($select),
            'ways' => Way::query()->where('organization_id', $organizationId)->where('status', 'active')->orderBy('code')->get($select),
            'price_books' => PriceBook::query()->where('organization_id', $organizationId)->where('status', 'active')->orderBy('code')->get($select),
        ];
    }

    private function referenceId(string $model, int $organizationId, ?string $publicId): ?int
    {
        if (! $publicId) {
            return null;
        }

        return $model::query()->where('organization_id', $organizationId)->where('public_id', $publicId)->where('status', 'active')->value('id') ?: abort(404);
    }

    private function assertNoParentCycle(FoundationMasterRecord $record, ?int $parentId): void
    {
        while ($parentId) {
            if ($parentId === $record->id) {
                throw new MasterDataConflictException('A record cannot be placed below itself.', 'foundation_master_parent_cycle');
            }
            $parentId = FoundationMasterRecord::query()->whereKey($parentId)->value('parent_id');
        }
    }

    private function query(int $organizationId, string $type): Builder
    {
        abort_unless(in_array($type, FoundationMasterRecord::TYPES, true), 404);

        return FoundationMasterRecord::query()->where('organization_id', $organizationId)->where('type', $type);
    }

    private function load(FoundationMasterRecord $record): FoundationMasterRecord
    {
        return $record->refresh()->load(['branch:id,public_id,code,name_en,name_my', 'area:id,public_id,code,name_en,name_my', 'way:id,public_id,code,name_en,name_my', 'priceBook:id,public_id,code,name_en,name_my', 'parent:id,public_id,code,name_en,name_my'])->loadCount(['children' => fn ($query) => $query->where('status', '!=', 'archived')]);
    }

    private function snapshot($record): array
    {
        return Arr::except($record->fresh()->toArray(), ['id', 'organization_id', 'branch_id', 'area_id', 'way_id', 'price_book_id', 'parent_id']);
    }

    private function audit(string $action, $record, ?array $before, array $after, array $context): void
    {
        AuditEvent::query()->create(['organization_id' => $record->organization_id, 'actor_user_id' => $context['actor_user_id'] ?? null, 'action' => $action, 'entity_type' => $record::class, 'entity_public_id' => $record->public_id, 'before_state' => $before, 'after_state' => $after, 'reason' => $context['reason'] ?? null, 'correlation_id' => $context['correlation_id'] ?? null, 'ip_address' => $context['ip_address'] ?? null]);
    }
}
