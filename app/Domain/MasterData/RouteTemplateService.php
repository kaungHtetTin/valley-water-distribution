<?php

namespace App\Domain\MasterData;

use App\Domain\MasterData\Exceptions\MasterDataConflictException;
use App\Models\AuditEvent;
use App\Models\Branch;
use App\Models\RouteTemplate;
use App\Models\Warehouse;
use App\Models\Way;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class RouteTemplateService
{
    public function dashboard(int $organizationId, array $filters = []): array
    {
        return [
            'templates' => RouteTemplate::query()->with(['branch:id,public_id,code,name_en,name_my', 'sourceWarehouse:id,public_id,code,name_en,name_my', 'ways:id,public_id,code,name_en,name_my'])->where('organization_id', $organizationId)
                ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(fn ($query) => $query->where('code', 'like', '%'.trim($search).'%')->orWhere('name_en', 'like', '%'.trim($search).'%')->orWhere('name_my', 'like', '%'.trim($search).'%')))
                ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))->orderBy('code')->get(),
            'branches' => Branch::query()->where('organization_id', $organizationId)->where('status', 'active')->orderBy('code')->get(['public_id', 'code', 'name_en', 'name_my']),
            'warehouses' => Warehouse::query()->with('branch:id,public_id')->where('organization_id', $organizationId)->where('status', 'active')->orderBy('code')->get(['id', 'public_id', 'branch_id', 'code', 'name_en', 'name_my']),
            'ways' => Way::query()->where('organization_id', $organizationId)->where('status', 'active')->orderBy('code')->get(['public_id', 'code', 'name_en', 'name_my']),
        ];
    }

    public function save(int $organizationId, ?string $publicId, array $attributes, array $auditContext): RouteTemplate
    {
        return DB::transaction(function () use ($organizationId, $publicId, $attributes, $auditContext): RouteTemplate {
            $branch = Branch::query()->where('organization_id', $organizationId)->where('public_id', $attributes['branch_public_id'])->where('status', 'active')->firstOrFail();
            $warehouse = Warehouse::query()->where('organization_id', $organizationId)->where('public_id', $attributes['source_warehouse_public_id'])->where('status', 'active')->firstOrFail();
            if ($warehouse->branch_id !== $branch->id) {
                throw new MasterDataConflictException('The source Warehouse must belong to the selected Branch.', 'route_warehouse_branch_mismatch');
            }
            $wayIds = Way::query()->where('organization_id', $organizationId)->whereIn('public_id', $attributes['way_public_ids'])->where('status', 'active')->pluck('id', 'public_id');
            $orderedWayIds = collect($attributes['way_public_ids'])->map(fn (string $id) => $wayIds[$id])->all();
            unset($attributes['branch_public_id'], $attributes['source_warehouse_public_id'], $attributes['way_public_ids']);
            if ($publicId) {
                $template = RouteTemplate::query()->where('organization_id', $organizationId)->where('public_id', $publicId)->lockForUpdate()->firstOrFail();
                $this->assertVersion($template, (int) $attributes['version']);
                $before = $this->snapshot($template);
                unset($attributes['version']);
                $template->fill([...$attributes, 'branch_id' => $branch->id, 'source_warehouse_id' => $warehouse->id, 'lock_version' => $template->lock_version + 1])->save();
                $action = 'updated';
            } else {
                unset($attributes['version']);
                $template = RouteTemplate::query()->create([...$attributes, 'organization_id' => $organizationId, 'branch_id' => $branch->id, 'source_warehouse_id' => $warehouse->id]);
                $before = null;
                $action = 'created';
            }
            $sync = [];
            foreach ($orderedWayIds as $index => $wayId) {
                $sync[$wayId] = ['organization_id' => $organizationId, 'sequence' => $index + 1];
            }
            $template->ways()->detach();
            $template->ways()->attach($sync);
            $template = $this->load($template);
            $this->audit("master_data.route_template.{$action}", $template, $before, $this->snapshot($template), $auditContext);

            return $template;
        });
    }

    public function archive(int $organizationId, string $publicId, int $version, string $reason, array $auditContext): RouteTemplate
    {
        return DB::transaction(function () use ($organizationId, $publicId, $version, $reason, $auditContext): RouteTemplate {
            $template = RouteTemplate::query()->where('organization_id', $organizationId)->where('public_id', $publicId)->lockForUpdate()->firstOrFail();
            $this->assertVersion($template, $version);
            $before = $this->snapshot($template);
            $template->update(['status' => 'archived', 'lock_version' => $template->lock_version + 1]);
            $auditContext['reason'] = $reason;
            $this->audit('master_data.route_template.archived', $template, $before, $this->snapshot($template), $auditContext);

            return $this->load($template);
        });
    }

    private function load(RouteTemplate $template): RouteTemplate
    {
        return $template->load(['branch:id,public_id,code,name_en,name_my', 'sourceWarehouse:id,public_id,code,name_en,name_my', 'ways:id,public_id,code,name_en,name_my']);
    }

    private function assertVersion(RouteTemplate $template, int $version): void
    {
        if ((int) $template->lock_version !== $version) {
            throw new MasterDataConflictException('This Route Template changed after it was opened. Refresh and review the latest values.');
        }
    }

    private function snapshot(RouteTemplate $template): array
    {
        $fresh = $template->fresh();

        return [...Arr::except($fresh->toArray(), ['id', 'organization_id', 'branch_id', 'source_warehouse_id']), 'way_public_ids' => $fresh->ways()->orderByPivot('sequence')->pluck('ways.public_id')->all()];
    }

    private function audit(string $action, RouteTemplate $template, ?array $before, array $after, array $context): void
    {
        AuditEvent::query()->create(['organization_id' => $template->organization_id, 'actor_user_id' => $context['actor_user_id'] ?? null, 'action' => $action, 'entity_type' => RouteTemplate::class, 'entity_public_id' => $template->public_id, 'before_state' => $before, 'after_state' => $after, 'reason' => $context['reason'] ?? null, 'correlation_id' => $context['correlation_id'] ?? null, 'ip_address' => $context['ip_address'] ?? null]);
    }
}
