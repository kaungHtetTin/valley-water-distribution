<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Support\ApiResponse;
use App\Support\AppAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
            'primary_color' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'default_theme' => ['sometimes', 'required', Rule::in(['light', 'dark'])],
            'default_customer_credit_limit' => ['sometimes', 'required', 'numeric', 'min:0', 'max:999999999999.99'],
            'delivery_credit_due_days' => ['sometimes', 'required', 'integer', 'min:1', 'max:365'],
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

        unset($validated['logo'], $validated['remove_logo']);

        if ($request->boolean('remove_logo') && $company->logo_path) {
            Storage::disk('public')->delete($company->logo_path);
            $validated['logo_path'] = null;
        }

        if ($request->hasFile('logo')) {
            if ($company->logo_path) {
                Storage::disk('public')->delete($company->logo_path);
            }
            $validated['logo_path'] = $request->file('logo')->store('branding', 'public');
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
