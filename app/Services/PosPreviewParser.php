<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class PosPreviewParser
{
    public function parse(string $path): array
    {
        $items = [];
        $sheets = [];
        $from = $to = null;
        $detailed = false;
        $sourceRows = 0;
        $excluded = 0;
        $excludedNonLiquor = 0;
        foreach ((new SpreadsheetRows)->sheets($path) as $sheet => $rows) {
            $sheetKey = strtolower(preg_replace('/[^a-z0-9]/i', '', $sheet));
            $kind = match ($sheetKey) {
                'itemwisesalesreport' => 'sold',
                'itemwisecomplimentaryreport' => 'comp',
                'itemwisenonchargeablereport' => 'nc',
                'itemwisecustomerreport' => 'customer',
                default => null,
            };
            if (! $kind) {
                continue;
            }
            $sheets[] = $sheet;
            $headers = [];
            $sheetFrom = $sheetTo = null;
            $sum = $sumAmount = 0;
            $footer = null;
            foreach ($rows as $rowNumber => $row) {
                $normal = array_map(fn ($v) => strtoupper(trim((string) $v)), $row);
                if (! $headers) {
                    if (($normal[0] ?? '') === 'START DATE') {
                        $sheetFrom = $this->date($row[1] ?? '');
                    }
                    if (($normal[0] ?? '') === 'END DATE') {
                        $sheetTo = $this->date($row[1] ?? '');
                    }
                    if (in_array('PRODUCT NAME', $normal, true)) {
                        $headers = array_flip($normal);
                        $required = $kind === 'customer'
                            ? ['ORDER DATE', 'ITEM NAME', 'QTY', 'AMOUNT', 'DISCOUNT', 'AMOUNT BEFORE TAX', 'ITEM STATUS', 'ORDER STATUS', 'ITEM TYPE', 'ORDER SUBTYPE']
                            : ['PRODUCT NAME', 'AMOUNT', match ($kind) {
                                'sold' => 'TOTAL QTY SOLD', 'comp' => 'TOTAL COMPLIMENTARY QTY', 'nc' => 'TOTAL NON CHARGEABLE QTY'
                            }];
                        foreach ($required as $header) {
                            if (! isset($headers[$header])) {
                                $this->fail("Missing {$header} in {$sheet}.");
                            }
                        }
                    }

                    continue;
                }
                $get = fn ($key, $default = '') => $row[$headers[$key] ?? -1] ?? $default;
                $quantityKey = $kind === 'customer' ? 'QTY' : match ($kind) {
                    'sold' => 'TOTAL QTY SOLD', 'comp' => 'TOTAL COMPLIMENTARY QTY', 'nc' => 'TOTAL NON CHARGEABLE QTY'
                };
                if (in_array('GRAND TOTAL', $normal, true)) {
                    if ($footer !== null) {
                        $this->fail("Duplicate Grand Total in {$sheet}.");
                    }
                    $footer = ['quantity' => $this->number($get($quantityKey), $sheet, $rowNumber), 'amount' => $this->number($get('AMOUNT'), $sheet, $rowNumber)];

                    continue;
                }
                $name = trim((string) $get($kind === 'customer' ? 'ITEM NAME' : 'PRODUCT NAME'));
                if ($name === '' || $name === '-') {
                    continue;
                }
                $qty = $this->number($get($quantityKey), $sheet, $rowNumber);
                if ($qty > 999999999 || abs($qty - round($qty, 3)) > 0.0000001) {
                    $this->fail('Quantity exceeds the supported range or three-decimal precision in row '.($rowNumber + 1).'.');
                }
                $amount = $this->number($get('AMOUNT'), $sheet, $rowNumber);
                $sum += $qty;
                $sumAmount += $amount;
                $sourceRows++;
                $itemType = '';
                if ($kind === 'customer') {
                    $detailed = true;
                    $status = strtoupper(trim((string) $get('ITEM STATUS')));
                    $orderStatus = strtoupper(trim((string) $get('ORDER STATUS')));
                    if (in_array($status, ['CANCELLED', 'CANCELED', 'VOID', 'VOIDED'], true) || in_array($orderStatus, ['CANCELLED', 'CANCELED', 'VOID', 'VOIDED'], true)) {
                        $excluded++;

                        continue;
                    }
                    if ($status !== 'COMPLETED' || $orderStatus !== 'CLOSED') {
                        $this->fail("Unrecognised or unfinished order status in {$sheet}, row ".($rowNumber + 1).'.');
                    }
                    $date = $this->date($get('ORDER DATE'), true);
                    $itemType = strtolower(trim((string) $get('ITEM TYPE')));
                    $subtype = strtolower(trim((string) $get('ORDER SUBTYPE')));
                    $channel = str_starts_with($itemType, 'nc-') || in_array($subtype, ['nc', 'non chargeable', 'non-chargeable'], true) ? 'nc' : (in_array($subtype, ['complimentary', 'comp'], true) ? 'comp' : 'sold');
                    $net = $this->number($get('AMOUNT BEFORE TAX'), $sheet, $rowNumber);
                    $discount = $this->number($get('DISCOUNT', 0), $sheet, $rowNumber);
                    if (abs($amount - $discount - $net) > 0.021) {
                        $this->fail('Amount less discount does not match pre-tax amount in row '.($rowNumber + 1).'.');
                    }
                    $category = (string) $get('CATEGORY NAME');
                } else {
                    $date = $sheetTo;
                    $channel = $kind;
                    $net = $amount;
                    $discount = 0;
                    $category = (string) $get('CATEGORY');
                }
                if ($kind === 'customer' ? ! $this->isCustomerLiquorType($itemType) : ! $this->isLiquorCategory($category)) {
                    $excludedNonLiquor++;

                    continue;
                }
                $identity = hash('sha256', mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $name))));
                $items[$identity] ??= ['name' => $name, 'category' => $category, 'sold' => 0, 'comp' => 0, 'nc' => 0, 'sold_amount' => 0, 'comp_amount' => 0, 'nc_amount' => 0, 'discount' => 0, 'daily' => []];
                $items[$identity][$channel] += $qty;
                $items[$identity][$channel.'_amount'] += $channel === 'sold' ? $net : $amount;
                $items[$identity]['discount'] += $discount;
                $items[$identity]['daily'][$date] ??= ['sold' => 0, 'comp' => 0, 'nc' => 0, 'sold_amount' => 0, 'comp_amount' => 0, 'nc_amount' => 0, 'discount' => 0];
                $items[$identity]['daily'][$date][$channel] += $qty;
                $items[$identity]['daily'][$date][$channel.'_amount'] += $channel === 'sold' ? $net : $amount;
                $items[$identity]['daily'][$date]['discount'] += $discount;
            }
            if (! $headers) {
                $this->fail("No supported headers found in {$sheet}.");
            }
            if ($footer && (abs($footer['quantity'] - $sum) > 0.0001 || abs($footer['amount'] - $sumAmount) > 0.051)) {
                $this->fail("The item quantities or amounts in {$sheet} do not reconcile with its Grand Total.");
            }
            if (! $sheetFrom || ! $sheetTo || $sheetFrom > $sheetTo) {
                $this->fail("The reporting period is missing or invalid in {$sheet}.");
            }
            if (($from && $from !== $sheetFrom) || ($to && $to !== $sheetTo)) {
                $this->fail('The sales, complimentary and non-chargeable sheets cover different periods.');
            }
            $from = $sheetFrom;
            $to = $sheetTo;
        }
        if ($detailed && count($sheets) !== 1) {
            $this->fail('Customer transactions and aggregate sales sheets cannot be combined in one import. Upload one report format at a time.');
        }
        if (! $items || (! $detailed && ! collect($sheets)->contains(fn ($s) => stripos($s, 'Sales') !== false))) {
            $this->fail('Use an Item Wise Sales Report or an Item Wise Customer Report.');
        }
        foreach ($items as &$item) {
            foreach (array_keys($item['daily']) as $day) {
                if (! $day || $day < $from || $day > $to) {
                    $this->fail('A transaction date lies outside the workbook reporting period.');
                }
            }
            $item['unit_price'] = $item['sold'] > 0 ? $item['sold_amount'] / $item['sold'] : null;
        }
        unset($item);

        return ['from' => $from, 'to' => $to, 'sheets' => $sheets, 'granularity' => $detailed ? 'daily' : 'period',
            'items' => count($items), 'lines' => array_values($items), 'source_rows' => $sourceRows, 'excluded_rows' => $excluded,
            'excluded_non_liquor' => $excludedNonLiquor,
            'quantity' => array_sum(array_column($items, 'sold')), 'comp' => array_sum(array_column($items, 'comp')),
            'nc' => array_sum(array_column($items, 'nc')), 'revenue' => array_sum(array_column($items, 'sold_amount'))];
    }

    private function number(mixed $value, string $sheet, int $row): float
    {
        $value = str_replace(',', '', trim((string) $value));
        if (! is_numeric($value) || ! is_finite((float) $value) || (float) $value < 0 || (float) $value > 1000000000) {
            $this->fail("Invalid or negative quantity/amount in {$sheet}, row ".($row + 1).'. Returns require an explicit supported format.');
        }

        return (float) $value;
    }

    private function date(mixed $value, bool $customer = false): string
    {
        try {
            if (is_numeric($value)) {
                return Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
            }
            $format = $customer ? '!d m Y' : '!d M Y h:i A';
            $date = Carbon::createFromFormat($format, trim((string) $value));
            if (! $date || Carbon::getLastErrors()) {
                $this->fail('Invalid date in POS report.');
            }

            return $date->toDateString();
        } catch (\Throwable $error) {
            $this->fail('Invalid or missing date in POS report.');
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['report' => $message]);
    }

    private function isLiquorCategory(string $category): bool
    {
        return preg_match('/beer|vodka|whisk|whiskey|gin|rum|tequila|wine|liqueur|aperitif|alcopop|brandy|cognac|champagne|sparkling|cocktail|shot|single\s+malt|spirit/i', $category) === 1;
    }

    private function isCustomerLiquorType(string $itemType): bool
    {
        return in_array($itemType, ['liquor-item', 'nc-liquor-item', 'so-liquor'], true);
    }
}
