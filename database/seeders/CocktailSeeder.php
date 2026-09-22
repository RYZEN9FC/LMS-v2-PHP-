<?php

namespace Database\Seeders;

use App\Models\Outlet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CocktailSeeder extends Seeder
{
    public function run(): void
    {
        $outlet = Outlet::query()->firstOrFail();
        $products = DB::table('products')->where('outlet_id', $outlet->id)->where('is_active', true)->pluck('id', 'name');

        $cocktails = [
            'Bloody Mary' => ['SMIRNOFF TRIPLE DISTILLED VODKA' => 60],
            'Cosmopolitan' => ['SMIRNOFF TRIPLE DISTILLED VODKA' => 60],
            'Daiquiri' => ['BACARDI CARTA BLANCA CLASSIC SUPERIOR WHITE RUM' => 60],
            'Dill With It!' => ['BACARDI CARTA BLANCA CLASSIC SUPERIOR WHITE RUM' => 70],
            'Gin & Tonic' => ['GREATER THAN LONDON DRY GIN SMALL BATCH' => 60],
            'Hot Toddy' => ['KYRON PREMIUM BRANDY FRENCH BLEND' => 60],
            'Kiraak Kaapi' => [
                'ROKU GIN' => 40,
                'GREATER THAN LONDON DRY GIN SMALL BATCH' => 20,
                'BACARDI CARTA BLANCA CLASSIC SUPERIOR WHITE RUM' => 10,
            ],
            'LIIT' => [
                'SMIRNOFF TRIPLE DISTILLED VODKA' => 40,
                'BACARDI CARTA BLANCA CLASSIC SUPERIOR WHITE RUM' => 20,
                'DESMONDJI AGAVE 51 CRAFT INDIAN AGAVE' => 20,
                'GREATER THAN LONDON DRY GIN SMALL BATCH' => 20,
            ],
            'Lite Lo' => [
                'BACARDI CARTA BLANCA CLASSIC SUPERIOR WHITE RUM' => 45,
                'SMIRNOFF TRIPLE DISTILLED VODKA' => 25,
            ],
            'Margarita' => [
                'DESMONDJI AGAVE 51 CRAFT INDIAN AGAVE' => 45,
                'SMIRNOFF TRIPLE DISTILLED VODKA' => 15,
            ],
            'Martini' => [
                'GREATER THAN LONDON DRY GIN SMALL BATCH' => 60,
                'SULA CHENIN BLANC' => 5,
            ],
            'Meloni' => [
                'TOKI SUNTORY WHISKY' => 55,
                'SMIRNOFF TRIPLE DISTILLED VODKA' => 10,
            ],
            'Miyaa Martini' => [
                'ROKU GIN' => 50,
                'SULA CHENIN BLANC' => 5,
            ],
            'Mojito' => ['BACARDI CARTA BLANCA CLASSIC SUPERIOR WHITE RUM' => 60],
            'Moonfire' => [
                'JOSE CUERVO ESPECIAL BLUE AGAVE SILVER TEQUILA' => 30,
                'ROKU GIN' => 30,
                'SMIRNOFF TRIPLE DISTILLED VODKA' => 5,
            ],
            'Moscow Mule' => ['SMIRNOFF TRIPLE DISTILLED VODKA' => 60],
            'Negroni' => [
                'GREATER THAN LONDON DRY GIN SMALL BATCH' => 30,
                'CAMPARI BITTER' => 30,
                // 30 ml sweet vermouth × 85% wine content.
                'SULA CABERNET SHIRAJ RED WINE' => 25.5,
            ],
            'Old Fashioned' => ['JIM BEAM KENTUCKY STRAIGHT BOURBON WHISKEY' => 60],
            'Picante' => ['DESMONDJI AGAVE 51 CRAFT INDIAN AGAVE' => 45],
            'Sangria Red' => [
                'SULA CABERNET SHIRAJ RED WINE' => 120,
                'KYRON PREMIUM BRANDY FRENCH BLEND' => 10,
            ],
            'Sangria White' => [
                'SULA CHENIN BLANC' => 120,
                'SMIRNOFF TRIPLE DISTILLED VODKA' => 30,
            ],
            'Screwdriver' => ['SMIRNOFF TRIPLE DISTILLED VODKA' => 60],
            'Sex on the Beach' => ['SMIRNOFF TRIPLE DISTILLED VODKA' => 60],
            'Sip in Peace' => [
                'DESMONDJI AGAVE 51 CRAFT INDIAN AGAVE' => 25,
                'JOSE CUERVO ESPECIAL BLUE AGAVE SILVER TEQUILA' => 20,
                'Creyente Mezcal' => 2.5,
            ],
            'The OG' => ['ROKU GIN' => 60],
            'Whisky Sour' => ['JIM BEAM KENTUCKY STRAIGHT BOURBON WHISKEY' => 60],
            'Wild Tea' => ['TOKI SUNTORY WHISKY' => 60],
        ];

        DB::transaction(function () use ($outlet, $products, $cocktails): void {
            $signatureCocktails = ['Dill With It!', 'Kiraak Kaapi', 'Lite Lo', 'Meloni', 'Miyaa Martini', 'Moonfire', 'Sip in Peace', 'The OG', 'Wild Tea'];
            foreach ($cocktails as $name => $ingredients) {
                $recipe = DB::table('recipes')->where('outlet_id', $outlet->id)->where('name', $name)->first();
                if ($recipe) {
                    DB::table('recipes')->where('id', $recipe->id)->update(['drink_type' => in_array($name, $signatureCocktails, true) ? 'signature' : 'classic']);
                    continue;
                }

                $ingredientRows = [];
                foreach ($ingredients as $productName => $volume) {
                    $productId = $products->get($productName);
                    if (! $productId) {
                        throw new \RuntimeException("Cannot create {$name}: product '{$productName}' is missing.");
                    }
                    $ingredientRows[] = ['product_id' => $productId, 'volume_ml' => $volume];
                }

                $recipeId = DB::table('recipes')->insertGetId([
                    'outlet_id' => $outlet->id,
                    'name' => $name,
                    'drink_type' => in_array($name, $signatureCocktails, true) ? 'signature' : 'classic',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                foreach ($ingredientRows as $ingredient) {
                    DB::table('recipe_ingredients')->insert([
                        'recipe_id' => $recipeId,
                        'product_id' => $ingredient['product_id'],
                        'volume_ml' => $ingredient['volume_ml'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });
    }
}
