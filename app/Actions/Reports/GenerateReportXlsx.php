<?php

namespace App\Actions\Reports;

use RuntimeException;
use ZipArchive;

/**
 * Write a minimal, dependency-free XLSX workbook from a report document.
 *
 * The workbook uses inline strings, so user-supplied text can never be
 * interpreted as a spreadsheet formula by Excel.
 */
class GenerateReportXlsx
{
    /**
     * @param  array<string, mixed>  $document
     */
    public function __invoke(array $document): string
    {
        $path = tempnam(sys_get_temp_dir(), 'report-');

        if ($path === false) {
            throw new RuntimeException('Unable to create a temporary report file.');
        }

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create the Excel workbook.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->packageRelationships());
        $zip->addFromString('docProps/core.xml', $this->coreProperties($document));
        $zip->addFromString('docProps/app.xml', $this->appProperties());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationships());
        $zip->addFromString('xl/styles.xml', $this->styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->worksheet($document));
        $zip->close();

        return $path;
    }

    private function contentTypes(): string
    {
        return $this->xml(<<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
    <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
    <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
    <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
</Types>
XML);
    }

    private function packageRelationships(): string
    {
        return $this->xml(<<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
    <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
</Relationships>
XML);
    }

    private function workbook(): string
    {
        return $this->xml(<<<'XML'
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>
        <sheet name="Report" sheetId="1" r:id="rId1"/>
    </sheets>
</workbook>
XML);
    }

    private function workbookRelationships(): string
    {
        return $this->xml(<<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
XML);
    }

    private function styles(): string
    {
        return $this->xml(<<<'XML'
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <fonts count="4">
        <font><sz val="10"/><name val="Calibri"/></font>
        <font><b/><sz val="10"/><name val="Calibri"/></font>
        <font><b/><sz val="16"/><name val="Calibri"/></font>
        <font><sz val="9"/><color rgb="FF666666"/><name val="Calibri"/></font>
    </fonts>
    <fills count="3">
        <fill><patternFill patternType="none"/></fill>
        <fill><patternFill patternType="gray125"/></fill>
        <fill><patternFill patternType="solid"><fgColor rgb="FFF2F2F2"/><bgColor indexed="64"/></patternFill></fill>
    </fills>
    <borders count="2">
        <border><left/><right/><top/><bottom/><diagonal/></border>
        <border><left style="thin"><color rgb="FFD9D9D9"/></left><right style="thin"><color rgb="FFD9D9D9"/></right><top style="thin"><color rgb="FFD9D9D9"/></top><bottom style="thin"><color rgb="FFD9D9D9"/></bottom><diagonal/></border>
    </borders>
    <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
    <cellXfs count="4">
        <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>
        <xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>
        <xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>
        <xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>
    </cellXfs>
    <cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>
</styleSheet>
XML);
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function worksheet(array $document): string
    {
        $rows = [];
        $rowNumber = 1;

        $rows[] = $this->row($rowNumber++, [$document['title']], 2);
        $rows[] = $this->row($rowNumber++, [
            sprintf('%s · Generated %s', $document['app_name'], $document['generated_at']),
        ], 3);

        foreach ($document['filters'] as $filter) {
            $rows[] = $this->row($rowNumber++, [$filter['label'].': '.$filter['value']], 3);
        }

        $rows[] = $this->emptyRow($rowNumber++);

        foreach ($document['sections'] as $section) {
            $rows[] = $this->row($rowNumber++, [$section['heading']], 1);
            $rows[] = $this->row($rowNumber++, $section['headers'], 1);

            foreach ($section['rows'] as $values) {
                $rows[] = $this->row($rowNumber++, $values);
            }

            $rows[] = $this->emptyRow($rowNumber++);
        }

        foreach ($document['notes'] as $note) {
            $rows[] = $this->row($rowNumber++, [$note], 3);
        }

        return $this->xml(sprintf(
            '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
            .'<cols><col min="1" max="1" width="34" customWidth="1"/><col min="2" max="12" width="18" customWidth="1"/></cols>'
            .'<sheetData>%s</sheetData>'
            .'<pageMargins left="0.5" right="0.5" top="0.5" bottom="0.5" header="0.3" footer="0.3"/>'
            .'</worksheet>',
            implode('', $rows),
        ));
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private function row(int $rowNumber, array $values, int $style = 0): string
    {
        $cells = [];

        foreach (array_values($values) as $index => $value) {
            $reference = $this->columnName($index + 1).$rowNumber;

            if (is_int($value) || is_float($value)) {
                $cells[] = sprintf('<c r="%s" s="%d"><v>%s</v></c>', $reference, $style, $value);

                continue;
            }

            $cells[] = sprintf(
                '<c r="%s" s="%d" t="inlineStr"><is><t xml:space="preserve">%s</t></is></c>',
                $reference,
                $style,
                htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8'),
            );
        }

        return sprintf('<row r="%d">%s</row>', $rowNumber, implode('', $cells));
    }

    private function emptyRow(int $rowNumber): string
    {
        return sprintf('<row r="%d"/>', $rowNumber);
    }

    private function columnName(int $number): string
    {
        $name = '';

        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)).$name;
            $number = intdiv($number, 26);
        }

        return $name;
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function coreProperties(array $document): string
    {
        $now = now()->utc()->format('Y-m-d\TH:i:s\Z');

        return $this->xml(sprintf(
            '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            .'<dc:title>%s</dc:title><dc:creator>%s</dc:creator>'
            .'<dcterms:created xsi:type="dcterms:W3CDTF">%s</dcterms:created>'
            .'<dcterms:modified xsi:type="dcterms:W3CDTF">%s</dcterms:modified>'
            .'</cp:coreProperties>',
            htmlspecialchars((string) $document['title'], ENT_XML1 | ENT_QUOTES, 'UTF-8'),
            htmlspecialchars((string) $document['app_name'], ENT_XML1 | ENT_QUOTES, 'UTF-8'),
            $now,
            $now,
        ));
    }

    private function appProperties(): string
    {
        return $this->xml(<<<'XML'
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties">
    <Application>Temple Street Reports</Application>
</Properties>
XML);
    }

    private function xml(string $xml): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.$xml;
    }
}
