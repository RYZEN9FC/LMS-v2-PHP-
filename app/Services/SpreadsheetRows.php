<?php

namespace App\Services;

use Generator;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use XMLReader;
use ZipArchive;

class SpreadsheetRows
{
    /** Count physical rows before parsing so upload screens can report real progress. */
    public function rowCounts(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $book = $reader->load($path);
            try {
                $counts = [];
                foreach ($book->getWorksheetIterator() as $sheet) {
                    if ($sheet->getHighestDataRow() > 100000) {
                        $this->fail('The worksheet exceeds 100,000 rows.');
                    }
                    $counts[$sheet->getTitle()] = $sheet->getHighestDataRow();
                }

                return $counts;
            } finally {
                $book->disconnectWorksheets();
            }
        }

        try {
            $size = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $size += $zip->statIndex($i)['size'];
            }
            if ($size > 150 * 1024 * 1024) {
                $this->fail('The uncompressed workbook exceeds the 150 MB safety limit.');
            }
            $workbookXml = $zip->getFromName('xl/workbook.xml');
            $relationsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
            if ($workbookXml === false || $relationsXml === false) {
                $this->fail('The workbook structure is incomplete.');
            }
            $workbook = simplexml_load_string($workbookXml, 'SimpleXMLElement', LIBXML_NONET);
            $relations = simplexml_load_string($relationsXml, 'SimpleXMLElement', LIBXML_NONET);
            $targets = [];
            foreach ($relations->children() as $rel) {
                $targets[(string) $rel['Id']] = (string) $rel['Target'];
            }

            $counts = [];
            foreach ($workbook->xpath('//*[local-name()="sheet"]') as $sheet) {
                $attributes = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
                $target = $targets[(string) $attributes['id']] ?? '';
                $entry = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.$target;
                if (! preg_match('#^xl/worksheets/[^/]+\.xml$#', $entry)) {
                    $this->fail('Unsupported worksheet relationship.');
                }
                $xml = new XMLReader;
                if (! $xml->open('zip://'.$path.'#'.$entry, null, LIBXML_NONET)) {
                    $this->fail('The worksheet could not be opened.');
                }
                $count = 0;
                try {
                    while ($xml->read()) {
                        if ($xml->nodeType === XMLReader::ELEMENT && $xml->localName === 'row') {
                            if (++$count > 100000) {
                                $this->fail('The worksheet exceeds 100,000 rows.');
                            }
                        }
                    }
                } finally {
                    $xml->close();
                }
                $counts[(string) $sheet['name']] = $count;
            }

            return $counts;
        } finally {
            $zip->close();
        }
    }

    /** Read cached underlying values, never formatted cells or executable formulas. */
    public function sheets(string $path): Generator
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $book = $reader->load($path);
            try {
                foreach ($book->getWorksheetIterator() as $sheet) {
                    if ($sheet->getHighestDataRow() > 100000 || Coordinate::columnIndexFromString($sheet->getHighestDataColumn()) > 128) {
                        $this->fail('The worksheet is too large.');
                    }
                    yield $sheet->getTitle() => $sheet->toArray(null, false, false, false);
                }
            } finally {
                $book->disconnectWorksheets();
            }

            return;
        }
        try {
            $size = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $size += $zip->statIndex($i)['size'];
            }
            if ($size > 150 * 1024 * 1024) {
                $this->fail('The uncompressed workbook exceeds the 150 MB safety limit.');
            }
            $strings = [];
            $xml = new XMLReader;
            $shared = $zip->getFromName('xl/sharedStrings.xml');
            if ($shared !== false) {
                $xml->XML($shared, null, LIBXML_NONET);
                while ($xml->read()) {
                    if ($xml->nodeType === XMLReader::ELEMENT && $xml->localName === 'si') {
                        $node = simplexml_load_string($xml->readOuterXml(), 'SimpleXMLElement', LIBXML_NONET);
                        $strings[] = implode('', array_map('strval', $node->xpath('//*[local-name()="t"]')));
                    }
                }
                $xml->close();
            }
            unset($shared);
            $workbook = simplexml_load_string($zip->getFromName('xl/workbook.xml'), 'SimpleXMLElement', LIBXML_NONET);
            $relations = simplexml_load_string($zip->getFromName('xl/_rels/workbook.xml.rels'), 'SimpleXMLElement', LIBXML_NONET);
            $targets = [];
            foreach ($relations->children() as $rel) {
                $targets[(string) $rel['Id']] = (string) $rel['Target'];
            }
            foreach ($workbook->xpath('//*[local-name()="sheet"]') as $sheet) {
                $attributes = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
                $target = $targets[(string) $attributes['id']] ?? '';
                $entry = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.$target;
                if (! preg_match('#^xl/worksheets/[^/]+\.xml$#', $entry)) {
                    $this->fail('Unsupported worksheet relationship.');
                }
                yield (string) $sheet['name'] => $this->rows($path, $entry, $strings);
            }
        } finally {
            $zip->close();
        }
    }

    private function rows(string $path, string $entry, array $strings): Generator
    {
        $xml = new XMLReader;
        if (! $xml->open('zip://'.$path.'#'.$entry, null, LIBXML_NONET)) {
            $this->fail('The worksheet could not be opened.');
        }
        try {
            $count = 0;
            while ($xml->read()) {
                if ($xml->nodeType !== XMLReader::ELEMENT || $xml->localName !== 'row') {
                    continue;
                }
                if (++$count > 100000) {
                    $this->fail('The worksheet exceeds 100,000 rows.');
                }
                $node = simplexml_load_string($xml->readOuterXml(), 'SimpleXMLElement', LIBXML_NONET);
                $row = [];
                foreach ($node->xpath('//*[local-name()="c"]') as $cell) {
                    preg_match('/^([A-Z]+)/', (string) $cell['r'], $match);
                    $column = 0;
                    foreach (str_split($match[1] ?? '') as $letter) {
                        $column = $column * 26 + ord($letter) - 64;
                    }
                    if ($column < 1 || $column > 128) {
                        $this->fail('Unsupported column count.');
                    }
                    $values = $cell->xpath('./*[local-name()="v"]');
                    $value = isset($values[0]) ? (string) $values[0] : null;
                    $type = (string) $cell['t'];
                    if ($type === 'e') {
                        $this->fail('Excel contains an error at cell '.(string) $cell['r'].'.');
                    }
                    if ($type === 's') {
                        $value = $strings[(int) $value] ?? null;
                    } elseif ($type === 'inlineStr') {
                        $value = implode('', array_map('strval', $cell->xpath('.//*[local-name()="t"]')));
                    } elseif ($type !== 'str' && $value !== null && is_numeric($value)) {
                        $value = (float) $value;
                    }
                    $row[$column - 1] = $value;
                }
                yield (int) $node['r'] - 1 => $row;
            }
        } finally {
            $xml->close();
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['report' => $message]);
    }
}
