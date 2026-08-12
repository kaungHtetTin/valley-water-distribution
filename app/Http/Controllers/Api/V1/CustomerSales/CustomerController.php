<?php

namespace App\Http\Controllers\Api\V1\CustomerSales;

use App\Domain\CustomerSales\CustomerService;
use App\Domain\CustomerSales\Exceptions\CustomerConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CustomerSales\ArchiveCustomerRequest;
use App\Http\Requests\Api\V1\CustomerSales\StoreCustomerRequest;
use App\Http\Requests\Api\V1\CustomerSales\UpdateCustomerRequest;
use App\Http\Resources\Api\V1\CustomerResource;
use App\Support\Tenancy\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $customers, private readonly OrganizationContext $organization) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:180'], 'status' => ['nullable', 'in:prospect,pending_verification,active,suspended,closed'], 'way' => ['nullable', 'string', 'size:26'], 'sort' => ['nullable', 'in:code,name_en,lifecycle_status,updated_at'], 'direction' => ['nullable', 'in:asc,desc'], 'per_page' => ['nullable', 'integer', 'min:5', 'max:100'], 'page' => ['nullable', 'integer', 'min:1']]);

        return CustomerResource::collection($this->customers->paginate($this->organization->id(), $filters));
    }

    public function store(StoreCustomerRequest $request): CustomerResource|JsonResponse
    {
        try {
            $validated = $request->validated();
            $duplicates = $this->customers->duplicateMatches($this->organization->id(), $validated['contact']['phone'], $validated['name_en']);
            $resource = new CustomerResource($this->customers->create($this->organization->id(), $validated, $this->auditContext($request)));

            return $resource->additional(['meta' => ['duplicate_matches' => $duplicates]])->response()->setStatusCode(201);
        } catch (CustomerConflictException $exception) {
            return $this->conflict($request, $exception);
        }
    }

    public function update(UpdateCustomerRequest $request, string $customer): CustomerResource|JsonResponse
    {
        try {
            return new CustomerResource($this->customers->update($this->organization->id(), $customer, $request->validated(), $this->auditContext($request)));
        } catch (CustomerConflictException $exception) {
            return $this->conflict($request, $exception);
        }
    }

    public function archive(ArchiveCustomerRequest $request, string $customer): CustomerResource|JsonResponse
    {
        try {
            return new CustomerResource($this->customers->archive($this->organization->id(), $customer, (int) $request->validated('version'), $request->validated('reason'), $this->auditContext($request)));
        } catch (CustomerConflictException $exception) {
            return $this->conflict($request, $exception);
        }
    }

    public function options(): JsonResponse
    {
        return response()->json(['data' => $this->customers->options($this->organization->id())]);
    }

    private function auditContext(Request $request): array
    {
        return ['actor_user_id' => $request->user()?->id, 'correlation_id' => $request->attributes->get('correlation_id'), 'ip_address' => $request->ip()];
    }

    private function conflict(Request $request, CustomerConflictException $exception): JsonResponse
    {
        return response()->json(['message' => $exception->getMessage(), 'code' => $exception->conflictCode, 'correlation_id' => $request->attributes->get('correlation_id')], 409);
    }
}
