<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AppAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhaseOneMasterDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_office_user_can_search_and_paginate_master_data()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'owner@valley.test')->first());

        $this->getJson('/api/master-data/meta')
            ->assertOk()
            ->assertJsonPath('data.resources.0.key', 'areas')
            ->assertJsonStructure(['data' => ['resources', 'options']]);

        $this->getJson('/api/master-data/customers?search=Shwe&is_active=1&per_page=2')
            ->assertOk()
            ->assertJsonPath('data.items.0.shop_name', 'Shwe Family Store')
            ->assertJsonPath('data.items.0.route', 'Taunggyi North')
            ->assertJsonPath('data.meta.total', 1);
    }

    public function test_company_is_managed_as_singleton_settings()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'owner@valley.test')->first());

        $this->getJson('/api/settings/company')
            ->assertOk()
            ->assertJsonPath('data.company.code', 'VALLEY');

        $this->putJson('/api/settings/company', [
            'code' => 'VALLEY',
            'name' => 'Valley Water Company',
            'legal_name' => 'Valley Water Distribution Co., Ltd.',
            'phone' => '081 999 888',
            'email' => 'office@valley.test',
            'registration_no' => 'TGI-2026-001',
            'tax_no' => 'MM-VALLEY-01',
            'address' => 'East Circular Road, Taunggyi',
            'city' => 'Taunggyi',
            'state' => 'Shan State',
            'default_customer_credit_limit' => 750000,
            'delivery_credit_due_days' => 21,
        ])->assertOk()
            ->assertJsonPath('data.company.name', 'Valley Water Company')
            ->assertJsonPath('data.company.default_customer_credit_limit', '750000.00')
            ->assertJsonPath('data.company.delivery_credit_due_days', 21);

        $this->assertDatabaseCount('companies', 1);
        $this->postJson('/api/master-data/companies', [])->assertNotFound();
        $this->deleteJson('/api/master-data/companies/1')->assertNotFound();
    }

    public function test_company_branding_can_be_updated()
    {
        Storage::fake('public');
        $this->seed();
        $this->actingAs(User::where('email', 'owner@valley.test')->first());

        $response = $this->post('/api/settings/company', [
            '_method' => 'PUT',
            'code' => 'VALLEY',
            'name' => 'Valley Springs',
            'primary_color' => '#087f74',
            'default_theme' => 'dark',
            'logo' => UploadedFile::fake()->createWithContent(
                'logo.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')
            ),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.company.name', 'Valley Springs')
            ->assertJsonPath('data.company.primary_color', '#087f74')
            ->assertJsonPath('data.company.default_theme', 'dark');

        $logoPath = $response->json('data.company.logo_path');
        Storage::disk('public')->assertExists($logoPath);
    }

    public function test_empty_active_filter_does_not_hide_seeded_master_data()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'owner@valley.test')->first());

        $this->getJson('/api/master-data/customers?is_active=&per_page=10')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 4)
            ->assertJsonCount(4, 'data.items');
    }

    public function test_blank_codes_are_generated_for_all_coded_master_data_forms()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'owner@valley.test')->first());
        $areaId = DB::table('areas')->value('id');

        $forms = [
            ['areas', ['code' => '', 'name' => 'Generated Area'], 'AREA'],
            ['routes', ['code' => null, 'name' => 'Generated Route', 'area_id' => $areaId], 'RTE'],
            ['warehouses', ['code' => '', 'name' => 'Generated Warehouse'], 'WH'],
            ['brands', ['code' => null, 'name' => 'Generated Brand'], 'BRD'],
            ['price-types', ['code' => '', 'name' => 'Generated Price', 'currency' => 'MMK'], 'PRT'],
            ['customers', ['code' => null, 'shop_name' => 'Generated Store', 'contact_name' => 'Generated Customer', 'phone' => '09 700 100 200', 'credit_limit' => 0], 'CUS'],
            ['employees', ['code' => '', 'name' => 'Generated Employee', 'employee_type' => 'warehouse'], 'EMP'],
            ['vehicles', ['code' => null, 'plate_no' => 'GEN-1001', 'vehicle_type' => 'truck'], 'VEH'],
        ];

        foreach ($forms as [$resource, $payload, $prefix]) {
            $code = $this->postJson("/api/master-data/{$resource}", $payload)
                ->assertCreated()
                ->json('data.item.code');

            $this->assertMatchesRegularExpression("/^{$prefix}-\\d{4,}$/", $code);
        }
    }

    public function test_blank_company_code_is_generated()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'owner@valley.test')->first());

        $this->putJson('/api/settings/company', [
            'code' => '',
            'name' => 'Valley Water Distribution',
        ])->assertOk()
            ->assertJsonPath('data.company.code', 'COM-0001');
    }

    public function test_master_data_api_requires_authentication_and_permission()
    {
        $this->seed();

        $this->getJson('/api/master-data/customers')
            ->assertUnauthorized()
            ->assertJsonPath('ok', false);

        $this->actingAs(User::where('email', 'client@valley.test')->first())
            ->getJson('/api/master-data/customers')
            ->assertForbidden()
            ->assertJsonPath('ok', false);
    }

    public function test_office_user_can_create_update_and_delete_a_master_record()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'owner@valley.test')->first());

        $created = $this->postJson('/api/master-data/brands', [
            'code' => 'NEW',
            'name' => 'New Brand',
            'description' => 'Phase one test brand',
            'is_active' => true,
        ])->assertCreated()->assertJsonPath('data.item.code', 'NEW');

        $id = $created->json('data.item.id');

        $this->putJson("/api/master-data/brands/{$id}", [
            'code' => 'NEW',
            'name' => 'Updated Brand',
            'description' => null,
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.item.name', 'Updated Brand')
            ->assertJsonPath('data.item.is_active', 0);

        $this->deleteJson("/api/master-data/brands/{$id}")->assertOk();
        $this->assertSoftDeleted('brands', ['id' => $id]);
    }

    public function test_master_data_validation_uses_standard_error_shape()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'owner@valley.test')->first());

        $this->postJson('/api/master-data/products', [
            'sku' => '',
            'name' => '',
            'unit' => '',
        ])->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonStructure(['errors' => ['sku', 'name', 'unit']]);
    }

    public function test_office_user_can_view_and_update_product_prices_as_a_matrix_row()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'owner@valley.test')->first());

        $matrix = $this->getJson('/api/master-data/product-prices/matrix?search=5%20Gallon')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonCount(3, 'data.price_types');

        $productId = $matrix->json('data.items.0.id');
        $prices = collect($matrix->json('data.price_types'))->map(fn ($priceType, $index) => [
            'price_type_id' => $priceType['id'],
            'amount' => 4000 + ($index * 500),
        ])->values()->all();

        $this->putJson('/api/master-data/product-prices/matrix', [
            'product_id' => $productId,
            'effective_from' => '2026-09-09',
            'prices' => $prices,
        ])->assertOk()
            ->assertJsonPath('message', 'Product prices updated.');

        foreach ($prices as $price) {
            $this->assertDatabaseHas('product_prices', [
                'product_id' => $productId,
                'price_type_id' => $price['price_type_id'],
                'effective_from' => '2026-09-09',
                'amount' => $price['amount'],
                'is_active' => true,
            ]);
        }
    }

    public function test_customer_password_creates_and_updates_linked_login_account()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'owner@valley.test')->first());

        $response = $this->postJson('/api/master-data/customers', [
            'code' => 'CUS-LOGIN',
            'shop_name' => 'Login Test Store',
            'contact_name' => 'Ma Login',
            'phone' => '09 777 111 222',
            'email' => 'login.customer@valley.test',
            'password' => 'initial-password',
            'password_confirmation' => 'initial-password',
            'credit_limit' => 100000,
            'is_active' => true,
        ])->assertCreated();

        $customerId = $response->json('data.item.id');
        $user = User::where('customer_id', $customerId)->firstOrFail();
        $this->assertSame('Customer', $user->role);
        $this->assertTrue(Hash::check('initial-password', $user->password));
        $response->assertJsonMissingPath('data.item.password');

        $this->putJson("/api/master-data/customers/{$customerId}", [
            'code' => 'CUS-LOGIN',
            'shop_name' => 'Login Test Store',
            'contact_name' => 'Ma Login Updated',
            'phone' => '09 777 111 222',
            'email' => 'login.customer@valley.test',
            'password' => null,
            'password_confirmation' => null,
            'credit_limit' => 100000,
            'is_active' => true,
        ])->assertOk();

        $user->refresh();
        $this->assertSame('Ma Login Updated', $user->name);
        $this->assertTrue(Hash::check('initial-password', $user->password));
    }

    public function test_employee_password_creates_login_with_matching_app_role()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'owner@valley.test')->first());

        $response = $this->postJson('/api/master-data/employees', [
            'code' => 'DRV-LOGIN',
            'name' => 'New Driver',
            'employee_type' => 'driver',
            'phone' => '09 777 333 444',
            'email' => 'new.driver@valley.test',
            'password' => 'driver-password',
            'password_confirmation' => 'driver-password',
            'is_active' => true,
        ])->assertCreated();

        $user = User::where('employee_id', $response->json('data.item.id'))->firstOrFail();
        $this->assertSame('Driver', $user->role);
        $this->assertTrue(Hash::check('driver-password', $user->password));
    }

    public function test_password_requires_an_email_and_confirmation()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'owner@valley.test')->first());

        $this->postJson('/api/master-data/customers', [
            'code' => 'CUS-NO-EMAIL',
            'shop_name' => 'No Email Store',
            'contact_name' => 'No Email',
            'phone' => '09 777 555 666',
            'password' => 'password-one',
            'password_confirmation' => 'password-two',
            'credit_limit' => 0,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_role_permissions_can_be_managed_from_master_data_api()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'owner@valley.test')->first());
        $permissionId = DB::table('permissions')->where('name', 'office.master-data.view')->value('id');

        $response = $this->postJson('/api/master-data/roles', [
            'name' => 'Master Reviewer',
            'description' => 'Read-only master data reviewer.',
            'guard_name' => 'web',
            'allowed_apps' => ['office'],
            'permission_ids' => [$permissionId],
            'is_active' => true,
        ])->assertCreated();

        $this->assertDatabaseHas('permission_role', [
            'role_id' => $response->json('data.item.id'),
            'permission_id' => $permissionId,
        ]);
        $response->assertJsonPath('data.item.allowed_apps.0', 'office');
    }

    public function test_renaming_a_role_keeps_linked_users_and_app_access_in_sync()
    {
        $this->seed();
        $owner = User::where('email', 'owner@valley.test')->firstOrFail();
        $role = DB::table('roles')->where('name', 'Owner')->first();
        $permissionIds = DB::table('permission_role')->where('role_id', $role->id)->pluck('permission_id')->all();

        $this->actingAs($owner)->putJson("/api/master-data/roles/{$role->id}", [
            'name' => 'Business Owner',
            'description' => $role->description,
            'guard_name' => 'web',
            'allowed_apps' => ['office'],
            'permission_ids' => $permissionIds,
            'is_active' => true,
        ])->assertOk()
            ->assertJsonPath('data.item.name', 'Business Owner');

        $this->assertDatabaseHas('users', ['id' => $owner->id, 'role' => 'Business Owner']);
        $this->assertSame(['office'], AppAccess::allowedAppsForRole('Business Owner'));
    }

    public function test_client_and_driver_profiles_are_scoped_to_signed_in_user()
    {
        $this->seed();

        $this->actingAs(User::where('email', 'client@valley.test')->first())
            ->getJson('/api/mobile/master/profile')
            ->assertOk()
            ->assertJsonPath('data.profile.shop_name', 'Shwe Family Store')
            ->assertJsonPath('data.profile.route', 'Taunggyi North');

        $this->actingAs(User::where('email', 'driver@valley.test')->first())
            ->getJson('/api/mobile/master/profile')
            ->assertOk()
            ->assertJsonPath('data.profile.code', 'DRV-001');

        $this->getJson('/api/mobile/master/vehicle')
            ->assertOk()
            ->assertJsonPath('data.vehicle.code', 'VEH-001');
    }

    public function test_sales_user_can_view_assigned_customers_and_register_a_new_one()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'sales@valley.test')->first());

        $this->getJson('/api/mobile/master/customers?search=Cherry')
            ->assertOk()
            ->assertJsonPath('data.items.0.shop_name', 'Cherry Mini Mart')
            ->assertJsonPath('data.meta.total', 1);

        $customerId = DB::table('customers')->where('shop_name', 'Cherry Mini Mart')->value('id');
        $this->getJson("/api/mobile/master/customers/{$customerId}")
            ->assertOk()
            ->assertJsonPath('data.customer.shop_name', 'Cherry Mini Mart')
            ->assertJsonStructure(['data' => ['orders', 'top_products', 'order_summary' => ['orders_count', 'pending_count', 'total_amount', 'average_order_value', 'outstanding_balance', 'monthly_sales', 'yearly_sales']]]);

        $created = $this->postJson('/api/mobile/master/customers', [
            'shop_name' => 'Blue Lake Store',
            'contact_name' => 'Ma Su',
            'phone' => '09 777 123 456',
            'email' => 'blue@example.test',
            'address' => 'North Quarter',
        ])->assertCreated()
            ->assertJsonPath('data.customer.route_id', DB::table('routes')->where('code', 'TGI-N')->value('id'))
            ->assertJsonPath('data.customer.credit_limit', 500000);

        $this->assertDatabaseHas('customers', ['shop_name' => 'Blue Lake Store']);

        $this->putJson('/api/mobile/master/customers/'.$created->json('data.customer.id'), [
            'shop_name' => 'Blue Lake Mini Store',
            'contact_name' => 'Ma Su',
            'phone' => '09 777 123 456',
            'email' => 'blue@example.test',
            'address' => 'North Quarter',
        ])->assertOk()->assertJsonPath('data.customer.shop_name', 'Blue Lake Mini Store');

        $this->postJson('/api/mobile/master/customers', [
            'shop_name' => 'Duplicate Phone Store',
            'contact_name' => 'Ma Su',
            'phone' => '09 777 123 456',
        ])->assertUnprocessable();
    }
}
