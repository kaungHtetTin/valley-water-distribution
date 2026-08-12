<?php

namespace App\Http\Controllers\Api\V1\MasterData;

use App\Domain\MasterData\Exceptions\MasterDataConflictException;
use App\Domain\MasterData\LocationMasterService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MasterData\ArchiveLocationRequest;
use App\Http\Requests\Api\V1\MasterData\StoreBranchRequest;
use App\Http\Requests\Api\V1\MasterData\UpdateBranchRequest;
use App\Http\Resources\Api\V1\BranchResource;
use App\Support\Tenancy\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BranchController extends Controller
{
    public function __construct(private readonly LocationMasterService $locations, private readonly OrganizationContext $organization) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', 'in:active,inactive,archived'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return BranchResource::collection($this->locations->paginateBranches($this->organization->id(), $filters));
    }

    public function store(StoreBranchRequest $request): JsonResponse
    {
        return (new BranchResource($this->locations->createBranch($this->organization->id(), $request->validated(), $this->auditContext($request))))->response()->setStatusCode(201);
    }

    public function update(UpdateBranchRequest $request, string $branch): BranchResource|JsonResponse
    {
        try {
            return new BranchResource($this->locations->updateBranch($this->organization->id(), $branch, $request->validated(), $this->auditContext($request)));
        } catch (MasterDataConflictException $exception) {
            return $this->conflict($request, $exception);
        }
    }

    public function archive(ArchiveLocationRequest $request, string $branch): BranchResource|JsonResponse
    {
        try {
            return new BranchResource($this->locations->archiveBranch($this->organization->id(), $branch, (int) $request->validated('version'), $request->validated('reason'), $this->auditContext($request)));
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
