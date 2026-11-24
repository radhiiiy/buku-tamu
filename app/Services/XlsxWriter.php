<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

/**
 * Penulis .xlsx minimal tanpa dependensi tambahan (hanya ext-zip).
 * Teks ditulis sebagai inline string, sehingga isi sel yang diawali "=" tidak pernah dievaluasi sebagai rumus.
 */
class XlsxWriter
{
    public const STYLE_DEFAULT = 0;

    public const STYLE_HEADER = 1;

    public const STYLE_TITLE = 2;

    /** @var array<int, array{name:string, rows:array, styles:array, widths:array}> */
    private array $sheets = [];

    public function addSheet(string $name, array $rows, array $rowStyles = [], array $widths = []): static
    {
        $this->sheets[] = ['name' => $this->cleanName($name, count($this->sheets) + 1), 'rows' => $rows, 'styles' => $rowStyles, 'widths' => $widths];

        return $this;
    }

    public function save(string $path): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Ekstensi PHP zip diperlukan untuk membuat berkas .xlsx.');
        }
        if ($this->sheets === []) {
            throw new RuntimeException('Workbook harus punya minimal satu sheet.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Tidak dapat menulis berkas: '.$path);
        }

        $n = count($this->sheets);
        $overrides = '';
        $wbSheets = '';
        $wbRels = '';
        foreach ($this->sheets as $i => $s) {
            $id = $i + 1;
            $overrides .= '<Override PartName="/xl/worksheets/sheet'.$id.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $wbSheets .= '<sheet name="'.$this->esc($s['name']).'" sheetId="'.$id.'" r:id="rId'.$id.'"/>';
            $wbRels .= '<Relationship Id="rId'.$id.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$id.'.xml"/>';
            $zip->addFromString('xl/worksheets/sheet'.$id.'.xml', $this->sheetXml($s));
        }
        $wbRels .= '<Relationship Id="rId'.($n + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        $head = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $zip->addFromString('[Content_Types].xml', $head.'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'.$overrides.'</Types>');
        $zip->addFromString('_rels/.rels', $head.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', $head.'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'.$wbSheets.'</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', $head.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$wbRels.'</Relationships>');
        $zip->addFromString('xl/styles.xml', $head.'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="3"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font><font><b/><sz val="14"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1E3A6E"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/><xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
        $zip->close();
    }

    private function sheetXml(array $s): string
    {
        $cols = '';
        foreach ($s['widths'] as $i => $w) {
            $c = $i + 1;
            $cols .= '<col min="'.$c.'" max="'.$c.'" width="'.$w.'" customWidth="1"/>';
        }
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $xml .= $cols !== '' ? '<cols>'.$cols.'</cols>' : '';
        $xml .= '<sheetData>';
        foreach ($s['rows'] as $r => $row) {
            $style = (int) ($s['styles'][$r] ?? 0);
            $xml .= '<row r="'.($r + 1).'">';
            foreach (array_values($row) as $c => $val) {
                $ref = $this->col($c).($r + 1);
                $sAttr = $style ? ' s="'.$style.'"' : '';
                if ($val === null || $val === '') {
                    $xml .= $style ? '<c r="'.$ref.'"'.$sAttr.'/>' : '';
                } elseif (is_int($val) || is_float($val)) {
                    $xml .= '<c r="'.$ref.'"'.$sAttr.'><v>'.$val.'</v></c>';
                } else {
                    $xml .= '<c r="'.$ref.'"'.$sAttr.' t="inlineStr"><is><t xml:space="preserve">'.$this->esc((string) $val).'</t></is></c>';
                }
            }
            $xml .= '</row>';
        }

        return $xml.'</sheetData></worksheet>';
    }

    private function col(int $i): string
    {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
            $s = chr(65 + ($i - 1) % 26).$s;
        }

        return $s;
    }

    private function esc(string $v): string
    {
        $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $v) ?? '';

        return htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function cleanName(string $name, int $fallback): string
    {
        $name = trim(str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $name));
        $name = mb_substr($name, 0, 31);

        return $name !== '' ? $name : 'Sheet'.$fallback;
    }
}
