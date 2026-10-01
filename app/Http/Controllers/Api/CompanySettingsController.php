<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Support\ApiResponse;
use App\Support\AppAccess;
use App\Support\PrintSettings;
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
            'contact_channels' => ['nullable', 'array'],
            'contact_channels.phone' => ['nullable', 'string', 'max:40'],
            'contact_channels.email' => ['nullable', 'email', 'max:150'],
            'contact_channels.website' => ['nullable', 'url:http,https', 'max:255'],
            'contact_channels.facebook' => ['nullable', 'url:http,https', 'max:255'],
            'contact_channels.viber' => ['nullable', 'string', 'max:100'],
            'contact_channels.telegram' => ['nullable', 'string', 'max:100'],
            'contact_channels.whatsapp' => ['nullable', 'string', 'max:100'],
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

        if (array_key_exists('contact_channels', $validated)) {
            $validated['contact_channels'] = array_filter(
                $validated['contact_channels'] ?? [],
                fn ($value) => filled($value),
            );
        }

        $company->fill($validated);
        $company->is_active = true;
        $company->updated_by = $request->user()->id;
        $company->save();

        return ApiResponse::success('Company settings saved.', [
            'company' => $company->fresh(),
        ]);
    }

    public function printing(Request $request)
    {
        $company = Company::query()->oldest('id')->first();
        abort_unless($company, 404, 'Company settings have not been initialized.');

        return ApiResponse::success('Printing settings loaded.', [
            'company' => $company->only(['name', 'legal_name', 'phone', 'email', 'tax_no', 'address', 'city', 'state', 'logo_url', 'primary_color']),
            'documents' => PrintSettings::merge($company->print_settings, $company->primary_color),
        ]);
    }

    public function updatePrinting(Request $request)
    {
        $this->authorizePermission($request, 'office.master-data.manage');
        $company = Company::query()->oldest('id')->first();
        abort_unless($company, 404, 'Company settings have not been initialized.');

        $documentKeys = array_keys(PrintSettings::documentTypes());
        $validated = $request->validate([
            'documents' => ['required', 'array'],
            'documents.*' => ['required', 'array'],
            'documents.*.paper_size' => ['required', Rule::in(['A4', 'A5', 'Letter', '80mm', '58mm'])],
            'documents.*.orientation' => ['required', Rule::in(['portrait', 'landscape'])],
            'documents.*.margin_mm' => ['required', 'integer', 'min:0', 'max:30'],
            'documents.*.design' => ['required', Rule::in(['classic', 'compact', 'minimal'])],
            'documents.*.accent_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'documents.*.show_logo' => ['required', 'boolean'],
            'documents.*.show_address' => ['required', 'boolean'],
            'documents.*.show_contact' => ['required', 'boolean'],
            'documents.*.show_tax_number' => ['required', 'boolean'],
            'documents.*.show_signatures' => ['required', 'boolean'],
            'documents.*.show_notes' => ['required', 'boolean'],
            'documents.*.header_text' => ['nullable', 'string', 'max:160'],
            'documents.*.footer_text' => ['nullable', 'string', 'max:240'],
            'documents.*.copies' => ['required', 'integer', 'min:1', 'max:3'],
        ]);

        $submitted = array_intersect_key($validated['documents'], array_flip($documentKeys));
        $company->print_settings = PrintSettings::merge($submitted, $company->primary_color);
        $company->updated_by = $request->user()->id;
        $company->save();

        return ApiResponse::success('Printing settings saved.', [
            'documents' => PrintSettings::merge($company->print_settings, $company->primary_color),
        ]);
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        abort_unless(in_array($permission, AppAccess::permissionsForRole($request->user()->role), true), 403);
    }
}
