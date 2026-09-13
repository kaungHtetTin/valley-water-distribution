<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\AppAccess;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MasterDataController extends Controller
{
    public function meta(Request $request)
    {
        $this->authorizeOffice($request, 'office.master-data.view');

        $resources = collect($this->resources())->map(fn ($config, $key) => [
            'key' => $key,
            'label' => $config['label'],
            'singular' => $config['singular'],
            'description' => $config['description'],
            'fields' => $config['fields'],
            'list' => $config['list'],
        ])->values();

        return ApiResponse::success('Master data setup loaded.', [
            'resources' => $resources,
            'options' => $this->optionLists(),
        ]);
    }

    public function index(Request $request, string $resource)
    {
        $this->authorizeOffice($request, 'office.master-data.view');
        $config = $this->resource($resource);
        $query = DB::table($config['table']);
        if (Schema::hasColumn($config['table'], 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($query) use ($config, $search) {
                foreach ($config['search'] as $index => $column) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $query->{$method}($column, 'like', "%{$search}%");
                }
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        foreach ($config['filters'] ?? [] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }

        $perPage = min(max((int) $request->query('per_page', 10), 1), 100);
        $paginator = $query->orderBy($config['sort'][0], $config['sort'][1])->paginate($perPage);
        $items = collect($paginator->items())->map(fn ($item) => $this->decorate($resource, (array) $item));

        return ApiResponse::success("{$config['label']} loaded.", [
            'items' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Request $request, string $resource, int $id)
    {
        $this->authorizeOffice($request, 'office.master-data.view');
        $config = $this->resource($resource);
        $query = DB::table($config['table'])->where('id', $id);
        if (Schema::hasColumn($config['table'], 'deleted_at')) {
            $query->whereNull('deleted_at');
        }
        $item = $query->first();
        abort_unless($item, 404);

        return ApiResponse::success("{$config['singular']} loaded.", [
            'item' => $this->decorate($resource, (array) $item),
        ]);
    }

    public function productPriceMatrix(Request $request)
    {
        $this->authorizeOffice($request, 'office.master-data.view');

        $products = DB::table('products')
            ->whereNull('deleted_at')
            ->when(trim((string) $request->query('search')), function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('sku', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(min(max((int) $request->query('per_page', 25), 1), 100));

        $priceTypes = DB::table('price_types')
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'currency']);

        $productIds = collect($products->items())->pluck('id');
        $prices = DB::table('product_prices')
            ->whereIn('product_id', $productIds)
            ->where('is_active', true)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get(['id', 'product_id', 'price_type_id', 'amount', 'effective_from'])
            ->groupBy('product_id')
            ->map(fn ($items) => $items->unique('price_type_id')->values());

        $items = collect($products->items())->map(fn ($product) => [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'unit' => $product->unit,
            'is_active' => (bool) $product->is_active,
            'prices' => $prices->get($product->id, collect()),
        ]);

        return ApiResponse::success('Product price matrix loaded.', [
            'items' => $items,
            'price_types' => $priceTypes,
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function updateProductPriceMatrix(Request $request)
    {
        $this->authorizeOffice($request, 'office.master-data.manage');
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'effective_from' => ['required', 'date'],
            'prices' => ['required', 'array', 'min:1'],
            'prices.*.price_type_id' => ['required', 'integer', 'exists:price_types,id'],
            'prices.*.amount' => ['required', 'numeric', 'min:0'],
        ]);

        $now = now();
        DB::transaction(function () use ($request, $validated, $now) {
            foreach ($validated['prices'] as $price) {
                $values = [
                    'amount' => $price['amount'],
                    'is_active' => true,
                    'updated_at' => $now,
                ];
                if (Schema::hasColumn('product_prices', 'updated_by')) {
                    $values['updated_by'] = $request->user()->id;
                }

                $existing = DB::table('product_prices')->where([
                    'product_id' => $validated['product_id'],
                    'price_type_id' => $price['price_type_id'],
                    'effective_from' => $validated['effective_from'],
                ])->exists();

                if (! $existing) {
                    $values['created_at'] = $now;
                    if (Schema::hasColumn('product_prices', 'created_by')) {
                        $values['created_by'] = $request->user()->id;
                    }
                }

                DB::table('product_prices')->updateOrInsert([
                    'product_id' => $validated['product_id'],
                    'price_type_id' => $price['price_type_id'],
                    'effective_from' => $validated['effective_from'],
                ], $values);
            }
        });

        return ApiResponse::success('Product prices updated.');
    }

    public function store(Request $request, string $resource)
    {
        $this->authorizeOffice($request, 'office.master-data.manage');
        $config = $this->resource($resource);
        $validated = $request->validate($this->rules($resource));
        $permissionIds = $validated['permission_ids'] ?? [];
        $password = $validated['password'] ?? null;
        unset($validated['permission_ids'], $validated['password'], $validated['password_confirmation']);
        if ($resource === 'roles') {
            $validated['allowed_apps'] = json_encode($validated['allowed_apps'] ?? []);
        }
        $generateCode = $this->needsGeneratedCode($resource, $validated);
        if ($generateCode) {
            $validated['code'] = 'AUTO-'.Str::upper(Str::random(12));
        }
        $validated['is_active'] = $validated['is_active'] ?? true;
        if (Schema::hasColumn($config['table'], 'created_by')) {
            $validated['created_by'] = $request->user()->id;
            $validated['updated_by'] = $request->user()->id;
        }
        $validated['created_at'] = now();
        $validated['updated_at'] = now();

        $id = DB::transaction(function () use ($config, $generateCode, $password, $permissionIds, $resource, $validated) {
            $id = DB::table($config['table'])->insertGetId($validated);
            if ($generateCode) {
                $validated['code'] = $this->generatedCode($resource, $id);
                DB::table($config['table'])->where('id', $id)->update(['code' => $validated['code']]);
            }
            $this->syncRolePermissions($resource, $id, $permissionIds);
            $this->syncLoginAccount($resource, $id, $validated, $password);

            return $id;
        });

        return ApiResponse::success("{$config['singular']} created.", [
            'item' => $this->decorate($resource, (array) DB::table($config['table'])->find($id)),
        ], 201);
    }

    public function update(Request $request, string $resource, int $id)
    {
        $this->authorizeOffice($request, 'office.master-data.manage');
        $config = $this->resource($resource);
        abort_unless(DB::table($config['table'])->where('id', $id)->exists(), 404);
        $previousRoleName = $resource === 'roles' ? DB::table('roles')->where('id', $id)->value('name') : null;
        $validated = $request->validate($this->rules($resource, $id));
        $permissionIds = $validated['permission_ids'] ?? null;
        $password = $validated['password'] ?? null;
        unset($validated['permission_ids'], $validated['password'], $validated['password_confirmation']);
        if ($resource === 'roles') {
            $validated['allowed_apps'] = json_encode($validated['allowed_apps'] ?? []);
        }
        if ($this->needsGeneratedCode($resource, $validated)) {
            $validated['code'] = $this->generatedCode($resource, $id);
        }
        if (Schema::hasColumn($config['table'], 'updated_by')) {
            $validated['updated_by'] = $request->user()->id;
        }
        $validated['updated_at'] = now();

        DB::transaction(function () use ($id, $config, $password, $permissionIds, $previousRoleName, $resource, $validated) {
            DB::table($config['table'])->where('id', $id)->update($validated);
            if ($resource === 'roles' && $previousRoleName !== $validated['name']) {
                User::query()->where('role', $previousRoleName)->update(['role' => $validated['name']]);
            }
            if ($permissionIds !== null) {
                $this->syncRolePermissions($resource, $id, $permissionIds);
            }
            $record = (array) DB::table($config['table'])->find($id);
            $this->syncLoginAccount($resource, $id, $record, $password);
        });

        return ApiResponse::success("{$config['singular']} updated.", [
            'item' => $this->decorate($resource, (array) DB::table($config['table'])->find($id)),
        ]);
    }

    public function destroy(Request $request, string $resource, int $id)
    {
        $this->authorizeOffice($request, 'office.master-data.manage');
        $config = $this->resource($resource);
        if ($this->hasReferences($resource, $id)) {
            return ApiResponse::error(
                "{$config['singular']} is used by another record and cannot be deleted.",
                ['record' => ['Set the record to inactive instead.']],
                409
            );
        }

        try {
            if (Schema::hasColumn($config['table'], 'deleted_at')) {
                $deleted = DB::table($config['table'])->where('id', $id)->whereNull('deleted_at')->update([
                    'is_active' => false,
                    'updated_by' => $request->user()->id,
                    'updated_at' => now(),
                    'deleted_at' => now(),
                ]);
            } else {
                $deleted = DB::table($config['table'])->where('id', $id)->delete();
            }
        } catch (QueryException) {
            return ApiResponse::error(
                "{$config['singular']} is used by another record and cannot be deleted.",
                ['record' => ['Set the record to inactive instead.']],
                409
            );
        }

        abort_unless($deleted, 404);

        return ApiResponse::success("{$config['singular']} deleted.");
    }

    private function authorizeOffice(Request $request, string $permission): void
    {
        abort_unless($request->user() && in_array($permission, AppAccess::permissionsForRole($request->user()->role), true), 403);
    }

    private function resource(string $resource): array
    {
        $resources = $this->resources();
        abort_unless(isset($resources[$resource]), 404);

        return $resources[$resource];
    }

    private function rules(string $resource, ?int $id = null): array
    {
        $unique = fn (string $table, string $column) => Rule::unique($table, $column)->ignore($id);
        $active = ['sometimes', 'boolean'];
        $linkedUser = in_array($resource, ['customers', 'employees'], true) && $id
            ? User::query()->where($resource === 'customers' ? 'customer_id' : 'employee_id', $id)->first()
            : null;
        $accountEmail = Rule::unique('users', 'email')->ignore($linkedUser?->id);
        $emailPresence = $linkedUser ? 'required' : 'nullable';
        $password = ['nullable', 'string', 'min:8', 'max:72', 'confirmed'];

        return match ($resource) {
            'areas' => $this->codedRules('areas', $id, ['description' => ['nullable', 'string', 'max:500']]),
            'routes' => $this->codedRules('routes', $id, [
                'area_id' => ['required', 'integer', 'exists:areas,id'],
                'service_day' => ['nullable', Rule::in(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'])],
                'description' => ['nullable', 'string', 'max:500'],
            ]),
            'warehouses' => $this->codedRules('warehouses', $id, [
                'area_id' => ['nullable', 'integer', 'exists:areas,id'],
                'phone' => ['nullable', 'string', 'max:40'],
                'address' => ['nullable', 'string', 'max:500'],
            ]),
            'brands' => $this->codedRules('brands', $id, ['description' => ['nullable', 'string', 'max:500']]),
            'products' => [
                'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
                'sku' => ['required', 'string', 'max:50', $unique('products', 'sku')],
                'name' => ['required', 'string', 'max:150'],
                'unit' => ['required', 'string', 'max:30'],
                'size' => ['nullable', 'string', 'max:60'],
                'description' => ['nullable', 'string', 'max:500'],
                'is_active' => $active,
            ],
            'price-types' => [
                'code' => ['nullable', 'string', 'max:30', $unique('price_types', 'code')],
                'name' => ['required', 'string', 'max:100'],
                'currency' => ['required', 'string', 'size:3'],
                'is_default' => ['sometimes', 'boolean'],
                'is_active' => $active,
            ],
            'product-prices' => [
                'product_id' => ['required', 'integer', 'exists:products,id'],
                'price_type_id' => ['required', 'integer', 'exists:price_types,id'],
                'amount' => ['required', 'numeric', 'min:0'],
                'effective_from' => ['nullable', 'date'],
                'is_active' => $active,
            ],
            'customers' => [
                'area_id' => ['nullable', 'integer', 'exists:areas,id'],
                'route_id' => ['nullable', 'integer', 'exists:routes,id'],
                'price_type_id' => ['nullable', 'integer', 'exists:price_types,id'],
                'code' => ['nullable', 'string', 'max:30', $unique('customers', 'code')],
                'shop_name' => ['required', 'string', 'max:150'],
                'contact_name' => ['required', 'string', 'max:150'],
                'phone' => ['required', 'string', 'max:40'],
                'email' => [$emailPresence, 'required_with:password', 'email', 'max:150', $accountEmail],
                'password' => $password,
                'password_confirmation' => ['nullable', 'string', 'max:72'],
                'address' => ['nullable', 'string', 'max:500'],
                'credit_limit' => ['required', 'numeric', 'min:0'],
                'is_active' => $active,
            ],
            'employees' => [
                'assigned_route_id' => ['nullable', 'integer', 'exists:routes,id'],
                'code' => ['nullable', 'string', 'max:30', $unique('employees', 'code')],
                'name' => ['required', 'string', 'max:150'],
                'employee_type' => ['required', Rule::in(['office', 'sales', 'driver', 'warehouse'])],
                'phone' => ['nullable', 'string', 'max:40'],
                'email' => [$emailPresence, 'required_with:password', 'email', 'max:150', $unique('employees', 'email'), $accountEmail],
                'password' => $password,
                'password_confirmation' => ['nullable', 'string', 'max:72'],
                'hire_date' => ['nullable', 'date'],
                'address' => ['nullable', 'string', 'max:500'],
                'is_active' => $active,
            ],
            'vehicles' => [
                'assigned_driver_id' => ['nullable', 'integer', 'exists:employees,id'],
                'code' => ['nullable', 'string', 'max:30', $unique('vehicles', 'code')],
                'plate_no' => ['required', 'string', 'max:40', $unique('vehicles', 'plate_no')],
                'vehicle_type' => ['required', Rule::in(['truck', 'van', 'motorbike', 'other'])],
                'make' => ['nullable', 'string', 'max:80'],
                'model' => ['nullable', 'string', 'max:80'],
                'capacity' => ['nullable', 'numeric', 'min:0'],
                'is_active' => $active,
            ],
            'roles' => [
                'name' => ['required', 'string', 'max:100', $unique('roles', 'name')],
                'description' => ['nullable', 'string', 'max:500'],
                'guard_name' => ['required', Rule::in(['web'])],
                'allowed_apps' => ['required', 'array', 'min:1'],
                'allowed_apps.*' => ['string', Rule::in(AppAccess::APPS)],
                'permission_ids' => ['sometimes', 'array'],
                'permission_ids.*' => ['integer', 'exists:permissions,id'],
                'is_active' => $active,
            ],
            'permissions' => [
                'name' => ['required', 'string', 'max:150', $unique('permissions', 'name')],
                'group' => ['required', 'string', 'max:100'],
                'guard_name' => ['required', Rule::in(['web'])],
                'is_active' => $active,
            ],
            default => abort(404),
        };
    }

    private function codedRules(string $table, ?int $id, array $extra): array
    {
        return array_merge([
            'code' => ['nullable', 'string', 'max:30', Rule::unique($table, 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:150'],
            'is_active' => ['sometimes', 'boolean'],
        ], $extra);
    }

    private function syncRolePermissions(string $resource, int $roleId, array $permissionIds): void
    {
        if ($resource !== 'roles') {
            return;
        }

        DB::table('permission_role')->where('role_id', $roleId)->delete();
        foreach (array_unique($permissionIds) as $permissionId) {
            DB::table('permission_role')->insert(['role_id' => $roleId, 'permission_id' => $permissionId]);
        }
    }

    private function syncLoginAccount(string $resource, int $recordId, array $record, ?string $password): void
    {
        if (! in_array($resource, ['customers', 'employees'], true)) {
            return;
        }

        $linkColumn = $resource === 'customers' ? 'customer_id' : 'employee_id';
        $user = User::query()->where($linkColumn, $recordId)->first();
        if (! $user && ! $password) {
            return;
        }

        $attributes = [
            'name' => $resource === 'customers' ? $record['contact_name'] : $record['name'],
            'email' => $record['email'],
            'phone' => $record['phone'] ?? null,
            'role' => $resource === 'customers' ? 'Customer' : $this->employeeRole($record['employee_type']),
            'customer_id' => $resource === 'customers' ? $recordId : null,
            'employee_id' => $resource === 'employees' ? $recordId : null,
        ];

        if ($password) {
            $attributes['password'] = Hash::make($password);
        }

        if ($user) {
            $user->update($attributes);
        } else {
            User::create($attributes + ['locale' => 'en']);
        }
    }

    private function employeeRole(string $employeeType): string
    {
        return match ($employeeType) {
            'sales' => 'Sales Representative',
            'driver' => 'Driver',
            default => 'Office Staff',
        };
    }

    private function needsGeneratedCode(string $resource, array $values): bool
    {
        return array_key_exists($resource, $this->codePrefixes()) && blank($values['code'] ?? null);
    }

    private function generatedCode(string $resource, int $recordId): string
    {
        $prefix = $this->codePrefixes()[$resource];
        $table = $this->resource($resource)['table'];
        $sequence = $recordId;

        do {
            $code = $prefix.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            $sequence++;
        } while (DB::table($table)->where('code', $code)->where('id', '!=', $recordId)->exists());

        return $code;
    }

    private function codePrefixes(): array
    {
        return [
            'areas' => 'AREA',
            'routes' => 'RTE',
            'warehouses' => 'WH',
            'brands' => 'BRD',
            'price-types' => 'PRT',
            'customers' => 'CUS',
            'employees' => 'EMP',
            'vehicles' => 'VEH',
        ];
    }

    private function decorate(string $resource, array $item): array
    {
        $relations = $this->resource($resource)['relations'] ?? [];
        foreach ($relations as $field => [$table, $label, $alias]) {
            $item[$alias] = $item[$field] ? DB::table($table)->where('id', $item[$field])->value($label) : null;
        }

        if ($resource === 'roles') {
            $item['permission_ids'] = DB::table('permission_role')->where('role_id', $item['id'])->pluck('permission_id')->all();
            $item['allowed_apps'] = json_decode($item['allowed_apps'] ?? '[]', true) ?: [];
        }

        return $item;
    }

    private function optionLists(): array
    {
        $lists = [
            'areas' => ['areas', 'name'],
            'routes' => ['routes', 'name'],
            'brands' => ['brands', 'name'],
            'products' => ['products', 'name'],
            'price-types' => ['price_types', 'name'],
            'employees' => ['employees', 'name'],
            'permissions' => ['permissions', 'name'],
        ];

        $options = collect($lists)->map(function ($definition) {
            $query = DB::table($definition[0])->where('is_active', true);
            if (Schema::hasColumn($definition[0], 'deleted_at')) {
                $query->whereNull('deleted_at');
            }

            return $query->orderBy($definition[1])->get(['id', DB::raw("{$definition[1]} as label")]);
        })->all();

        $options['apps'] = collect(AppAccess::APPS)->map(fn ($app) => [
            'id' => $app,
            'label' => ucfirst($app).' App',
        ]);

        return $options;
    }

    private function hasReferences(string $resource, int $id): bool
    {
        $references = [
            'areas' => [['routes', 'area_id'], ['warehouses', 'area_id'], ['customers', 'area_id']],
            'routes' => [['customers', 'route_id'], ['employees', 'assigned_route_id'], ['orders', 'route_id'], ['deliveries', 'route_id']],
            'warehouses' => [['stock_balances', 'warehouse_id'], ['stock_movements', 'warehouse_id'], ['deliveries', 'warehouse_id']],
            'brands' => [['products', 'brand_id']],
            'products' => [['product_prices', 'product_id'], ['order_items', 'product_id'], ['invoice_items', 'product_id'], ['stock_balances', 'product_id']],
            'customers' => [['users', 'customer_id'], ['orders', 'customer_id'], ['invoices', 'customer_id'], ['collections', 'customer_id']],
            'employees' => [['users', 'employee_id'], ['attendance_records', 'employee_id'], ['deliveries', 'driver_id'], ['vehicle_costs', 'employee_id']],
            'vehicles' => [['deliveries', 'vehicle_id'], ['vehicle_costs', 'vehicle_id']],
            'suppliers' => [['supplier_ledger_entries', 'supplier_id']],
        ];

        return collect($references[$resource] ?? [])->contains(fn ($reference) => DB::table($reference[0])->where($reference[1], $id)->exists());
    }

    private function resources(): array
    {
        $active = ['name' => 'is_active', 'label' => 'Status', 'type' => 'boolean'];
        $code = ['name' => 'code', 'label' => 'Code', 'type' => 'text'];
        $name = ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true];

        return [
            'areas' => $this->config('areas', 'Areas', 'Area', 'Service territories used by routes and customers.',
                [$code, $name, ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'], $active],
                ['code', 'name', 'description', 'is_active'], ['code', 'name', 'description']),
            'routes' => $this->config('routes', 'Routes', 'Route', 'Delivery and sales service routes.',
                [['name' => 'area_id', 'label' => 'Area', 'type' => 'select', 'source' => 'areas', 'required' => true], $code, $name, ['name' => 'service_day', 'label' => 'Service day', 'type' => 'select', 'options' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']], ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'], $active],
                ['code', 'name', 'area', 'service_day', 'is_active'], ['code', 'name', 'service_day', 'description'], ['area_id'], ['area_id' => ['areas', 'name', 'area']]),
            'warehouses' => $this->config('warehouses', 'Warehouses', 'Warehouse', 'Stock holding locations.',
                [['name' => 'area_id', 'label' => 'Area', 'type' => 'select', 'source' => 'areas'], $code, $name, ['name' => 'phone', 'label' => 'Phone', 'type' => 'text'], ['name' => 'address', 'label' => 'Address', 'type' => 'textarea'], $active],
                ['code', 'name', 'area', 'phone', 'is_active'], ['code', 'name', 'phone', 'address'], ['area_id'], ['area_id' => ['areas', 'name', 'area']]),
            'brands' => $this->config('brands', 'Brands', 'Brand', 'Product brand definitions.',
                [$code, $name, ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'], $active],
                ['code', 'name', 'description', 'is_active'], ['code', 'name', 'description']),
            'products' => $this->config('products', 'Products', 'Product', 'Sellable water and related items.',
                [['name' => 'brand_id', 'label' => 'Brand', 'type' => 'select', 'source' => 'brands'], ['name' => 'sku', 'label' => 'SKU', 'type' => 'text', 'required' => true], $name, ['name' => 'unit', 'label' => 'Unit', 'type' => 'text', 'required' => true], ['name' => 'size', 'label' => 'Size', 'type' => 'text'], ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'], $active],
                ['sku', 'name', 'brand', 'unit', 'is_active'], ['sku', 'name', 'unit', 'size', 'description'], ['brand_id'], ['brand_id' => ['brands', 'name', 'brand']]),
            'price-types' => $this->config('price_types', 'Price Types', 'Price Type', 'Retail, wholesale, and special pricing tiers.',
                [$code, $name, ['name' => 'currency', 'label' => 'Currency', 'type' => 'text', 'required' => true], ['name' => 'is_default', 'label' => 'Default price', 'type' => 'boolean'], $active],
                ['code', 'name', 'currency', 'is_default', 'is_active'], ['code', 'name']),
            'product-prices' => $this->config('product_prices', 'Product Prices', 'Product Price', 'Prices by product and customer price type.',
                [['name' => 'product_id', 'label' => 'Product', 'type' => 'select', 'source' => 'products', 'required' => true], ['name' => 'price_type_id', 'label' => 'Price type', 'type' => 'select', 'source' => 'price-types', 'required' => true], ['name' => 'amount', 'label' => 'Amount', 'type' => 'number', 'required' => true], ['name' => 'effective_from', 'label' => 'Effective from', 'type' => 'date'], $active],
                ['product', 'price_type', 'amount', 'effective_from', 'is_active'], ['effective_from'], ['product_id', 'price_type_id'], ['product_id' => ['products', 'name', 'product'], 'price_type_id' => ['price_types', 'name', 'price_type']]),
            'customers' => $this->config('customers', 'Customers', 'Customer', 'Reseller shops, contacts, route, and credit settings.',
                [['name' => 'area_id', 'label' => 'Area', 'type' => 'select', 'source' => 'areas'], ['name' => 'route_id', 'label' => 'Route', 'type' => 'select', 'source' => 'routes'], ['name' => 'price_type_id', 'label' => 'Price type', 'type' => 'select', 'source' => 'price-types'], $code, ['name' => 'shop_name', 'label' => 'Shop name', 'type' => 'text', 'required' => true], ['name' => 'contact_name', 'label' => 'Contact name', 'type' => 'text', 'required' => true], ['name' => 'phone', 'label' => 'Phone', 'type' => 'text', 'required' => true], ['name' => 'email', 'label' => 'Email', 'type' => 'email'], ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'autocomplete' => 'new-password'], ['name' => 'password_confirmation', 'label' => 'Confirm password', 'type' => 'password', 'autocomplete' => 'new-password'], ['name' => 'address', 'label' => 'Address', 'type' => 'textarea'], ['name' => 'credit_limit', 'label' => 'Credit limit', 'type' => 'number', 'required' => true], $active],
                ['code', 'shop_name', 'contact_name', 'route', 'phone', 'is_active'], ['code', 'shop_name', 'contact_name', 'phone', 'email', 'address'], ['area_id', 'route_id', 'price_type_id'], ['area_id' => ['areas', 'name', 'area'], 'route_id' => ['routes', 'name', 'route'], 'price_type_id' => ['price_types', 'name', 'price_type']]),
            'employees' => $this->config('employees', 'Employees', 'Employee', 'Office, warehouse, sales, and driver records.',
                [['name' => 'assigned_route_id', 'label' => 'Assigned route', 'type' => 'select', 'source' => 'routes'], $code, $name, ['name' => 'employee_type', 'label' => 'Employee type', 'type' => 'select', 'required' => true, 'options' => ['office', 'sales', 'driver', 'warehouse']], ['name' => 'phone', 'label' => 'Phone', 'type' => 'text'], ['name' => 'email', 'label' => 'Email', 'type' => 'email'], ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'autocomplete' => 'new-password'], ['name' => 'password_confirmation', 'label' => 'Confirm password', 'type' => 'password', 'autocomplete' => 'new-password'], ['name' => 'hire_date', 'label' => 'Hire date', 'type' => 'date'], ['name' => 'address', 'label' => 'Address', 'type' => 'textarea'], $active],
                ['code', 'name', 'employee_type', 'assigned_route', 'phone', 'is_active'], ['code', 'name', 'employee_type', 'phone', 'email'], ['assigned_route_id', 'employee_type'], ['assigned_route_id' => ['routes', 'name', 'assigned_route']]),
            'vehicles' => $this->config('vehicles', 'Vehicles', 'Vehicle', 'Delivery vehicles and assigned drivers.',
                [['name' => 'assigned_driver_id', 'label' => 'Assigned driver', 'type' => 'select', 'source' => 'employees'], $code, ['name' => 'plate_no', 'label' => 'Plate no.', 'type' => 'text', 'required' => true], ['name' => 'vehicle_type', 'label' => 'Vehicle type', 'type' => 'select', 'required' => true, 'options' => ['truck', 'van', 'motorbike', 'other']], ['name' => 'make', 'label' => 'Make', 'type' => 'text'], ['name' => 'model', 'label' => 'Model', 'type' => 'text'], ['name' => 'capacity', 'label' => 'Capacity', 'type' => 'number'], $active],
                ['code', 'plate_no', 'vehicle_type', 'assigned_driver', 'capacity', 'is_active'], ['code', 'plate_no', 'make', 'model'], ['assigned_driver_id', 'vehicle_type'], ['assigned_driver_id' => ['employees', 'name', 'assigned_driver']]),
            'roles' => $this->config('roles', 'Roles & Permissions', 'Role', 'App roles and permission assignments.',
                [$name, ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'], ['name' => 'guard_name', 'label' => 'Guard', 'type' => 'hidden', 'default' => 'web'], ['name' => 'allowed_apps', 'label' => 'Allowed apps', 'type' => 'multiselect', 'source' => 'apps'], ['name' => 'permission_ids', 'label' => 'Permissions', 'type' => 'multiselect', 'source' => 'permissions'], $active],
                ['name', 'allowed_apps', 'description', 'is_active'], ['name', 'description']),
            'permissions' => $this->config('permissions', 'Permissions', 'Permission', 'Permission catalog used by roles.',
                [['name' => 'name', 'label' => 'Permission', 'type' => 'text', 'required' => true], ['name' => 'group', 'label' => 'Group', 'type' => 'text', 'required' => true], ['name' => 'guard_name', 'label' => 'Guard', 'type' => 'hidden', 'default' => 'web'], $active],
                ['name', 'group', 'is_active'], ['name', 'group']),
        ];
    }

    private function config(string $table, string $label, string $singular, string $description, array $fields, array $list, array $search, array $filters = [], array $relations = []): array
    {
        return compact('table', 'label', 'singular', 'description', 'fields', 'list', 'search', 'filters', 'relations') + ['sort' => ['id', 'desc']];
    }
}
