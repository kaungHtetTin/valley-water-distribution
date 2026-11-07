<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AppAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EmployeeAccountTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): void
    {
        $this->seed();
        $this->actingAs(User::where('role', 'Owner')->firstOrFail());
    }

    public function test_account_credentials_are_optional_but_new_email_requires_confirmed_password(): void
    {
        $this->owner();
        $id = $this->postJson('/api/master-data/employees', ['name' => 'No login yet', 'employee_type' => 'accountant'])
            ->assertCreated()->json('data.item.id');
        $this->assertDatabaseMissing('users', ['employee_id' => $id]);
        $this->postJson('/api/master-data/employees', ['name' => 'Incomplete', 'employee_type' => 'hr', 'email' => 'new-hr@test.example'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson('/api/master-data/employees', ['name' => 'Mismatch', 'employee_type' => 'hr', 'email' => 'new-hr@test.example', 'password' => 'Password123!', 'password_confirmation' => 'Different123!'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson('/api/master-data/employees', ['name' => 'Duplicate', 'employee_type' => 'hr', 'email' => User::where('role', 'Owner')->value('email'), 'password' => 'Password123!', 'password_confirmation' => 'Password123!'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->putJson('/api/master-data/employees/'.$id, [
            'name' => 'Accountant with login', 'employee_type' => 'accountant', 'email' => 'accountant@test.example',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!',
        ])->assertOk();
        $account = User::where('employee_id', $id)->firstOrFail();
        $this->assertSame('Accountant', $account->role);
        $this->assertTrue(Hash::check('Password123!', $account->password));
        $this->postJson('/api/auth/logout')->assertOk();
        $this->postJson('/api/auth/login', ['email' => $account->email, 'password' => 'Password123!', 'app' => 'office'])
            ->assertOk()->assertJsonPath('data.user.role', 'Accountant');
    }

    public function test_type_changes_assign_matching_role_and_remove_old_access_without_resetting_password(): void
    {
        $this->owner();
        $id = $this->postJson('/api/master-data/employees', [
            'name' => 'Changing type', 'employee_type' => 'sales', 'email' => 'changing@test.example',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!',
        ])->assertCreated()->json('data.item.id');
        $account = User::where('employee_id', $id)->firstOrFail();
        $password = $account->password;
        $this->putJson('/api/master-data/employees/'.$id, ['name' => 'Manager', 'employee_type' => 'manager'])
            ->assertOk()->assertJsonPath('data.item.access_role', 'Manager');
        $this->assertTrue(AppAccess::canAccess($account->fresh(), 'office'));
        $this->assertFalse(AppAccess::canAccess($account->fresh(), 'sales'));
        $this->assertSame($password, $account->fresh()->password);
        $account->createToken('old-token');
        $this->putJson('/api/master-data/employees/'.$id, ['name' => 'Helper', 'employee_type' => 'sale_helper'])->assertOk();
        $this->assertSame('Sale Helper', $account->fresh()->role);
        $this->assertFalse(AppAccess::canAccess($account->fresh(), 'office'));
        $this->assertSame(0, $account->tokens()->count());
    }

    public function test_hr_can_edit_employee_details_but_cannot_create_accounts_change_passwords_or_change_linked_types(): void
    {
        $this->owner();
        $id = $this->postJson('/api/master-data/employees', [
            'name' => 'Linked', 'employee_type' => 'warehouse', 'email' => 'linked@test.example',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!',
        ])->assertCreated()->json('data.item.id');
        $this->actingAs(User::factory()->create(['role' => 'HR']));
        $this->putJson('/api/master-data/employees/'.$id, ['name' => 'Renamed', 'employee_type' => 'warehouse'])->assertOk();
        $this->postJson('/api/master-data/employees', ['name' => 'Forbidden', 'employee_type' => 'manager', 'email' => 'forbidden@test.example', 'password' => 'Password123!', 'password_confirmation' => 'Password123!'])->assertForbidden();
        $this->putJson('/api/master-data/employees/'.$id, ['name' => 'Linked', 'employee_type' => 'warehouse', 'password' => 'Changed123!', 'password_confirmation' => 'Changed123!'])->assertForbidden();
        $this->putJson('/api/master-data/employees/'.$id, ['name' => 'Linked', 'employee_type' => 'manager'])->assertForbidden();
        $this->assertDatabaseHas('users', ['employee_id' => $id, 'role' => 'Storekeeper']);
    }

    public function test_unrelated_edits_preserve_a_customized_account_role_and_use_linked_email(): void
    {
        $this->owner();
        $id = $this->postJson('/api/master-data/employees', ['name' => 'Existing office employee', 'employee_type' => 'accountant'])->assertCreated()->json('data.item.id');
        $account = User::factory()->create(['role' => 'Manager', 'email' => 'separate@test.example', 'employee_id' => $id]);
        $this->getJson('/api/master-data/employees/'.$id)->assertOk()->assertJsonPath('data.item.email', $account->email);
        $this->putJson('/api/master-data/employees/'.$id, ['name' => 'Edited', 'employee_type' => 'accountant'])->assertOk();
        $this->assertSame('Manager', $account->fresh()->role);
        $this->assertSame($account->password, $account->fresh()->password);
        $this->assertDatabaseHas('employees', ['id' => $id, 'email' => $account->email]);
    }
}
