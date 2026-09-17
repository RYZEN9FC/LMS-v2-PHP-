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
use PhpOffice\PhpSpreadsheet\Worksheet\Table;
use PhpOffice\PhpSpreadsheet\Worksheet\Table\TableStyle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExcelExporter
{
    /** @param Collection<int, array<string, mixed>> $rows */
    public function current(Outlet $outlet, Carbon $asAt, Collection $rows, ?string $periodNotice = null): StreamedResponse
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Current stock');
        if ($periodNotice) {
            $sheet->setCellValue('A3', $periodNotice);
        }
        $sheet->mergeCells('A1:F1')->setCellValue('A1', $outlet->name.' · Current expected stock');
        $sheet->mergeCells('A2:F2')->setCellValue('A2', 'As at end of '.$asAt->format('j M Y'));
        $sheet->mergeCells('A4:A5')->setCellValue('A4', 'Item');
        $sheet->mergeCells('B4:B5')->setCellValue('B4', 'UON');
        $sheet->mergeCells('C4:D4')->setCellValue('C4', 'Total');
        $sheet->mergeCells('E4:E5')->setCellValue('E4', 'Latest bottle price');
        $sheet->mergeCells('F4:F5')->setCellValue('F4', 'Cost of available stock');
        $sheet->fromArray(['Total ml', 'Bottles + ml'], null, 'C5');

        $rowNumber = 6;
        foreach ($rows as $row) {
            $product = $row['product'];
            $sheet->fromArray([
                $product->name,
                (float) $product->bottle_size_ml,
                (float) $row['expected_ml'],
                StockFormatter::bottlesAndMl((float) $row['expected_ml'], (float) $product->bottle_size_ml, true),
                $row['latest_bottle_price'] ?? 'Unknown',
                $row['stock_value'] ?? 'Unknown',
            ], null, 'A'.$rowNumber++, true);
        }

        $sheet->mergeCells('A'.$rowNumber.':E'.$rowNumber)
            ->setCellValue('A'.$rowNumber, $rows->contains(fn ($r) => $r['stock_value'] === null) ? 'Known stock cost subtotal (incomplete)' : 'Total cost of available expected stock')
            ->setCellValue('F'.$rowNumber, (float) $rows->sum('stock_value'));
        $sheet->getStyle('B6:C'.$rowNumber)->getNumberFormat()->setFormatCode('#,##0.##" ml"');
        $sheet->getStyle('E6:F'.$rowNumber)->getNumberFormat()->setFormatCode('"₹"#,##0.00');
        $this->finish($sheet, 'A1:F'.$rowNumber, 'A4:F5', ['A' => 26, 'B' => 12, 'C' => 15, 'D' => 39, 'E' => 20, 'F' => 22]);
        $sheet->getStyle('A'.$rowNumber.':F'.$rowNumber)->getFont()->setBold(true);
        $sheet->getStyle('A'.$rowNumber.':F'.$rowNumber)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEF4FF');

        return $this->download($book, 'expected-stock-'.$asAt->toDateString().'.xlsx');
    }

    /** @param array{days: Collection<int, Carbon>, rows: Collection<int, array<string, mixed>>} $report */
    public function interval(Outlet $outlet, Carbon $from, Carbon $to, array $report): StreamedResponse
    {
        $book = new Spreadsheet;
        $days = $report['days'];
        $runningStock = $report['rows']->mapWithKeys(fn ($row) => [$row['product']->id => (float) $row['opening_ml']])->all();
        $categoryOrder = array_flip(['Beer', 'Alcopop', 'Wine', 'Vodka', 'Gin', 'Rum', 'Tequila', 'Whisky', 'Brandy', 'Liqueur', 'Other']);
        $categoryGroups = $report['rows']
            ->groupBy(fn ($row) => $row['product']->spirit_type ?: 'Other')
            ->sortBy(fn ($rows, $category) => $categoryOrder[$category] ?? 999);
        $headers = [
            'Brand', 'UON', 'Opening stock', 'Initial stock introduced', 'Indent refill',
            '30 ml pegs', '60 ml pegs', 'Full bottles', 'Cocktails', 'Other ml servings',
            'Total stock use Bottles + ml', 'Total stock use Total ml', 'Closing stock Bottles + ml', 'Closing stock Total ml',
        ];

        foreach ($days as $dayIndex => $day) {
            $sheet = $dayIndex === 0 ? $book->getActiveSheet() : $book->createSheet();
            $nextDay = $day->copy()->addDay();
            $sheet->setTitle($this->intervalSheetTitle($day));
            $sheet->mergeCells('A1:N1')->setCellValue('A1', $outlet->name.' · Daily stock movement');
            $sheet->mergeCells('A2:N2')->setCellValue('A2', $day->format('j M Y').' – '.$nextDay->format('j M Y').' · 24-hour interval');
            if ($report['periodNotice'] ?? null) {
                $sheet->mergeCells('A3:N3')->setCellValue('A3', $report['periodNotice']);
            }

            $rowNumber = 4;
            $hasIndent = false;
            foreach ($categoryGroups as $category => $categoryRows) {
                $sheet->mergeCells('A'.$rowNumber.':N'.$rowNumber)->setCellValue('A'.$rowNumber, strtoupper($category).' · '.$categoryRows->count().' brands');
                $sheet->getStyle('A'.$rowNumber.':N'.$rowNumber)->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('9A3412');
                $sheet->getStyle('A'.$rowNumber.':N'.$rowNumber)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF7ED');
                $rowNumber++;
                $headerRow = $rowNumber;
                $sheet->fromArray($headers, null, 'A'.$rowNumber++);
                $dataStart = $rowNumber;
                $categoryHasIndent = false;

                foreach ($categoryRows as $row) {
                    $product = $row['product'];
                    $daily = $row['days'][$day->toDateString()];
                    $openingMl = $runningStock[$product->id];
                    $initialMl = (float) $daily['opening_ml'];
                    $indentMl = (float) $daily['indent_ml'];
                    $saleMl = (float) $daily['sale_ml'];
                    $closingMl = $openingMl + $initialMl + $indentMl - $saleMl;
                    $runningStock[$product->id] = $closingMl;
                    $categoryHasIndent = $categoryHasIndent || $indentMl > 0;

                    $sheet->fromArray([
                        $product->name,
                        (float) $product->bottle_size_ml,
                        StockFormatter::bottlesAndMl($openingMl, (float) $product->bottle_size_ml, true),
                        StockFormatter::bottlesAndMl($initialMl, (float) $product->bottle_size_ml),
                        StockFormatter::bottlesAndMl($indentMl, (float) $product->bottle_size_ml),
                        $daily['sales']['peg_30'],
                        $daily['sales']['peg_60'],
                        $daily['sales']['full_bottle'],
                        $daily['sales']['cocktail'],
                        $daily['sales']['measured'],
                        StockFormatter::bottlesAndMl($saleMl, (float) $product->bottle_size_ml),
                        $saleMl,
                        StockFormatter::bottlesAndMl($closingMl, (float) $product->bottle_size_ml, true),
                        $closingMl,
                    ], null, 'A'.$rowNumber++, true);
                }

                $dataEnd = $rowNumber - 1;
                $tableName = 'Stock_'.$day->format('Ymd').'_'.preg_replace('/[^A-Za-z0-9_]/', '', $category);
                $table = new Table('A'.$headerRow.':N'.$dataEnd, $tableName);
                $table->getStyle()->setTheme(TableStyle::TABLE_STYLE_MEDIUM9)->setShowRowStripes(true);
                $sheet->addTable($table);
                $sheet->getStyle('B'.$dataStart.':B'.$dataEnd)->getNumberFormat()->setFormatCode('#,##0.##" ml"');
                $sheet->getStyle('F'.$dataStart.':J'.$dataEnd)->getNumberFormat()->setFormatCode('#,##0.###');
                $sheet->getStyle('L'.$dataStart.':L'.$dataEnd)->getNumberFormat()->setFormatCode('#,##0.##" ml"');
                $sheet->getStyle('N'.$dataStart.':N'.$dataEnd)->getNumberFormat()->setFormatCode('#,##0.##" ml"');
                if ($categoryHasIndent) {
                    $sheet->getStyle('E'.$headerRow.':E'.$dataEnd)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('ECFDF3');
                }
                $hasIndent = $hasIndent || $categoryHasIndent;
                $rowNumber += 2;
            }

            $lastRow = max(6, $rowNumber - 2);
            $this->finish($sheet, 'A1:N'.$lastRow, 'A5:N5', [
                'A' => 38, 'B' => 12, 'C' => 34, 'D' => 28, 'E' => 28,
                'F' => 14, 'G' => 14, 'H' => 14, 'I' => 14, 'J' => 18,
                'K' => 32, 'L' => 20, 'M' => 34, 'N' => 20,
            ]);
            $sheet->getStyle('A1:N'.$lastRow)->getAlignment()->setWrapText(true);
            $sheet->freezePane('C6');
            if ($hasIndent) {
                $sheet->getTabColor()->setRGB('22C55E');
            }
        }

        $book->setActiveSheetIndex(0);

        return $this->download($book, 'stock-movement-'.$from->toDateString().'-to-'.$to->toDateString().'.xlsx');
    }

    private function intervalSheetTitle(Carbon $day): string
    {
        $nextDay = $day->copy()->addDay();

        return $day->month === $nextDay->month
            ? $day->format('j').'-'.$nextDay->format('j M')
            : $day->format('j M').'-'.$nextDay->format('j M');
    }

    private function finish(Worksheet $sheet, string $range, string $headerRange, array $widths = []): void
    {
        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
        $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D0D5DD');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setColor(new Color('FF667085'));
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DBE8FF');
        $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
        $sheet->getRowDimension(1)->setRowHeight(25);
        $sheet->freezePane('A6');
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

    private function column(int $number): string
    {
        $result = '';
        while ($number > 0) {
            $number--;
            $result = chr(65 + ($number % 26)).$result;
            $number = intdiv($number, 26);
        }

        return $result;
    }
}
