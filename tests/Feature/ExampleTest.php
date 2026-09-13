<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_root_redirects_to_the_office_app()
    {
        $response = $this->get('/');

        $response->assertRedirect('/office');
    }

    public function test_phase_zero_app_routes_are_available()
    {
        foreach (['/office', '/client', '/sales', '/driver'] as $route) {
            $this->get($route)->assertOk();
        }
    }

    public function test_mobile_subroutes_are_deep_linkable()
    {
        foreach (['/client/home', '/client/orders', '/client/profile', '/sales/route', '/sales/orders', '/sales/customers', '/sales/attendance', '/sales/salary', '/driver/load', '/driver/profile', '/driver/attendance', '/driver/salary'] as $route) {
            $this->get($route)->assertOk();
        }
    }

    public function test_attendance_office_routes_are_deep_linkable()
    {
        foreach (['/office/orders', '/office/invoices', '/office/returns', '/office/damage', '/office/stock/receive', '/office/stock/issues', '/office/stock/transfers', '/office/stock/damage', '/office/stock/closing', '/office/stock/balances', '/office/stock/value', '/office/stock/card', '/office/deliveries', '/office/deliveries/new', '/office/deliveries/live-map', '/office/deliveries/history', '/office/attendance/locations', '/office/attendance/records', '/office/attendance/summary', '/office/payroll/drafts', '/office/payroll/adjustments', '/office/payroll/salary-history'] as $route) {
            $this->get($route)->assertOk();
        }
    }

    public function test_public_attendance_route_is_available()
    {
        $this->get('/attendance/demo-token')->assertOk();
    }
}
