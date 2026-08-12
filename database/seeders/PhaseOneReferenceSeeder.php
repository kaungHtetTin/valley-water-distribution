<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\PriceBook;
use App\Models\PriceType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\Sku;
use App\Models\SkuUomConversion;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class PhaseOneReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::query()->updateOrCreate(
            ['code' => 'VALLEY'],
            [
                'name' => 'Valley Water',
                'legal_name' => 'Valley Water Distribution',
                'default_locale' => 'my-MM',
                'currency' => 'MMK',
                'timezone' => 'Asia/Yangon',
                'status' => 'active',
            ],
        );

        $branch = Branch::query()->updateOrCreate(
            ['organization_id' => $organization->id, 'code' => 'TGI'],
            ['name_en' => 'Taunggyi Branch', 'name_my' => 'တောင်ကြီး ရုံးခွဲ', 'status' => 'active'],
        );

        $areas = collect([
            ['code' => 'TGI', 'name_en' => 'Taunggyi', 'name_my' => 'တောင်ကြီး', 'sort_order' => 10],
            ['code' => 'ATY', 'name_en' => 'Aye Thar Yar', 'name_my' => 'အေးသာယာ', 'sort_order' => 20],
            ['code' => 'NSN', 'name_en' => 'Nam San', 'name_my' => 'နမ့်စန်', 'sort_order' => 30],
        ])->mapWithKeys(function (array $attributes) use ($organization): array {
            $area = Area::query()->updateOrCreate(
                ['organization_id' => $organization->id, 'code' => $attributes['code']],
                [...$attributes, 'status' => 'active'],
            );

            return [$attributes['code'] => $area];
        });

        Warehouse::query()->updateOrCreate(
            ['organization_id' => $organization->id, 'code' => 'TGI-WH'],
            [
                'branch_id' => $branch->id,
                'area_id' => $areas['TGI']->id,
                'name_en' => 'TGI Warehouse',
                'name_my' => 'တောင်ကြီး ဂိုဒေါင်',
                'kind' => 'distribution',
                'status' => 'active',
            ],
        );

        Warehouse::query()->updateOrCreate(
            ['organization_id' => $organization->id, 'code' => 'NSN-WH'],
            [
                'branch_id' => $branch->id,
                'area_id' => $areas['NSN']->id,
                'name_en' => 'Nam San Warehouse',
                'name_my' => 'နမ့်စန် ဂိုဒေါင်',
                'kind' => 'distribution',
                'status' => 'active',
            ],
        );

        $brand = Brand::query()->updateOrCreate(
            ['organization_id' => $organization->id, 'code' => 'VALLEY'],
            ['name_en' => 'Valley', 'name_my' => 'Valley', 'status' => 'active'],
        );

        foreach ([
            ['code' => 'BTL', 'name_en' => 'Bottle', 'name_my' => 'ရေဘူး', 'symbol' => 'btl'],
            ['code' => 'PACK', 'name_en' => 'Pack', 'name_my' => 'အထုပ်', 'symbol' => 'pack'],
            ['code' => 'CTN', 'name_en' => 'Carton', 'name_my' => 'ကတ်ထူပုံး', 'symbol' => 'ctn'],
        ] as $unit) {
            UnitOfMeasure::query()->updateOrCreate(
                ['organization_id' => $organization->id, 'code' => $unit['code']],
                [...$unit, 'dimension' => 'quantity', 'decimal_places' => 0, 'status' => 'active'],
            );
        }

        $bottle = UnitOfMeasure::query()
            ->where('organization_id', $organization->id)
            ->where('code', 'BTL')
            ->firstOrFail();

        $category = ProductCategory::query()->updateOrCreate(
            ['organization_id' => $organization->id, 'code' => 'WATER'],
            ['name_en' => 'Purified Drinking Water', 'name_my' => 'သန့်စင်သောက်ရေ', 'status' => 'active'],
        );

        $product = Product::query()->updateOrCreate(
            ['organization_id' => $organization->id, 'code' => 'VALLEY-WATER'],
            [
                'brand_id' => $brand->id,
                'product_category_id' => $category->id,
                'name_en' => 'Valley Purified Drinking Water',
                'name_my' => 'Valley သန့်စင်သောက်ရေ',
                'status' => 'active',
            ],
        );

        foreach ([
            ['code' => 'VAL-500', 'name_en' => 'Valley Water 0.5 L', 'name_my' => 'Valley သောက်ရေ ၀.၅ လီတာ', 'size_label' => '0.5 L', 'volume_ml' => 500],
            ['code' => 'VAL-700', 'name_en' => 'Valley Water 0.7 L', 'name_my' => 'Valley သောက်ရေ ၀.၇ လီတာ', 'size_label' => '0.7 L', 'volume_ml' => 700],
            ['code' => 'VAL-1000', 'name_en' => 'Valley Water 1 L', 'name_my' => 'Valley သောက်ရေ ၁ လီတာ', 'size_label' => '1 L', 'volume_ml' => 1000],
        ] as $attributes) {
            $sku = Sku::query()->updateOrCreate(
                ['organization_id' => $organization->id, 'code' => $attributes['code']],
                [
                    ...$attributes,
                    'product_id' => $product->id,
                    'base_uom_id' => $bottle->id,
                    'minimum_order_quantity' => 1,
                    'order_step_quantity' => 1,
                    'minimum_delivery_quantity' => 1,
                    'sale_status' => 'saleable',
                    'status' => 'active',
                ],
            );

            SkuUomConversion::query()->updateOrCreate(
                ['sku_id' => $sku->id, 'uom_id' => $bottle->id, 'version' => 1],
                [
                    'organization_id' => $organization->id,
                    'factor_to_base' => 1,
                    'is_selling_unit' => true,
                    'is_kpi_base' => true,
                    'effective_from' => '2026-01-01',
                    'status' => 'active',
                ],
            );
        }

        foreach ([
            ['code' => 'RETAIL', 'name_en' => 'Retail', 'name_my' => 'လက်လီ', 'precedence' => 300, 'requires_approval' => false],
            ['code' => 'WHOLESALE', 'name_en' => 'Wholesale', 'name_my' => 'လက်ကား', 'precedence' => 200, 'requires_approval' => false],
            ['code' => 'SPECIAL', 'name_en' => 'Special', 'name_my' => 'အထူး', 'precedence' => 100, 'requires_approval' => true],
        ] as $attributes) {
            $priceType = PriceType::query()->updateOrCreate(
                ['organization_id' => $organization->id, 'code' => $attributes['code']],
                [...$attributes, 'status' => 'active'],
            );

            PriceBook::query()->updateOrCreate(
                ['organization_id' => $organization->id, 'code' => $attributes['code'].'-DEFAULT'],
                [
                    'price_type_id' => $priceType->id,
                    'name_en' => $attributes['name_en'].' Default',
                    'name_my' => $attributes['name_my'].' မူလဈေးနှုန်း',
                    'currency' => 'MMK',
                    'scope_type' => 'organization_default',
                    'effective_from' => '2026-01-01',
                    'status' => 'active',
                ],
            );
        }

        foreach ([
            ['code' => 'MASTER-ADMIN', 'name_en' => 'Master Data Administrator', 'name_my' => 'အခြေခံဒေတာ စီမံခန့်ခွဲသူ', 'permissions' => Role::PERMISSIONS],
            ['code' => 'MASTER-VIEWER', 'name_en' => 'Master Data Viewer', 'name_my' => 'အခြေခံဒေတာ ကြည့်ရှုသူ', 'permissions' => ['master_data.view', 'master_data.export']],
        ] as $role) {
            Role::query()->updateOrCreate(
                ['organization_id' => $organization->id, 'code' => $role['code']],
                [...$role, 'status' => 'active'],
            );
        }
    }
}
