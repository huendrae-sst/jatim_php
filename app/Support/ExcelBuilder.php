<?php

class ExcelBuilder
{
    private array $sheets = [];

    public function addSheet(string $name, array $columns, array $rows, ?string $titleBanner = null): void
    {
        $this->sheets[] = [
            'name' => $name,
            'columns' => $columns,
            'rows' => $rows,
            'titleBanner' => $titleBanner,
        ];
    }

    private function colName(int $index): string
    {
        $name = '';
        while ($index > 0) {
            $rem = ($index - 1) % 26;
            $name = chr(65 + $rem).$name;
            $index = (int) (($index - 1) / 26);
        }

        return $name;
    }

    private function escape(string $val): string
    {
        return htmlspecialchars($val, ENT_XML1, 'UTF-8');
    }

    public function build(string $outputPath): bool
    {
        $dir = dirname($outputPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $zip = new ZipArchive;
        if ($zip->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        // 1. [Content_Types].xml
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n";
        $contentTypes .= '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">';
        $contentTypes .= '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>';
        $contentTypes .= '<Default Extension="xml" ContentType="application/xml"/>';
        $contentTypes .= '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
        $contentTypes .= '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        foreach ($this->sheets as $idx => $s) {
            $num = $idx + 1;
            $contentTypes .= "<Override PartName=\"/xl/worksheets/sheet{$num}.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml\"/>";
        }
        $contentTypes .= '</Types>';
        $zip->addFromString('[Content_Types].xml', $contentTypes);

        // 2. _rels/.rels
        $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n";
        $rootRels .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        $rootRels .= '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>';
        $rootRels .= '</Relationships>';
        $zip->addFromString('_rels/.rels', $rootRels);

        // 3. xl/_rels/workbook.xml.rels
        $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n";
        $wbRels .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        $wbRels .= '<Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        foreach ($this->sheets as $idx => $s) {
            $num = $idx + 1;
            $wbRels .= "<Relationship Id=\"rIdSheet{$num}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet\" Target=\"worksheets/sheet{$num}.xml\"/>";
        }
        $wbRels .= '</Relationships>';
        $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);

        // 4. xl/workbook.xml
        $wb = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n";
        $wb .= '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $wb .= '<sheets>';
        foreach ($this->sheets as $idx => $s) {
            $num = $idx + 1;
            $safeName = substr(preg_replace('/[\\\\\/\?\*\[\]]/', '_', $s['name']), 0, 31);
            $wb .= "<sheet name=\"{$safeName}\" sheetId=\"{$num}\" r:id=\"rIdSheet{$num}\"/>";
        }
        $wb .= '</sheets>';
        $wb .= '</workbook>';
        $zip->addFromString('xl/workbook.xml', $wb);

        // 5. xl/styles.xml
        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n";
        $styles .= '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';

        // Fonts
        $styles .= '<fonts count="6">';
        $styles .= '<font><sz val="10"/><color rgb="FF000000"/><name val="Segoe UI"/></font>'; // 0: Normal
        $styles .= '<font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Segoe UI"/></font>'; // 1: Header Bold White
        $styles .= '<font><b/><sz val="10"/><color rgb="FF1F2937"/><name val="Segoe UI"/></font>'; // 2: Bold Dark
        $styles .= '<font><i/><sz val="9.5"/><color rgb="FF4B5563"/><name val="Segoe UI"/></font>'; // 3: Italic Gray
        $styles .= '<font><b/><sz val="13"/><color rgb="FF991B1B"/><name val="Segoe UI"/></font>'; // 4: Title Red
        $styles .= '<font><b/><sz val="10"/><color rgb="FF065F46"/><name val="Segoe UI"/></font>'; // 5: Pass Green
        $styles .= '</fonts>';

        // Fills
        $styles .= '<fills count="8">';
        $styles .= '<fill><patternFill patternType="none"/></fill>'; // 0
        $styles .= '<fill><patternFill patternType="gray125"/></fill>'; // 1
        $styles .= '<fill><patternFill patternType="solid"><fgColor rgb="FFB91C1C"/></patternFill></fill>'; // 2: Bank Jatim Crimson Red
        $styles .= '<fill><patternFill patternType="solid"><fgColor rgb="FFF9FAFB"/></patternFill></fill>'; // 3: Alternating row light gray
        $styles .= '<fill><patternFill patternType="solid"><fgColor rgb="FFFEF2F2"/></patternFill></fill>'; // 4: Banner soft red
        $styles .= '<fill><patternFill patternType="solid"><fgColor rgb="FFECFDF5"/></patternFill></fill>'; // 5: Pass light green
        $styles .= '<fill><patternFill patternType="solid"><fgColor rgb="FFEFF6FF"/></patternFill></fill>'; // 6: Soft blue
        $styles .= '<fill><patternFill patternType="solid"><fgColor rgb="FFFEF3C7"/></patternFill></fill>'; // 7: Soft yellow
        $styles .= '</fills>';

        // Borders
        $styles .= '<borders count="2">';
        $styles .= '<border><left/><right/><top/><bottom/><diagonal/></border>'; // 0: None
        $styles .= '<border>'. // 1: Thin border
                   '<left style="thin"><color rgb="FFD1D5DB"/></left>'.
                   '<right style="thin"><color rgb="FFD1D5DB"/></right>'.
                   '<top style="thin"><color rgb="FFD1D5DB"/></top>'.
                   '<bottom style="thin"><color rgb="FFD1D5DB"/></bottom>'.
                   '</border>';
        $styles .= '</borders>';

        // Cell Style Xfs
        $styles .= '<cellStyleXfs count="1">';
        $styles .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>';
        $styles .= '</cellStyleXfs>';

        // Cell Xfs
        $styles .= '<cellXfs count="10">';
        // 0: Default
        $styles .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>';
        // 1: Header (Red background, white bold text, centered, thin border)
        $styles .= '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>';
        // 2: Regular Left Border
        $styles .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="top" wrapText="1"/></xf>';
        // 3: Alternating Left Border
        $styles .= '<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="top" wrapText="1"/></xf>';
        // 4: Regular Center Border
        $styles .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="top"/></xf>';
        // 5: Alternating Center Border
        $styles .= '<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="top"/></xf>';
        // 6: Bold Left Border (Key steps / Subheader)
        $styles .= '<xf numFmtId="0" fontId="2" fillId="6" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="top" wrapText="1"/></xf>';
        // 7: Title Banner (Red font, soft red bg, no border)
        $styles .= '<xf numFmtId="0" fontId="4" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>';
        // 8: Status Pass (Green bold font, light green bg)
        $styles .= '<xf numFmtId="0" fontId="5" fillId="5" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="top"/></xf>';
        // 9: Code / Monospace style
        $styles .= '<xf numFmtId="0" fontId="2" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="top" wrapText="1"/></xf>';
        $styles .= '</cellXfs>';

        $styles .= '</styleSheet>';
        $zip->addFromString('xl/styles.xml', $styles);

        // 6. Worksheets
        foreach ($this->sheets as $idx => $s) {
            $num = $idx + 1;
            $ws = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n";
            $ws .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';

            // Sheet Views (freeze pane)
            $freezeRow = $s['titleBanner'] ? 3 : 2;
            $ws .= '<sheetViews>';
            $ws .= '<sheetView tabSelected="'.($idx === 0 ? '1' : '0').'" workbookViewId="0">';
            $ws .= "<pane ySplit=\"{$freezeRow}\" topLeftCell=\"A".($freezeRow + 1).'" activePane="bottomLeft" state="frozen"/>';
            $ws .= '</sheetView>';
            $ws .= '</sheetViews>';

            // Columns width
            $ws .= '<cols>';
            foreach ($s['columns'] as $cIdx => $colDef) {
                $cNum = $cIdx + 1;
                $w = $colDef['width'] ?? 15;
                $ws .= "<col min=\"{$cNum}\" max=\"{$cNum}\" width=\"{$w}\" customWidth=\"1\"/>";
            }
            $ws .= '</cols>';

            $ws .= '<sheetData>';
            $currentRow = 1;

            // Optional Title Banner
            if (! empty($s['titleBanner'])) {
                $lastColLetter = $this->colName(count($s['columns']));
                $ws .= "<row r=\"{$currentRow}\" ht=\"30\" customHeight=\"1\">";
                $ws .= "<c r=\"A{$currentRow}\" s=\"7\" t=\"inlineStr\"><is><t>".$this->escape($s['titleBanner']).'</t></is></c>';
                for ($ci = 2; $ci <= count($s['columns']); $ci++) {
                    $cLetter = $this->colName($ci);
                    $ws .= "<c r=\"{$cLetter}{$currentRow}\" s=\"7\"/>";
                }
                $ws .= '</row>';
                $currentRow++;
            }

            // Header Row
            $ws .= "<row r=\"{$currentRow}\" ht=\"28\" customHeight=\"1\">";
            foreach ($s['columns'] as $cIdx => $colDef) {
                $cLetter = $this->colName($cIdx + 1);
                $title = $colDef['title'] ?? "Col {$cLetter}";
                $ws .= "<c r=\"{$cLetter}{$currentRow}\" s=\"1\" t=\"inlineStr\"><is><t>".$this->escape($title).'</t></is></c>';
            }
            $ws .= '</row>';
            $currentRow++;

            // Data Rows
            foreach ($s['rows'] as $rIdx => $rowItems) {
                $isAlt = ($rIdx % 2 === 1);
                $ws .= "<row r=\"{$currentRow}\" ht=\"24\" customHeight=\"0\">";
                foreach ($s['columns'] as $cIdx => $colDef) {
                    $cLetter = $this->colName($cIdx + 1);
                    $val = (string) ($rowItems[$colDef['key']] ?? '');

                    // Style selection
                    $styleId = 2;
                    if (isset($colDef['align']) && $colDef['align'] === 'center') {
                        $styleId = $isAlt ? 5 : 4;
                    } else {
                        $styleId = $isAlt ? 3 : 2;
                    }

                    if (isset($colDef['type']) && $colDef['type'] === 'status') {
                        $styleId = 8; // Green status
                    } elseif (isset($colDef['type']) && $colDef['type'] === 'code') {
                        $styleId = 9;
                    }

                    $ws .= "<c r=\"{$cLetter}{$currentRow}\" s=\"{$styleId}\" t=\"inlineStr\"><is><t>".$this->escape($val).'</t></is></c>';
                }
                $ws .= '</row>';
                $currentRow++;
            }

            $ws .= '</sheetData>';

            // Merge cells for title banner if present
            if (! empty($s['titleBanner'])) {
                $lastColLetter = $this->colName(count($s['columns']));
                $ws .= '<mergeCells count="1">';
                $ws .= "<mergeCell ref=\"A1:{$lastColLetter}1\"/>";
                $ws .= '</mergeCells>';
            }

            $ws .= '</worksheet>';
            $zip->addFromString("xl/worksheets/sheet{$num}.xml", $ws);
        }

        $zip->close();

        return true;
    }
}
