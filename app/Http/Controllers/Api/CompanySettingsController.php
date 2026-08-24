<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Support\ApiResponse;
use App\Support\AppAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompanySettingsController extends Controller
{
    public function show(Request $request)
    {
        $this->authorizePermission($request, 'office.master-data.view');
        $company = Company::query()->oldest('id')->first();
        abort_unless($company, 404, 'Company settings have not been initialized.');

        return ApiResponse::success('Company settings loaded.', [
            'company' => $company,
        ]);
    }

    public function update(Request $request)
    {
        $this->authorizePermission($request, 'office.master-data.manage');
        $company = Company::query()->oldest('id')->first();
        abort_unless($company, 404, 'Company settings have not been initialized.');

        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:30', Rule::unique('companies', 'code')->ignore($company->id)],
            'name' => ['required', 'string', 'max:150'],
            'legal_name' => ['nullable', 'string', 'max:180'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:150'],
            'registration_no' => ['nullable', 'string', 'max:80'],
            'tax_no' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
        ]);

        if (blank($validated['code'] ?? null)) {
            $validated['code'] = 'COM-'.str_pad((string) $company->id, 4, '0', STR_PAD_LEFT);
        }

        $company->fill($validated);
        $company->is_active = true;
        $company->updated_by = $request->user()->id;
        $company->save();

        return ApiResponse::success('Company settings saved.', [
            'company' => $company->fresh(),
        ]);
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        abort_unless(in_array($permission, AppAccess::permissionsForRole($request->user()->role), true), 403);
    }
}
