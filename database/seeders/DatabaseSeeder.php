<?php

namespace Database\Seeders;

use App\Models\Organisation;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $organisation = Organisation::firstOrCreate(
            ['slug' => 'sip-society'],
            ['name' => 'Sip Society', 'timezone' => 'Asia/Kolkata'],
        );

        $outlet = Outlet::firstOrCreate(
            ['organisation_id' => $organisation->id, 'name' => 'Sip Society'],
            ['code' => 'SIP'],
        );

        $user = User::firstOrCreate(
            ['email' => 'demo@example.com'],
            ['name' => 'Demo Owner', 'password' => 'password', 'organisation_id' => $organisation->id],
        );

        $user->outlets()->syncWithoutDetaching([
            $outlet->id => ['role' => 'owner', 'is_active' => true],
        ]);

        $absolut = Product::firstOrCreate(
            ['outlet_id' => $outlet->id, 'name' => 'ABSOLUT VODKA'],
            ['bottle_size_ml' => 750, 'excise_code' => 'ABS-750'],
        );
        $aperol = Product::firstOrCreate(
            ['outlet_id' => $outlet->id, 'name' => 'APEROL'],
            ['bottle_size_ml' => 750, 'excise_code' => 'APE-750'],
        );
        $jameson = Product::firstOrCreate(
            ['outlet_id' => $outlet->id, 'name' => 'JAMESON'],
            ['bottle_size_ml' => 750, 'excise_code' => 'JAM-750'],
        );

        if (StockMovement::where('outlet_id', $outlet->id)->exists()) {
            return;
        }

        $add = function (Product $product, string $date, string $type, float $ml, ?string $saleType = null, ?float $saleQuantity = null, ?float $bottlePrice = null): void {
            StockMovement::create([
                'outlet_id' => $product->outlet_id,
                'product_id' => $product->id,
                'effective_date' => $date,
                'movement_type' => $type,
                'sale_type' => $saleType,
                'sale_quantity' => $saleQuantity,
                'volume_ml' => $ml,
                'value_change' => $bottlePrice === null ? 0 : $ml * ($bottlePrice / (float) $product->bottle_size_ml),
                'receipt_price_per_ml' => $bottlePrice === null ? null : $bottlePrice / (float) $product->bottle_size_ml,
                'reference' => 'Demo data',
            ]);
        };

        // Opening balance, excise refills, and POS consumption for 7-9 September.
        $add($absolut, '2026-09-07', 'opening', 12000, null, null, 1800);
        $add($absolut, '2026-09-07', 'indent', 3000, null, null, 1950);
        $add($absolut, '2026-09-08', 'indent', 2250, null, null, 1980);
        $add($absolut, '2026-09-07', 'sale', -240, 'peg_30', 8);
        $add($absolut, '2026-09-07', 'sale', -240, 'peg_60', 4);
        $add($absolut, '2026-09-07', 'sale', -750, 'full_bottle', 1);
        $add($absolut, '2026-09-07', 'sale', -90, 'cocktail', 2);
        $add($absolut, '2026-09-08', 'sale', -300, 'peg_30', 10);
        $add($absolut, '2026-09-08', 'sale', -120, 'peg_60', 2);
        $add($absolut, '2026-09-08', 'sale', -135, 'cocktail', 3);
        $add($absolut, '2026-09-09', 'sale', -60, 'peg_30', 2);

        $add($aperol, '2026-09-07', 'opening', 3750, null, null, 2100);
        $add($aperol, '2026-09-09', 'indent', 1500, null, null, 2150);
        $add($aperol, '2026-09-07', 'sale', -180, 'cocktail', 3);
        $add($aperol, '2026-09-08', 'sale', -120, 'cocktail', 2);

        $add($jameson, '2026-09-07', 'opening', 6750, null, null, 2400);
        $add($jameson, '2026-09-08', 'sale', -180, 'peg_30', 6);
        $add($jameson, '2026-09-09', 'sale', -750, 'full_bottle', 1);
    }
}
