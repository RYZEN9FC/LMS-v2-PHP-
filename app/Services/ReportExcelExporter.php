<?php

namespace App\Services;

use App\Models\Outlet;
use App\Support\StockFormatter;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Table;
use PhpOffice\PhpSpreadsheet\Worksheet\Table\TableStyle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExcelExporter
{
    private const CATEGORY_ORDER = ['Beer', 'Alcopop', 'Wine', 'Vodka', 'Gin', 'Rum', 'Tequila', 'Whisky', 'Brandy', 'Liqueur', 'Other'];

    /** @param Collection<int, array<string, mixed>> $rows */
    public function current(Outlet $outlet, Carbon $asAt, Collection $rows, ?string $periodNotice = null): StreamedResponse
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Current stock');
        $sheet->mergeCells('A1:E1')->setCellValue('A1', $outlet->name.' · Current expected stock');
        $sheet->mergeCells('A2:E2')->setCellValue('A2', 'Stock position as at the end of '.$asAt->format('j M Y'));
        $sheet->mergeCells('A3:E3')->setCellValue('A3', $periodNotice ?: 'Quantities are shown as whole bottles plus remaining millilitres. Landing price is the most recent receipt price.');

        $rowNumber = 5;
        foreach ($this->categoryGroups($rows) as $category => $categoryRows) {
            $sheet->mergeCells("A{$rowNumber}:E{$rowNumber}")->setCellValue("A{$rowNumber}", strtoupper($category).' · '.$categoryRows->count().' brands');
            $this->styleCategoryTitle($sheet, $rowNumber, 'E');
            $rowNumber++;
            $headerRow = $rowNumber;
            $sheet->fromArray(['Item', 'Bottle size', 'Bottles + ml', 'Landing price', 'Cost of available stock'], null, 'A'.$rowNumber++);
            $dataStart = $rowNumber;
            foreach ($categoryRows as $row) {
                $product = $row['product'];
                $sheet->fromArray([
                    $product->name,
                    (float) $product->bottle_size_ml,
                    StockFormatter::bottlesAndMl((float) $row['expected_ml'], (float) $product->bottle_size_ml, true),
                    $row['latest_bottle_price'] ?? 'Unknown',
                    $row['stock_value'] ?? 'Unknown',
                ], null, 'A'.$rowNumber++, true);
            }
            $dataEnd = $rowNumber - 1;
            $this->addTable($sheet, "A{$headerRow}:E{$dataEnd}", 'Current_'.$category);
            $this->styleHeader($sheet, "A{$headerRow}:E{$headerRow}");
            $sheet->getStyle("B{$dataStart}:B{$dataEnd}")->getNumberFormat()->setFormatCode('#,##0.##" ml"');
            $sheet->getStyle("D{$dataStart}:E{$dataEnd}")->getNumberFormat()->setFormatCode('"₹"#,##0.00');
            $rowNumber += 2;
        }

        $totalRow = max(5, $rowNumber);
        $sheet->mergeCells("A{$totalRow}:D{$totalRow}")
            ->setCellValue("A{$totalRow}", $rows->contains(fn ($row) => $row['stock_value'] === null) ? 'Known stock value subtotal (incomplete)' : 'Total stock value')
            ->setCellValue("E{$totalRow}", (float) $rows->sum('stock_value'));
        $sheet->getStyle("A{$totalRow}:E{$totalRow}")->getFont()->setBold(true);
        $sheet->getStyle("A{$totalRow}:E{$totalRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF7ED');
        $sheet->getStyle("E{$totalRow}")->getNumberFormat()->setFormatCode('"₹"#,##0.00');
        $this->finish($sheet, "A1:E{$totalRow}", ['A' => 42, 'B' => 15, 'C' => 34, 'D' => 19, 'E' => 24]);
        $sheet->freezePane('A5');

        return $this->download($book, 'expected-stock-'.$asAt->toDateString().'.xlsx');
    }

    /** @param array{days: Collection<int, Carbon>, rows: Collection<int, array<string, mixed>>} $report */
    public function interval(Outlet $outlet, Carbon $from, Carbon $to, array $report): StreamedResponse
    {
        $book = new Spreadsheet;
        $summary = $book->getActiveSheet();
        $summary->setTitle('Summary');
        $this->writeIntervalSheet($summary, $outlet, $from, $to, $report, null);

        $runningStock = $report['rows']->mapWithKeys(fn ($row) => [$row['product']->id => (float) $row['opening_ml']])->all();
        foreach ($report['days'] as $day) {
            $sheet = $book->createSheet();
            $sheet->setTitle($this->intervalSheetTitle($day));
            $this->writeIntervalSheet($sheet, $outlet, $from, $to, $report, $day, $runningStock);
        }
        $book->setActiveSheetIndex(0);

        return $this->download($book, 'stock-movement-'.$from->toDateString().'-to-'.$to->toDateString().'.xlsx');
    }

    /**
     * @param array{days: Collection<int, Carbon>, rows: Collection<int, array<string, mixed>>} $report
     * @param array<int, float>|null $runningStock
     */
    private function writeIntervalSheet(Worksheet $sheet, Outlet $outlet, Carbon $from, Carbon $to, array $report, ?Carbon $day, ?array &$runningStock = null): void
    {
        $lastColumn = 'N';
        $isSummary = $day === null;
        $title = $isSummary ? 'Aggregated stock movement' : 'Daily stock movement';
        $period = $isSummary
            ? $from->format('j M Y').' – '.$to->format('j M Y')
            : $day->format('j M Y').' – '.$day->copy()->addDay()->format('j M Y').' · 24-hour interval';
        $sheet->mergeCells("A1:{$lastColumn}1")->setCellValue('A1', $outlet->name.' · '.$title);
        $sheet->mergeCells("A2:{$lastColumn}2")->setCellValue('A2', $period);
        $sheet->mergeCells("A3:{$lastColumn}3")->setCellValue('A3', $report['periodNotice'] ?? 'Stock received combines indent deliveries and any stock introduced during the selected period.');

        $headers = [
            'Brand', 'Bottle size', 'Opening stock', 'Stock received',
            '30 ml pegs', '60 ml pegs', 'Full bottles', 'Cocktails', 'Other ml servings',
            'Total stock use Bottles + ml', 'Total stock use Total ml',
            'Closing stock Bottles + ml', 'Landing price', 'Stock cost',
        ];
        $rowNumber = 5;
        $sheetHasReceipt = false;
        foreach ($this->categoryGroups($report['rows']) as $category => $categoryRows) {
            $sheet->mergeCells("A{$rowNumber}:{$lastColumn}{$rowNumber}")->setCellValue("A{$rowNumber}", strtoupper($category).' · '.$categoryRows->count().' brands');
            $this->styleCategoryTitle($sheet, $rowNumber, $lastColumn);
            $rowNumber++;
            $headerRow = $rowNumber;
            $sheet->fromArray($headers, null, 'A'.$rowNumber++);
            $dataStart = $rowNumber;
            $categoryHasReceipt = false;

            foreach ($categoryRows as $row) {
                $product = $row['product'];
                if ($isSummary) {
                    $sales = ['peg_30' => 0.0, 'peg_60' => 0.0, 'full_bottle' => 0.0, 'cocktail' => 0.0, 'measured' => 0.0];
                    foreach ($row['days'] as $daily) {
                        foreach ($sales as $key => $value) {
                            $sales[$key] += (float) ($daily['sales'][$key] ?? 0);
                        }
                    }
                    $openingMl = (float) $row['opening_ml'];
                    $receivedMl = (float) $row['total_indent_ml'] + (float) $row['total_opening_ml'];
                    $saleMl = (float) $row['total_sale_ml'];
                    $closingMl = (float) $row['ending_ml'];
                } else {
                    $daily = $row['days'][$day->toDateString()];
                    $sales = $daily['sales'];
                    $openingMl = (float) $runningStock[$product->id];
                    $receivedMl = (float) $daily['indent_ml'] + (float) $daily['opening_ml'];
                    $saleMl = (float) $daily['sale_ml'];
                    $closingMl = $openingMl + $receivedMl - $saleMl;
                    $runningStock[$product->id] = $closingMl;
                }
                $categoryHasReceipt = $categoryHasReceipt || $receivedMl > 0;
                $sheet->fromArray([
                    $product->name,
                    (float) $product->bottle_size_ml,
                    StockFormatter::bottlesAndMl($openingMl, (float) $product->bottle_size_ml, true),
                    StockFormatter::bottlesAndMl($receivedMl, (float) $product->bottle_size_ml),
                    $sales['peg_30'], $sales['peg_60'], $sales['full_bottle'], $sales['cocktail'], $sales['measured'],
                    StockFormatter::bottlesAndMl($saleMl, (float) $product->bottle_size_ml),
                    $saleMl,
                    StockFormatter::bottlesAndMl($closingMl, (float) $product->bottle_size_ml, true),
                    $row['latest_bottle_price'] ?? 'Unknown',
                    $row['stock_value'] ?? 'Unknown',
                ], null, 'A'.$rowNumber++, true);
            }

            $dataEnd = $rowNumber - 1;
            $suffix = $isSummary ? 'Summary' : $day->format('Ymd');
            $this->addTable($sheet, "A{$headerRow}:{$lastColumn}{$dataEnd}", $suffix.'_'.$category);
            $this->styleHeader($sheet, "A{$headerRow}:{$lastColumn}{$headerRow}");
            $sheet->getStyle("B{$dataStart}:B{$dataEnd}")->getNumberFormat()->setFormatCode('#,##0.##" ml"');
            $sheet->getStyle("E{$dataStart}:I{$dataEnd}")->getNumberFormat()->setFormatCode('#,##0.###');
            $sheet->getStyle("K{$dataStart}:K{$dataEnd}")->getNumberFormat()->setFormatCode('#,##0.##" ml"');
            $sheet->getStyle("M{$dataStart}:N{$dataEnd}")->getNumberFormat()->setFormatCode('"₹"#,##0.00');
            if ($categoryHasReceipt) {
                $sheet->getStyle("D{$headerRow}:D{$dataEnd}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('ECFDF3');
            }
            $sheetHasReceipt = $sheetHasReceipt || $categoryHasReceipt;
            $rowNumber += 2;
        }

        $lastRow = max(6, $rowNumber - 2);
        $this->finish($sheet, "A1:{$lastColumn}{$lastRow}", [
            'A' => 38, 'B' => 14, 'C' => 32, 'D' => 30,
            'E' => 13, 'F' => 13, 'G' => 13, 'H' => 13, 'I' => 17,
            'J' => 31, 'K' => 20, 'L' => 32, 'M' => 18, 'N' => 20,
        ]);
        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getAlignment()->setWrapText(true);
        $sheet->freezePane('C7');
        if ($sheetHasReceipt) {
            $sheet->getTabColor()->setRGB('22C55E');
        }
    }

    /** @param Collection<int, array<string, mixed>> $rows */
    private function categoryGroups(Collection $rows): Collection
    {
        $order = array_flip(self::CATEGORY_ORDER);

        return $rows->groupBy(fn ($row) => $row['product']->spirit_type ?: 'Other')
            ->sortBy(fn ($items, $category) => $order[$category] ?? 999);
    }

    private function intervalSheetTitle(Carbon $day): string
    {
        $nextDay = $day->copy()->addDay();

        return $day->month === $nextDay->month
            ? $day->format('j').'-'.$nextDay->format('j M')
            : $day->format('j M').'-'.$nextDay->format('j M');
    }

    private function addTable(Worksheet $sheet, string $range, string $name): void
    {
        $safeName = preg_replace('/[^A-Za-z0-9_]/', '', $name) ?: 'StockTable';
        $table = new Table($range, 'Stock_'.$safeName);
        $table->getStyle()->setTheme(TableStyle::TABLE_STYLE_MEDIUM9)->setShowRowStripes(true);
        $sheet->addTable($table);
    }

    private function styleCategoryTitle(Worksheet $sheet, int $row, string $lastColumn): void
    {
        $range = "A{$row}:{$lastColumn}{$row}";
        $sheet->getStyle($range)->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('9A3412');
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF7ED');
    }

    private function styleHeader(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true);
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DBE8FF');
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
    }

    /** @param array<string, int> $widths */
    private function finish(Worksheet $sheet, string $range, array $widths): void
    {
        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
        $sheet->setShowGridlines(false);
        $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D0D5DD');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setColor(new Color('FF667085'));
        $sheet->getStyle('A3')->getFont()->setSize(9)->setColor(new Color('FF667085'));
        $sheet->getRowDimension(1)->setRowHeight(25);
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4)->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(0.35)->setRight(0.25)->setBottom(0.35)->setLeft(0.25);
    }

    private function download(Spreadsheet $book, string $filename): StreamedResponse
    {
        foreach ($book->getWorksheetIterator() as $sheet) {
            foreach ($sheet->getCellCollection()->getCoordinates() as $coordinate) {
                $cell = $sheet->getCell($coordinate);
                if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                    $cell->setValueExplicit($cell->getValue(), DataType::TYPE_STRING);
                }
            }
        }

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
