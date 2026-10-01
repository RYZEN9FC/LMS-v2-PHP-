<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;
use Smalot\PdfParser\Parser;

/**
 * Reads the Safari/macOS Quartz rendering of the Telangana excise indent.
 *
 * Keep this separate from ExcisePreviewParser: that parser is calibrated for
 * Chrome/Skia PDFs and remains the first parser attempted by the upload flow.
 */
class MacOsExcisePreviewParser
{
    public function parse(string $path, ?callable $progress = null): array
    {
        $pdf = (new Parser)->parseFile($path);
        $text = preg_replace('/\s+/u', ' ', $pdf->getText());
        preg_match('/\bIND\d[A-Z0-9]+\b/i', $text, $number);
        preg_match('/Date\s*:\s*(\d{2}-[A-Z]{3}-\d{2})/i', $text, $date);
        $bottleIndent = stripos($text, 'Bottle Indent') !== false;
        $items = [];
        $allText = '';
        $pageRows = [];

        foreach ($pdf->getPages() as $page) {
            $entries = array_map(fn ($entry) => [
                'x' => (float) $entry[0][4],
                'y' => (float) $entry[0][5],
                'text' => (string) $entry[1],
            ], $page->getDataTm());
            $allText .= "\n".$this->join($entries, ' ');
            $anchors = [];
            foreach ($entries as $entry) {
                if ($entry['x'] >= 35 && $entry['x'] < 55 && preg_match('/^\d+$/', trim($entry['text']))) {
                    $anchors[(string) round($entry['y'], 1)][] = $entry;
                }
            }
            krsort($anchors, SORT_NUMERIC);
            $anchors = array_map(fn ($group) => [
                'y' => $group[0]['y'],
                'number' => (int) $this->join($group),
            ], array_values($anchors));
            $pageRows[] = compact('entries', 'anchors');
        }

        $totalRows = array_sum(array_map(fn ($page) => count($page['anchors']), $pageRows));
        $processedRows = 0;
        if ($progress) {
            $progress(0, $totalRows);
        }

        foreach ($pageRows as $page) {
            $entries = $page['entries'];
            $anchors = $page['anchors'];
            foreach ($anchors as $index => $anchor) {
                $upper = isset($anchors[$index - 1]) ? ($anchors[$index - 1]['y'] + $anchor['y']) / 2 : $anchor['y'] + 35;
                $lower = isset($anchors[$index + 1]) ? ($anchors[$index + 1]['y'] + $anchor['y']) / 2 : $anchor['y'] - 45;
                $footer = array_filter($entries, fn ($entry) => $entry['y'] < $anchor['y'] && preg_match('/C\|B|Invoice|IML\s*:/i', $entry['text']));
                if ($footer) {
                    $lower = max($lower, max(array_column($footer, 'y')) + 1);
                }
                $row = array_values(array_filter($entries, fn ($entry) => $entry['y'] < $upper && $entry['y'] > $lower));
                $sizeCodes = array_values(array_filter($row, fn ($entry) => $entry['x'] >= 295 && $entry['x'] < 340
                    && abs($entry['y'] - $anchor['y']) < 1 && preg_match('/^[A-Z]{2}$/', trim($entry['text']))
                    && str_contains($this->column($row, $entry['x'] + 20, $entry['x'] + 100), '/')));
                if (count($sizeCodes) !== 1) {
                    $this->fail('Cannot identify the size code on macOS indent row '.$anchor['number'].'.');
                }
                $size = $sizeCodes[0];
                $packTypes = array_values(array_filter($row, fn ($entry) => $entry['x'] > $size['x'] + 45
                    && $entry['x'] < $size['x'] + 130 && preg_match('/^[GC]$/', trim($entry['text']))));
                if (count($packTypes) !== 1) {
                    $this->fail('Cannot identify the packaging on macOS indent row '.$anchor['number'].'.');
                }
                $packType = $packTypes[0];
                $pack = $this->column($row, $size['x'] + 20, $packType['x']);
                if (! preg_match('/(\d+)\s*\/\s*(\d+(?:\.\d+)?)\s*ml/i', $pack, $match)) {
                    $this->fail('Cannot read bottles per case / bottle volume on macOS indent row '.$anchor['number'].'.');
                }
                $types = array_values(array_filter($row, fn ($entry) => $entry['x'] >= 230 && $entry['x'] < $size['x']
                    && preg_match('/^(BEER|IML|Duty(?: Paid)?|Paid)$/i', trim($entry['text']))));
                if (! $types) {
                    $this->fail('Cannot identify the description boundary on macOS indent row '.$anchor['number'].'.');
                }
                $typeX = min(array_column($types, 'x'));
                $code = preg_replace('/[^0-9]/', '', $this->column($row, 65, 105));
                $name = $this->column($row, 105, $typeX);
                $numbers = $this->rowNumbers($row, $packType['x'], $anchor['y']);
                if (count($numbers) !== ($bottleIndent ? 2 : 3)) {
                    $this->fail('Quantity columns are incomplete on macOS indent row '.$anchor['number'].'.');
                }
                $cases = $bottleIndent ? 0 : $numbers[0];
                $loose = $numbers[$bottleIndent ? 0 : 1];
                $amount = $numbers[$bottleIndent ? 1 : 2];
                $bottles = $cases * (int) $match[1] + $loose;
                $sizeMl = (float) $match[2];
                if (! $code || ! $name || $sizeMl <= 0 || $bottles <= 0 || floor((float) $bottles) !== (float) $bottles || $amount < 0) {
                    $this->fail('Invalid stock or price on macOS indent row '.$anchor['number'].'.');
                }
                $items[] = [
                    'number' => $anchor['number'],
                    'code' => $code.trim($size['text']).trim($packType['text']),
                    'name' => $name,
                    'size_ml' => $sizeMl,
                    'cases' => $cases,
                    'loose_bottles' => $loose,
                    'bottles_per_case' => (int) $match[1],
                    'bottles' => (int) $bottles,
                    'volume_ml' => $bottles * $sizeMl,
                    'amount' => $amount,
                    'bottle_price' => $amount / $bottles,
                ];
                if ($progress) {
                    $progress(++$processedRows, $totalRows);
                } else {
                    $processedRows++;
                }
            }
        }

        preg_match('/Invoice\s*V\s*alue\s*:\s*([\d,.]+)/i', $allText, $invoice);
        preg_match('/N\s*e\s*t\s+Indent\s*V\s*alue\s*:\s*([\d,.]+)/i', $allText, $net);
        if (! $items || ! $number || ! $date || ! $invoice || ! $net) {
            $this->fail('The macOS PDF is incomplete or unsupported: indent identity, date, items and invoice totals are required.');
        }
        if (array_column($items, 'number') !== range(1, count($items))) {
            $this->fail('macOS indent row numbers are missing or duplicated. No stock has been changed.');
        }
        $invoiceValue = (float) str_replace(',', '', $invoice[1]);
        if (abs(array_sum(array_column($items, 'amount')) - $invoiceValue) > 0.011) {
            $this->fail('Extracted macOS item amounts do not reconcile with the invoice total. No stock has been changed.');
        }
        if ($progress) {
            $progress($totalRows, $totalRows);
        }

        return [
            'pages' => count($pdf->getPages()),
            'indent_number' => $number[0],
            'date' => $date[1],
            'invoice_value' => $invoiceValue,
            'net_value' => (float) str_replace(',', '', $net[1]),
            'lines' => $items,
            'bottles' => array_sum(array_column($items, 'bottles')),
        ];
    }

    private function column(array $entries, float $from, float $to): string
    {
        return $this->join(array_filter($entries, fn ($entry) => $entry['x'] >= $from && $entry['x'] < $to));
    }

    private function join(array $entries, string $separator = ''): string
    {
        $lines = [];
        foreach ($entries as $entry) {
            $lines[(string) round($entry['y'], 1)][] = $entry;
        }
        krsort($lines, SORT_NUMERIC);
        $text = [];
        foreach ($lines as $line) {
            usort($line, fn ($left, $right) => $left['x'] <=> $right['x']);
            $text[] = implode($separator, array_column($line, 'text'));
        }

        return trim(preg_replace('/\s+/u', ' ', implode(' ', $text)));
    }

    private function rowNumbers(array $row, float $packX, float $centerY): array
    {
        $cells = array_values(array_filter($row, fn ($entry) => $entry['x'] > $packX + 15
            && abs($entry['y'] - $centerY) < 1 && preg_match('/^[\d,. ]+$/', $entry['text'])));
        usort($cells, fn ($left, $right) => $left['x'] <=> $right['x']);
        $groups = [];
        $right = null;
        foreach ($cells as $cell) {
            if ($right === null || $cell['x'] - $right > 7) {
                $groups[] = '';
            }
            $groups[array_key_last($groups)] .= $cell['text'];
            $right = $cell['x'] + strlen($cell['text']) * 5.25;
        }

        return array_map(function ($value) {
            $value = str_replace([',', ' '], '', $value);
            if (! is_numeric($value)) {
                $this->fail('A macOS indent quantity or amount is not numeric.');
            }

            return (float) $value;
        }, $groups);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['indent' => $message]);
    }
}
