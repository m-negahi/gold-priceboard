<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Infrastructure\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;
use ZipArchive;

/**
 * XLSX e sade ba string cell; hich decimal-i az float rad nemishe.
 * Import faghat worksheet e aval, bedun e formula/macro/external entity ast.
 */
final readonly class ManualPriceWorkbook
{
    private const MAX_XML_BYTES = 8_000_000;

    private const MAX_ROWS = 500;

    /** @param list<array{symbol: string, label: string, price: string, unit: string}> $rows */
    public function write(string $path, array $rows): void
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new InvalidArgumentException('ساخت فایل اکسل ممکن نشد.');
        }

        try {
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
            $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Manual prices" sheetId="1" r:id="rId1"/></sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
            $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
            $xml = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0" rightToLeft="1"/></sheetViews><cols><col min="1" max="2" width="28" customWidth="1"/><col min="3" max="3" width="24" style="1" customWidth="1"/><col min="4" max="4" width="12" customWidth="1"/></cols><sheetData>';
            $values = [['symbol', 'label', 'price', 'unit']];

            foreach ($rows as $row) {
                $values[] = [$row['symbol'], $row['label'], $row['price'], $row['unit']];
            }

            foreach ($values as $i => $cells) {
                $n = $i + 1;
                $xml .= '<row r="'.$n.'">';

                foreach ($cells as $column => $value) {
                    $xml .= '<c r="'.chr(65 + $column).$n.'" t="inlineStr"'.($column === 2 ? ' s="1"' : '').'><is><t xml:space="preserve">'.htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></is></c>';
                }

                $xml .= '</row>';
            }

            $zip->addFromString('xl/worksheets/sheet1.xml', $xml.'</sheetData></worksheet>');
        } finally {
            $zip->close();
        }
    }

    /** @return list<array{row: int, symbol: string, label: string, price: string, unit: string, error: ?string}> */
    public function read(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new InvalidArgumentException('فایل باید اکسل معتبر با پسوند xlsx باشد.');
        }

        try {
            $bytes = 0;

            if ($zip->numFiles > 1000) {
                throw new InvalidArgumentException('فایل اکسل بیش از حد بزرگ است.');
            }

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $bytes += (int) ($stat['size'] ?? 0);

                if ($bytes > self::MAX_XML_BYTES || str_contains(strtolower((string) ($stat['name'] ?? '')), 'vbaproject')) {
                    throw new InvalidArgumentException('فایل اکسل بزرگ یا دارای ماکرو پذیرفته نمی‌شود.');
                }
            }

            $workbook = $this->xpath($this->entry($zip, 'xl/workbook.xml'));
            $sheet = $workbook->query('//*[local-name()="sheet"]')->item(0);
            $id = $sheet instanceof DOMElement ? $sheet->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id') : '';
            $relationships = $this->xpath($this->entry($zip, 'xl/_rels/workbook.xml.rels'));
            $target = '';

            foreach ($relationships->query('//*[local-name()="Relationship"]') as $relationship) {
                if ($relationship instanceof DOMElement && $relationship->getAttribute('Id') === $id
                    && $relationship->getAttribute('TargetMode') !== 'External') {
                    $target = $relationship->getAttribute('Target');
                }
            }

            $target = ltrim($target, '/');
            $target = str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;

            if (preg_match('~^xl/worksheets/[A-Za-z0-9_-]+\.xml$~D', $target) !== 1) {
                throw new InvalidArgumentException('برگه اول اکسل معتبر نیست.');
            }

            $shared = [];
            $sharedXml = $zip->getFromName('xl/sharedStrings.xml', self::MAX_XML_BYTES + 1);

            if ($sharedXml !== false) {
                $sharedPath = $this->xpath($sharedXml);

                foreach ($sharedPath->query('//*[local-name()="si"]') as $item) {
                    $shared[] = $this->text($sharedPath, $item);
                }
            }

            $sheetPath = $this->xpath($this->entry($zip, $target));
            $rows = $sheetPath->query('//*[local-name()="sheetData"]/*[local-name()="row"]');

            if ($rows->length > self::MAX_ROWS + 1) {
                throw new InvalidArgumentException('حداکثر 500 ردیف پذیرفته می‌شود.');
            }

            $parsed = [];
            $headers = null;

            foreach ($rows as $row) {
                $cells = [];
                $error = null;

                foreach ($sheetPath->query('./*[local-name()="c"]', $row) as $cell) {
                    if (! $cell instanceof DOMElement || preg_match('/^([A-Z]+)[0-9]+$/D', $cell->getAttribute('r'), $matches) !== 1) {
                        continue;
                    }

                    $column = $matches[1];

                    if (! in_array($column, ['A', 'B', 'C', 'D'], true)) {
                        continue;
                    }

                    if ($sheetPath->query('./*[local-name()="f"]', $cell)->length > 0) {
                        $error = 'فرمول پذیرفته نمی‌شود؛ مقدار را به‌صورت متن یا عدد وارد کنید.';
                    }

                    $type = $cell->getAttribute('t');
                    $value = $type === 'inlineStr' ? $this->text($sheetPath, $cell)
                        : ($sheetPath->query('./*[local-name()="v"]', $cell)->item(0)?->textContent ?? '');

                    if ($type === 's') {
                        $value = $shared[(int) $value] ?? '';
                    } elseif (! in_array($type, ['', 'n', 'inlineStr', 'str'], true)) {
                        $error = 'نوع سلول معتبر نیست.';
                    }

                    $cells[$column] = trim($value);
                }

                if ($headers === null) {
                    $headers = array_map('strtolower', [$cells['A'] ?? '', $cells['B'] ?? '', $cells['C'] ?? '', $cells['D'] ?? '']);

                    if ($headers !== ['symbol', 'label', 'price', 'unit']) {
                        throw new InvalidArgumentException('سرستون‌ها باید symbol، label، price و unit باشند. از نمونه اکسل استفاده کنید.');
                    }

                    continue;
                }

                // Radif e khali ye template dar import hich taghiri nemide.
                if (($cells['C'] ?? '') === '' && $error === null) {
                    continue;
                }

                $parsed[] = ['row' => (int) ($row->attributes?->getNamedItem('r')?->nodeValue ?? 0), 'symbol' => $cells['A'] ?? '', 'label' => $cells['B'] ?? '', 'price' => $cells['C'] ?? '', 'unit' => $cells['D'] ?? '', 'error' => $error];
            }

            if ($headers === null) {
                throw new InvalidArgumentException('فایل اکسل خالی است.');
            }

            return $parsed;
        } finally {
            $zip->close();
        }
    }

    private function entry(ZipArchive $zip, string $name): string
    {
        $xml = $zip->getFromName($name, self::MAX_XML_BYTES + 1);

        if ($xml === false) {
            throw new InvalidArgumentException('ساختار فایل اکسل معتبر نیست.');
        }

        return $xml;
    }

    private function xpath(string $xml): DOMXPath
    {
        if (strlen($xml) > self::MAX_XML_BYTES || preg_match('/<!DOCTYPE|<!ENTITY/i', $xml) === 1) {
            throw new InvalidArgumentException('محتوای فایل اکسل معتبر نیست.');
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $document = new DOMDocument;

            if (! $document->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS)) {
                throw new InvalidArgumentException('محتوای فایل اکسل قابل خواندن نیست.');
            }

            return new DOMXPath($document);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function text(DOMXPath $xpath, \DOMNode $node): string
    {
        $text = '';

        foreach ($xpath->query('.//*[local-name()="t"]', $node) as $part) {
            $text .= $part->textContent;
        }

        return $text;
    }
}
