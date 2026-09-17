<?php

namespace Tests\Feature;

use App\Models\Outlet;
use App\Models\Product;
use App\Services\ExpectedStockReportService;
use App\Support\StockFormatter;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class StockCalculationTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        $this->seedAndSignIn();

        return Product::create(['outlet_id' => 1, 'name' => 'Test cost', 'bottle_size_ml' => 330]);
    }

    public function test_exact_receipt_value_and_zero_balance_invariant(): void
    {
        $product = $this->product();
        $product->movements()->create(['outlet_id' => 1, 'effective_date' => '2026-07-01', 'movement_type' => 'indent', 'volume_ml' => 47520, 'value_change' => 14412, 'receipt_price_per_ml' => round(14412 / 47520, 6)]);
        $balance = app(ExpectedStockReportService::class)->productBalance($product, Carbon::parse('2026-07-01'));
        $this->assertSame(14412.0, $balance['stock_value']);
        $product->movements()->create(['outlet_id' => 1, 'effective_date' => '2026-07-02', 'movement_type' => 'sale', 'volume_ml' => -47520]);
        $balance = app(ExpectedStockReportService::class)->productBalance($product, Carbon::parse('2026-07-02'));
        $this->assertSame(0.0, $balance['stock_value']);
        $this->assertSame(0.0, $balance['expected_ml']);
    }

    public function test_existing_deficit_covered_by_receipt_leaves_no_phantom_cost(): void
    {
        $product = $this->product();
        $product->movements()->create(['outlet_id' => 1, 'effective_date' => '2026-07-01', 'movement_type' => 'sale', 'volume_ml' => -330]);
        $product->movements()->create(['outlet_id' => 1, 'effective_date' => '2026-07-02', 'movement_type' => 'indent', 'volume_ml' => 330, 'value_change' => 100]);
        $balance = app(ExpectedStockReportService::class)->productBalance($product, Carbon::parse('2026-07-02'));
        $this->assertSame(0.0, $balance['stock_value']);
        $this->assertNull($balance['cost_of_sales']);
    }

    public function test_missing_opening_cost_is_unknown_not_free(): void
    {
        $product = $this->product();
        $product->movements()->create(['outlet_id' => 1, 'effective_date' => '2026-07-01', 'movement_type' => 'opening', 'volume_ml' => 330]);
        $balance = app(ExpectedStockReportService::class)->productBalance($product, Carbon::parse('2026-07-01'));
        $this->assertNull($balance['stock_value']);
        $this->get('/reports/current?as_at=2026-07-01')->assertOk()->assertSee('Unknown')->assertSee('incomplete costs');
    }

    public function test_same_day_receipts_precede_sales_regardless_of_upload_order(): void
    {
        $product = $this->product();
        $product->movements()->create(['outlet_id' => 1, 'effective_date' => '2026-07-01', 'movement_type' => 'sale', 'volume_ml' => -330]);
        $product->movements()->create(['outlet_id' => 1, 'effective_date' => '2026-07-01', 'movement_type' => 'indent', 'volume_ml' => 660, 'value_change' => 200]);
        $balance = app(ExpectedStockReportService::class)->productBalance($product, Carbon::parse('2026-07-01'));
        $this->assertSame(100.0, $balance['stock_value']);
        $this->assertSame(100.0, $balance['cost_of_sales']);
    }

    public function test_interval_shows_initial_stock_introduced_after_start(): void
    {
        $product = $this->product();
        $product->movements()->create(['outlet_id' => 1, 'effective_date' => '2026-07-02', 'movement_type' => 'opening', 'volume_ml' => 330]);
        $report = app(ExpectedStockReportService::class)->interval(Outlet::findOrFail(1), Carbon::parse('2026-07-01'), Carbon::parse('2026-07-03'));
        $row = $report['rows']->first(fn ($r) => $r['product']->id === $product->id);
        $this->assertEquals($row['ending_ml'], $row['opening_ml'] + $row['total_opening_ml'] + $row['total_indent_ml'] - $row['total_sale_ml']);
        $this->assertSame(330.0, $row['total_opening_ml']);
        $this->get('/reports/interval?from=2026-07-01&to=2026-07-03')->assertOk()->assertSee('Initial stock:');
    }

    public function test_dates_are_validated_without_limiting_the_interval_length(): void
    {
        $this->seedAndSignIn();
        $this->getJson('/reports/current?as_at=garbage')->assertUnprocessable();
        $this->get('/reports/interval?from=2026-07-01&to=2026-09-09')->assertOk();
        $this->get('/reports/interval?from=2026-07-01&to=2026-07-31')->assertOk();
        $this->getJson('/reports/interval?from=2026-09-09&to=2026-07-01')->assertUnprocessable();
    }

    public function test_stock_formatter_preserves_ml_precision_and_negative_sign_scope(): void
    {
        $this->assertSame('0 bottles + 0.5 ml', StockFormatter::bottlesAndMl(0.5, 750));
        $this->assertSame('-(0 bottles + 30 ml)', StockFormatter::bottlesAndMl(-30, 750));
        $this->assertSame('10,830.5 ml', StockFormatter::ml(10830.5));
    }

    public function test_excel_numbers_are_numeric_and_brand_names_cannot_be_formulas(): void
    {
        $product = $this->product();
        $product->update(['name' => '=1+1']);
        $product->movements()->create(['outlet_id' => 1, 'effective_date' => '2026-07-01', 'movement_type' => 'indent', 'volume_ml' => 330, 'value_change' => 100]);
        $response = $this->get('/reports/current/excel?as_at=2026-07-01')->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'pegwise-xlsx-');
        try {
            file_put_contents($path, $response->streamedContent());
            $book = IOFactory::load($path);
            $sheet = $book->getActiveSheet();
            $this->assertSame('s', $sheet->getCell('A6')->getDataType());
            $this->assertSame('=1+1', $sheet->getCell('A6')->getValue());
            $this->assertSame('n', $sheet->getCell('C6')->getDataType());
            $this->assertSame('n', $sheet->getCell('F6')->getDataType());
            $this->assertEquals(100, $sheet->getCell('F6')->getValue());
            $book->disconnectWorksheets();
        } finally {
            unlink($path);
        }
    }
}
