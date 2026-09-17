<?php

namespace Tests\Feature;

use App\Services\ExcisePreviewParser;
use App\Services\PosPreviewParser;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ExtractionRegressionTest extends TestCase
{
    private function parseWorkbook(string $title, array $rows): array
    {
        $file = tempnam(sys_get_temp_dir(), 'pegwise-parser-').'.xlsx';
        $book = new Spreadsheet;
        $book->getActiveSheet()->setTitle($title)->fromArray($rows, null, 'A1', true);
        try {
            (new Xlsx($book))->save($file);

            return app(PosPreviewParser::class)->parse($file);
        } finally {
            $book->disconnectWorksheets();
            if (is_file($file)) {
                unlink($file);
            }
            if (is_file(substr($file, 0, -5))) {
                unlink(substr($file, 0, -5));
            }
        }
    }

    public function test_negative_quantity_and_incorrect_grand_total_are_rejected(): void
    {
        foreach ([[-1, -1], [2, 3]] as [$quantity, $total]) {
            try {
                $this->parseWorkbook('Item Wise Sales Report', [
                    ['Start Date', '10 Jul 2026 12:00 AM'], ['End Date', '22 Aug 2026 11:59 PM'],
                    ['Product Name', 'Total Qty Sold', 'Amount'], ['Beer', $quantity, 100], ['Grand Total', $total, 100],
                ]);
                $this->fail('Invalid quantities must never produce a stock preview.');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('report', $error->errors());
            }
        }
    }

    public function test_discounted_sales_explicit_comps_nc_and_voids_remain_distinct(): void
    {
        $data = $this->parseWorkbook('Item Wise Customer Report', [
            ['Start Date', '10 Jul 2026 12:00 AM'], ['End Date', '22 Aug 2026 11:59 PM'],
            ['Product Name', 'Item Name', 'Order Date', 'Qty', 'Amount', 'Discount', 'Amount Before Tax', 'Item Status', 'Order Status', 'Item Type', 'Order Subtype', 'Category Name'],
            ['Promo', 'Promo', '20 08 2026', 1, 100, 100, 0, 'COMPLETED', 'CLOSED', 'liquor-item', 'promotion', 'Beer'],
            ['Gift', 'Gift', '20 08 2026', 2, 200, 200, 0, 'COMPLETED', 'CLOSED', 'liquor-item', 'complimentary', 'Beer'],
            ['Staff', 'Staff', '20 08 2026', 3, 300, 300, 0, 'COMPLETED', 'CLOSED', 'nc-liquor-item', 'normal', 'Beer'],
            ['Void', 'Void', '20 08 2026', 4, 400, 0, 400, 'VOID', 'CLOSED', 'liquor-item', 'normal', 'Beer'],
            ['Grand Total', null, null, 10, 1000],
        ]);
        $this->assertEquals(1, $data['quantity']);
        $this->assertEquals(2, $data['comp']);
        $this->assertEquals(3, $data['nc']);
        $this->assertEquals(0, $data['revenue']);
        $this->assertSame(1, $data['excluded_rows']);
        $this->assertSame(3, $data['items']);
        $this->assertSame('daily', $data['granularity']);
    }

    public function test_all_supplied_indents_reconcile_and_keep_pack_identity(): void
    {
        $files = glob(dirname(base_path()).'/Indents and Bills for testing/Full indents from start to 8th sep/*.pdf');
        if (! $files) {
            $this->markTestSkipped('Local source documents unavailable.');
        }
        $this->assertCount(10, $files);
        foreach ($files as $file) {
            $data = app(ExcisePreviewParser::class)->parse($file);
            $this->assertEqualsWithDelta($data['invoice_value'], array_sum(array_column($data['lines'], 'amount')), 0.01, basename($file));
            foreach ($data['lines'] as $line) {
                $this->assertMatchesRegularExpression('/^\d{4}[A-Z]{3}$/', $line['code']);
                $this->assertEquals($line['bottles'] * $line['size_ml'], $line['volume_ml']);
            }
            if (basename($file) === '7 july.pdf') {
                $this->assertCount(35, $data['lines']);
                $this->assertSame('BUDWEISER KING OF BEERS', $data['lines'][0]['name']);
                $this->assertSame('0904JMG', $data['lines'][14]['code']);
            }
            if (basename($file) === '7 july pt2.pdf') {
                $this->assertSame(12, $data['bottles']);
                $this->assertSame('1984QMG', $data['lines'][0]['code']);
            }
        }
    }

    public function test_all_supplied_pos_formats(): void
    {
        $root = dirname(base_path()).'/Indents and Bills for testing/';
        foreach (glob($root.'*.xlsx') as $file) {
            if (str_starts_with(basename($file), '~$')) {
                continue;
            }
            $data = app(PosPreviewParser::class)->parse($file);
            if (str_contains(basename($file), '_sample')) {
                $this->assertEquals(100, $data['quantity']);
            } else {
                $this->assertEquals(6676, $data['quantity']);
                $this->assertEquals(12, $data['comp']);
                $this->assertEquals(285, $data['nc']);
                $this->assertSame(141, $data['items']);
                $this->assertSame(311, $data['excluded_non_liquor']);
            }
        }
        $files = glob($root.'Full Sale from start to 8th sep/Item_Wise_Customer*.xlsx');
        if (! $files) {
            $this->markTestSkipped('Local customer report unavailable.');
        }
        $data = app(PosPreviewParser::class)->parse($files[0]);
        $this->assertSame('daily', $data['granularity']);
        $this->assertSame('2026-07-10', $data['from']);
        $this->assertSame('2026-09-08', $data['to']);
        $this->assertEquals(9115, $data['quantity'] + $data['comp'] + $data['nc']);
        $this->assertSame(0, (int) $data['comp'], 'Discounts are not automatically complimentary.');
        $this->assertSame(25830, $data['source_rows']);
    }
}
