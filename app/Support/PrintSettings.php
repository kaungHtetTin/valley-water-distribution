<?php

namespace App\Support;

class PrintSettings
{
    public static function documentTypes(): array
    {
        return [
            'sales_order' => ['label' => 'Sales order voucher', 'description' => 'Customer order confirmation', 'paper_size' => 'A4', 'orientation' => 'portrait', 'design' => 'classic', 'show_signatures' => true],
            'sales_invoice' => ['label' => 'Sales invoice', 'description' => 'Official customer invoice', 'paper_size' => 'A4', 'orientation' => 'portrait', 'design' => 'classic', 'show_signatures' => true],
            'payment_receipt' => ['label' => 'Payment receipt', 'description' => 'Cash and credit collection receipt', 'paper_size' => '80mm', 'orientation' => 'portrait', 'design' => 'compact', 'show_signatures' => false],
            'delivery_voucher' => ['label' => 'Delivery voucher', 'description' => 'Driver and customer delivery record', 'paper_size' => 'A5', 'orientation' => 'portrait', 'design' => 'compact', 'show_signatures' => true],
            'sales_return' => ['label' => 'Sales return / credit note', 'description' => 'Returned products and customer credit', 'paper_size' => 'A4', 'orientation' => 'portrait', 'design' => 'classic', 'show_signatures' => true],
            'stock_receipt' => ['label' => 'Stock receipt / GRN', 'description' => 'Warehouse goods receipt', 'paper_size' => 'A4', 'orientation' => 'portrait', 'design' => 'compact', 'show_signatures' => true],
            'stock_transfer' => ['label' => 'Stock transfer voucher', 'description' => 'Warehouse-to-warehouse movement', 'paper_size' => 'A4', 'orientation' => 'landscape', 'design' => 'compact', 'show_signatures' => true],
            'stock_adjustment' => ['label' => 'Stock adjustment voucher', 'description' => 'Damage, loss and count differences', 'paper_size' => 'A4', 'orientation' => 'portrait', 'design' => 'compact', 'show_signatures' => true],
            'expense_payment' => ['label' => 'Expense / payment voucher', 'description' => 'Expense and supplier payment record', 'paper_size' => 'A5', 'orientation' => 'portrait', 'design' => 'classic', 'show_signatures' => true],
            'employee_payslip' => ['label' => 'Employee payslip', 'description' => 'Salary, bonus and deduction statement', 'paper_size' => 'A5', 'orientation' => 'portrait', 'design' => 'minimal', 'show_signatures' => false],
        ];
    }

    public static function defaults(?string $accentColor = null): array
    {
        return collect(self::documentTypes())->map(function (array $type) use ($accentColor) {
            return array_merge($type, [
                'margin_mm' => in_array($type['paper_size'], ['80mm', '58mm'], true) ? 4 : 10,
                'accent_color' => $accentColor ?: '#0b84a5',
                'show_logo' => true,
                'show_address' => true,
                'show_contact' => true,
                'show_tax_number' => false,
                'show_notes' => true,
                'header_text' => '',
                'footer_text' => 'Thank you.',
                'copies' => 1,
            ]);
        })->all();
    }

    public static function merge(?array $settings, ?string $accentColor = null): array
    {
        $defaults = self::defaults($accentColor);

        foreach ($settings ?? [] as $key => $value) {
            if (isset($defaults[$key]) && is_array($value)) {
                $defaults[$key] = array_merge($defaults[$key], $value);
            }
        }

        return $defaults;
    }
}
