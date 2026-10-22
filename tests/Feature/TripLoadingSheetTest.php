<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Support\PrintSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripLoadingSheetTest extends TestCase
{
    use RefreshDatabase;

    public function test_office_and_driver_can_load_a4_trip_loading_sheet_settings(): void
    {
        $this->seed();

        foreach (['office@valley.test', 'driver@valley.test'] as $email) {
            $this->actingAs(User::where('email', $email)->firstOrFail())
                ->getJson('/api/settings/printing')
                ->assertOk()
                ->assertJsonPath('data.documents.trip_loading_sheet.label', 'Trip loading sheet')
                ->assertJsonPath('data.documents.trip_loading_sheet.paper_size', 'A4')
                ->assertJsonPath('data.documents.trip_loading_sheet.orientation', 'portrait');
        }
    }

    public function test_driver_receives_the_printing_settings_saved_by_office(): void
    {
        $this->seed();
        $this->actingAs(User::where('email', 'owner@valley.test')->firstOrFail());
        $documents = PrintSettings::defaults();
        $documents['delivery_voucher'] = array_replace($documents['delivery_voucher'], [
            'paper_size' => 'Letter', 'orientation' => 'landscape', 'margin_mm' => 17,
            'copies' => 3, 'design' => 'minimal', 'accent_color' => '#123456',
            'show_logo' => false, 'show_address' => false, 'show_contact' => false,
            'show_tax_number' => true, 'show_notes' => false, 'show_signatures' => false,
            'header_text' => 'Warehouse copy', 'footer_text' => 'Safe travels',
        ]);
        $this->putJson('/api/settings/printing', ['documents' => $documents])->assertOk();

        $this->actingAs(User::where('email', 'driver@valley.test')->firstOrFail());
        $response = $this->getJson('/api/settings/printing')->assertOk();
        foreach ($documents['delivery_voucher'] as $key => $value) {
            $response->assertJsonPath("data.documents.delivery_voucher.{$key}", $value);
        }
    }

    public function test_loading_sheet_settings_keep_a4_portrait_when_older_settings_use_another_size(): void
    {
        $this->seed();
        Company::query()->firstOrFail()->update(['print_settings' => [
            'trip_loading_sheet' => ['paper_size' => '80mm', 'orientation' => 'landscape', 'copies' => 2],
        ]]);
        $this->actingAs(User::where('email', 'driver@valley.test')->firstOrFail())
            ->getJson('/api/settings/printing')->assertOk()
            ->assertJsonPath('data.documents.trip_loading_sheet.paper_size', 'A4')
            ->assertJsonPath('data.documents.trip_loading_sheet.orientation', 'portrait')
            ->assertJsonPath('data.documents.trip_loading_sheet.copies', 2);
    }
}
