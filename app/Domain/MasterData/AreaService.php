<?php

namespace App\Domain\MasterData;

use App\Domain\MasterData\Exceptions\MasterDataConflictException;
use App\Models\Area;
use App\Models\AuditEvent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class AreaService
{
    private const SNAPSHOT_FIELDS = [
        'public_id', 'parent_area_id', 'code', 'name_en', 'name_my', 'description',
        'sort_order', 'lock_version', 'status', 'created_at', 'updated_at',
    ];

    public function paginate(int $organizationId, array $filters): LengthAwarePaginator
    {
        $sort = in_array($filters['sort'] ?? null, ['code', 'name_en', 'name_my', 'sort_order', 'status', 'updated_at'], true)
            ? $filters['sort']
            : 'sort_order';
        $direction = ($filters['direction'] ?? null) === 'desc' ? 'desc' : 'asc';
        $perPage = min(max((int) ($filters['per_page'] ?? 20), 5), 100);

        return Area::query()
            ->with('parent:id,public_id,name_en,name_my')
            ->where('organization_id', $organizationId)
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $term = '%'.trim($search).'%';
                $query->where(function ($query) use ($term): void {
                    $query->where('code', 'like', $term)
                        ->orWhere('name_en', 'like', $term)
                        ->orWhere('name_my', 'like', $term);
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $organizationId, string $publicId): Area
    {
        return Area::query()
            ->with('parent:id,public_id,name_en,name_my')
            ->where('organization_id', $organizationId)
            ->where('public_id', $publicId)
            ->firstOrFail();
    }

    public function create(int $organizationId, array $attributes, array $auditContext): Area
    {
        return DB::transaction(function () use ($organizationId, $attributes, $auditContext): Area {
            $attributes['organization_id'] = $organizationId;
            $attributes['parent_area_id'] = $this->resolveParentId($organizationId, $attributes['parent_area_public_id'] ?? null);
            unset($attributes['parent_area_public_id']);

            $area = Area::query()->create($attributes);
            $this->recordAudit('master_data.area.created', $area, null, $this->snapshot($area), $auditContext);

            return $area->refresh()->load('parent:id,public_id,name_en,name_my');
        });
    }

    public function update(int $organizationId, string $publicId, array $attributes, array $auditContext): Area
    {
        return DB::transaction(function () use ($organizationId, $publicId, $attributes, $auditContext): Area {
            $area = Area::query()
                ->where('organization_id', $organizationId)
                ->where('public_id', $publicId)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertVersion($area, (int) $attributes['version']);
            $before = $this->snapshot($area);

            $attributes['parent_area_id'] = $this->resolveParentId(
                $organizationId,
                $attributes['parent_area_public_id'] ?? null,
                $area->id,
            );
            $attributes['lock_version'] = $area->lock_version + 1;
            unset($attributes['parent_area_public_id'], $attributes['version']);

            $area->fill($attributes)->save();
            $this->recordAudit('master_data.area.updated', $area, $before, $this->snapshot($area), $auditContext);

            return $area->load('parent:id,public_id,name_en,name_my');
        });
    }

    public function archive(int $organizationId, string $publicId, int $version, string $reason, array $auditContext): Area
    {
        return DB::transaction(function () use ($organizationId, $publicId, $version, $reason, $auditContext): Area {
            $area = Area::query()
                ->where('organization_id', $organizationId)
                ->where('public_id', $publicId)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertVersion($area, $version);

            if ($area->status === 'archived') {
                return $area->load('parent:id,public_id,name_en,name_my');
            }

            $hasActiveChildren = Area::query()
                ->where('organization_id', $organizationId)
                ->where('parent_area_id', $area->id)
                ->where('status', 'active')
                ->exists();

            if ($hasActiveChildren) {
                throw new MasterDataConflictException('Archive active child Areas first.', 'active_children');
            }

            $before = $this->snapshot($area);
            $area->update(['status' => 'archived', 'lock_version' => $area->lock_version + 1]);
            $auditContext['reason'] = $reason;
            $this->recordAudit('master_data.area.archived', $area, $before, $this->snapshot($area), $auditContext);

            return $area->load('parent:id,public_id,name_en,name_my');
        });
    }

    private function assertVersion(Area $area, int $version): void
    {
        if ($area->lock_version !== $version) {
            throw new MasterDataConflictException('This Area changed after it was opened. Refresh and review the latest values.');
        }
    }

    private function resolveParentId(int $organizationId, ?string $publicId, ?int $excludeId = null): ?int
    {
        if (! $publicId) {
            return null;
        }

        $parent = Area::query()
            ->where('organization_id', $organizationId)
            ->where('public_id', $publicId)
            ->where('status', '!=', 'archived')
            ->first();

        if (! $parent) {
            throw (new ModelNotFoundException)->setModel(Area::class, [$publicId]);
        }

        if ($excludeId !== null && $this->wouldCreateCycle($organizationId, $parent->id, $excludeId)) {
            throw new MasterDataConflictException(
                'An Area cannot be its own parent or be placed below one of its descendants.',
                'invalid_parent_cycle',
            );
        }

        return $parent->id;
    }

    private function wouldCreateCycle(int $organizationId, int $candidateParentId, int $areaId): bool
    {
        $currentId = $candidateParentId;
        $visited = [];

        while ($currentId !== null) {
            if ($currentId === $areaId || isset($visited[$currentId])) {
                return true;
            }

            $visited[$currentId] = true;
            $currentId = Area::query()
                ->where('organization_id', $organizationId)
                ->whereKey($currentId)
                ->value('parent_area_id');
        }

        return false;
    }

    private function snapshot(Area $area): array
    {
        return Arr::only($area->fresh()->toArray(), self::SNAPSHOT_FIELDS);
    }

    private function recordAudit(string $action, Area $area, ?array $before, array $after, array $context): void
    {
        AuditEvent::query()->create([
            'organization_id' => $area->organization_id,
            'actor_user_id' => $context['actor_user_id'] ?? null,
            'action' => $action,
            'entity_type' => Area::class,
            'entity_public_id' => $area->public_id,
            'before_state' => $before,
            'after_state' => $after,
            'reason' => $context['reason'] ?? null,
            'correlation_id' => $context['correlation_id'] ?? null,
            'ip_address' => $context['ip_address'] ?? null,
        ]);
    }
}
