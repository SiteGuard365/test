<?php
/**
 * XLSX export.
 *
 * @package WCPI
 */

defined( 'ABSPATH' ) || exit;

class WCPI_Export_XLSX {

    /**
     * Generate XLSX file.
     *
     * @param string $report Report key.
     * @param string $from From.
     * @param string $to To.
     * @param bool   $compare Include comparison.
     * @return array<string, string>|WP_Error
     */
    public static function generate( string $report, string $from, string $to, bool $compare = false ) {
        if ( ! class_exists( 'ZipArchive' ) ) {
            return new WP_Error( 'wcpi_missing_ziparchive', __( 'Excel export requires the ZipArchive PHP extension.', WCPI_TEXT_DOMAIN ) );
        }

        $rows = WCPI_Export_CSV::build_rows( $report, $from, $to, $compare );
        if ( empty( $rows ) ) {
            return new WP_Error( 'wcpi_empty_export', __( 'No data available for export.', WCPI_TEXT_DOMAIN ) );
        }

        $temp_file = wp_tempnam( 'wcpi-export.xlsx' );
        if ( ! $temp_file ) {
            return new WP_Error( 'wcpi_temp_export', __( 'Unable to create a temporary export file.', WCPI_TEXT_DOMAIN ) );
        }

        $zip = new ZipArchive();
        if ( true !== $zip->open( $temp_file, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
            @unlink( $temp_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
            return new WP_Error( 'wcpi_xlsx_create', __( 'Unable to prepare the Excel export archive.', WCPI_TEXT_DOMAIN ) );
        }

        $zip->addFromString( '[Content_Types].xml', self::content_types_xml() );
        $zip->addFromString( '_rels/.rels', self::root_rels_xml() );
        $zip->addFromString( 'docProps/app.xml', self::app_xml() );
        $zip->addFromString( 'docProps/core.xml', self::core_xml() );
        $zip->addFromString( 'xl/workbook.xml', self::workbook_xml() );
        $zip->addFromString( 'xl/_rels/workbook.xml.rels', self::workbook_rels_xml() );
        $zip->addFromString( 'xl/styles.xml', self::styles_xml() );
        $zip->addFromString( 'xl/worksheets/sheet1.xml', self::worksheet_xml( $rows ) );
        $zip->close();

        $contents = file_get_contents( $temp_file );
        @unlink( $temp_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink

        if ( false === $contents ) {
            return new WP_Error( 'wcpi_xlsx_read', __( 'Unable to read the generated Excel file.', WCPI_TEXT_DOMAIN ) );
        }

        $filename = WCPI_Filesystem::secure_filename( 'wcpi-' . $report, 'xlsx' );
        $file     = WCPI_Filesystem::write_file( 'exports', $filename, $contents );

        if ( ! is_wp_error( $file ) ) {
            WCPI_Export_CSV::log_export( 'xlsx', $report, $from, $to, $filename, $file );
        }

        return $file;
    }

    /**
     * Worksheet XML.
     *
     * @param array<int, array<int, mixed>> $rows Rows.
     * @return string
     */
    private static function worksheet_xml( array $rows ): string {
        $sheet_rows = '';

        foreach ( $rows as $row_index => $row ) {
            $cells = '';

            foreach ( $row as $column_index => $value ) {
                $reference = self::column_name( $column_index + 1 ) . (string) ( $row_index + 1 );
                $style     = 0 === $row_index ? ' s="1"' : '';

                if ( is_numeric( $value ) && '' !== (string) $value ) {
                    $cells .= '<c r="' . esc_attr( $reference ) . '"' . $style . '><v>' . self::xml_value( $value ) . '</v></c>';
                    continue;
                }

                $cells .= '<c r="' . esc_attr( $reference ) . '" t="inlineStr"' . $style . '><is><t xml:space="preserve">' . self::xml_value( $value ) . '</t></is></c>';
            }

            $sheet_rows .= '<row r="' . (string) ( $row_index + 1 ) . '">' . $cells . '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="18"/>'
            . '<cols><col min="1" max="12" width="18" customWidth="1"/></cols>'
            . '<sheetData>' . $sheet_rows . '</sheetData>'
            . '</worksheet>';
    }

    /**
     * Content types.
     *
     * @return string
     */
    private static function content_types_xml(): string {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '</Types>';
    }

    /**
     * Root relationships.
     *
     * @return string
     */
    private static function root_rels_xml(): string {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    /**
     * App metadata XML.
     *
     * @return string
     */
    private static function app_xml(): string {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            . '<Application>Site Guard 365</Application>'
            . '<DocSecurity>0</DocSecurity>'
            . '<ScaleCrop>false</ScaleCrop>'
            . '<HeadingPairs><vt:vector size="2" baseType="variant"><vt:variant><vt:lpstr>Worksheets</vt:lpstr></vt:variant><vt:variant><vt:i4>1</vt:i4></vt:variant></vt:vector></HeadingPairs>'
            . '<TitlesOfParts><vt:vector size="1" baseType="lpstr"><vt:lpstr>Profit Intelligence</vt:lpstr></vt:vector></TitlesOfParts>'
            . '<Company>Site Guard 365</Company>'
            . '</Properties>';
    }

    /**
     * Core metadata XML.
     *
     * @return string
     */
    private static function core_xml(): string {
        $created = gmdate( 'Y-m-d\TH:i:s\Z' );

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:title>SG365 Profit &amp; Expense Tracker</dc:title>'
            . '<dc:creator>Site Guard 365</dc:creator>'
            . '<cp:lastModifiedBy>Site Guard 365</cp:lastModifiedBy>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . esc_html( $created ) . '</dcterms:created>'
            . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . esc_html( $created ) . '</dcterms:modified>'
            . '</cp:coreProperties>';
    }

    /**
     * Workbook XML.
     *
     * @return string
     */
    private static function workbook_xml(): string {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Profit Intelligence" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    /**
     * Workbook relationships XML.
     *
     * @return string
     */
    private static function workbook_rels_xml(): string {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    /**
     * Styles XML.
     *
     * @return string
     */
    private static function styles_xml(): string {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    /**
     * Convert 1-based column number to Excel column name.
     *
     * @param int $index Column index.
     * @return string
     */
    private static function column_name( int $index ): string {
        $name = '';

        while ( $index > 0 ) {
            $index--;
            $name = chr( 65 + ( $index % 26 ) ) . $name;
            $index = (int) floor( $index / 26 );
        }

        return $name;
    }

    /**
     * Safe XML value.
     *
     * @param mixed $value Value.
     * @return string
     */
    private static function xml_value( $value ): string {
        return htmlspecialchars( (string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
    }
}
