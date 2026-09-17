<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $this->seedAndSignIn();

        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('Current expected stock')
            ->assertSee('ABSOLUT VODKA');
    }

    public function test_interval_report_uses_sale_type_columns_and_closing_stock(): void
    {
        $this->seedAndSignIn();

        $response = $this->get('/reports/interval?from=2026-09-07&to=2026-09-09');

        $response->assertOk()
            ->assertSee('30 ml')
            ->assertSee('60 ml')
            ->assertSee('Full')
            ->assertSee('Cocktails')
            ->assertSee('Expected stock')
            ->assertSee('16 bottles + 0 ml')
            ->assertSee('data-spirit-filter', false)
            ->assertSee('data-spirit-type="Other"', false);
    }

    public function test_interval_report_has_a_fixed_edge_daily_navigator(): void
    {
        $this->seedAndSignIn();

        $response = $this->get('/reports/interval?from=2026-07-01&to=2026-07-31');

        $response->assertOk()
            ->assertSee('Daily intervals')
            ->assertSee('interval-scroll-prev is-unavailable', false)
            ->assertSee('interval-viewport', false)
            ->assertSee('interval-scroll-next', false)
            ->assertSee('data-day-tab="2026-07-01"', false)
            ->assertSee('data-day-tab="2026-07-31"', false);
    }

    public function test_native_excel_exports_are_available(): void
    {
        $this->seedAndSignIn();

        $this->get('/reports/current/excel?as_at=2026-09-09')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->get('/reports/interval/excel?from=2026-09-07&to=2026-09-09')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_interval_excel_has_one_worksheet_per_daily_interval(): void
    {
        $this->seedAndSignIn();
        Product::where('name', 'ABSOLUT VODKA')->update(['spirit_type' => 'Vodka']);
        Product::where('name', 'APEROL')->update(['spirit_type' => 'Liqueur']);
        Product::where('name', 'JAMESON')->update(['spirit_type' => 'Whisky']);

        $response = $this->get('/reports/interval/excel?from=2026-09-07&to=2026-09-09')->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'pegwise-interval-');

        try {
            file_put_contents($path, $response->streamedContent());
            $book = IOFactory::load($path);

            $this->assertSame(['7-8 Sep', '8-9 Sep', '9-10 Sep'], $book->getSheetNames());
            foreach ($book->getWorksheetIterator() as $sheet) {
                $this->assertSame('VODKA · 1 brands', $sheet->getCell('A4')->getValue());
                $this->assertSame('Brand', $sheet->getCell('A5')->getValue());
                $this->assertSame('Opening stock', $sheet->getCell('C5')->getValue());
                $this->assertSame('Closing stock Total ml', $sheet->getCell('N5')->getValue());
                $this->assertCount(3, $sheet->getTableCollection());
            }

            $book->disconnectWorksheets();
        } finally {
            unlink($path);
        }
    }
}
