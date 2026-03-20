<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer\Xlsx;

use Php_Office\Php_Spreadsheet\Reader\Xlsx\Namespaces;
use Php_Office\Php_Spreadsheet\Shared\Xml_Writer;
use Php_Office\Php_Spreadsheet\Spreadsheet;
use Php_Office\Php_Spreadsheet\Theme as SpreadsheetTheme;
class Theme extends Writer_Part
{
    /**
     * Write theme to XML format.
     *
     * @return string XML Output
     */
    public function write_theme(Spreadsheet $spreadsheet): string
    {
        // Create XML writer
        $obj_writer = null;
        if ($this->get_parent_writer()->get_use_disk_caching()) {
            $obj_writer = new Xml_Writer(Xml_Writer::STORAGE_DISK, $this->get_parent_writer()->get_disk_caching_directory());
        } else {
            $obj_writer = new Xml_Writer(Xml_Writer::STORAGE_MEMORY);
        }
        $theme = $spreadsheet->get_theme();
        // XML header
        $obj_writer->start_document('1.0', 'UTF-8', 'yes');
        // a:theme
        $obj_writer->start_element('a:theme');
        $obj_writer->write_attribute('xmlns:a', Namespaces::DRAWINGML);
        $obj_writer->write_attribute('name', 'Office Theme');
        // a:themeElements
        $obj_writer->start_element('a:themeElements');
        // a:clrScheme
        $obj_writer->start_element('a:clrScheme');
        $obj_writer->write_attribute('name', $theme->get_theme_color_name());
        $this->write_colour_scheme($obj_writer, $theme);
        $obj_writer->end_element();
        // a:fontScheme
        $obj_writer->start_element('a:fontScheme');
        $obj_writer->write_attribute('name', $theme->get_theme_font_name());
        // a:majorFont
        $obj_writer->start_element('a:majorFont');
        $this->write_fonts($obj_writer, $theme->get_major_font_latin(), $theme->get_major_font_east_asian(), $theme->get_major_font_complex_script(), $theme->get_major_font_substitutions());
        $obj_writer->end_element();
        // a:majorFont
        // a:minorFont
        $obj_writer->start_element('a:minorFont');
        $this->write_fonts($obj_writer, $theme->get_minor_font_latin(), $theme->get_minor_font_east_asian(), $theme->get_minor_font_complex_script(), $theme->get_minor_font_substitutions());
        $obj_writer->end_element();
        // a:minorFont
        $obj_writer->end_element();
        // a:fontScheme
        // a:fmtScheme
        $obj_writer->start_element('a:fmtScheme');
        $obj_writer->write_attribute('name', 'Office');
        // a:fillStyleLst
        $obj_writer->start_element('a:fillStyleLst');
        // a:solidFill
        $obj_writer->start_element('a:solidFill');
        // a:schemeClr
        $obj_writer->start_element('a:schemeClr');
        $obj_writer->write_attribute('val', 'phClr');
        $obj_writer->end_element();
        $obj_writer->end_element();
        // a:gradFill
        $obj_writer->start_element('a:gradFill');
        $obj_writer->write_attribute('rotWithShape', '1');
        // a:gsLst
        $obj_writer->start_element('a:gsLst');
        // a:gs
        $obj_writer->start_element('a:gs');
        $obj_writer->write_attribute('pos', '0');
        // a:schemeClr
        $obj_writer->start_element('a:schemeClr');
        $obj_writer->write_attribute('val', 'phClr');
        // a:tint
        $obj_writer->start_element('a:tint');
        $obj_writer->write_attribute('val', '50000');
        $obj_writer->end_element();
        // a:satMod
        $obj_writer->start_element('a:satMod');
        $obj_writer->write_attribute('val', '300000');
        $obj_writer->end_element();
        $obj_writer->end_element();
        $obj_writer->end_element();
        // a:gs
        $obj_writer->start_element('a:gs');
        $obj_writer->write_attribute('pos', '35000');
        // a:schemeClr
        $obj_writer->start_element('a:schemeClr');
        $obj_writer->write_attribute('val', 'phClr');
        // a:tint
        $obj_writer->start_element('a:tint');
        $obj_writer->write_attribute('val', '37000');
        $obj_writer->end_element();
        // a:satMod
        $obj_writer->start_element('a:satMod');
        $obj_writer->write_attribute('val', '300000');
        $obj_writer->end_element();
        $obj_writer->end_element();
        $obj_writer->end_element();
        // a:gs
        $obj_writer->start_element('a:gs');
        $obj_writer->write_attribute('pos', '100000');
        // a:schemeClr
        $obj_writer->start_element('a:schemeClr');
        $obj_writer->write_attribute('val', 'phClr');
        // a:tint
        $obj_writer->start_element('a:tint');
        $obj_writer->write_attribute('val', '15000');
        $obj_writer->end_element();
        // a:satMod
        $obj_writer->start_element('a:satMod');
        $obj_writer->write_attribute('val', '350000');
        $obj_writer->end_element();
        $obj_writer->end_element();
        $obj_writer->end_element();
        $obj_writer->end_element();
        // a:lin
        $obj_writer->start_element('a:lin');
        $obj_writer->write_attribute('ang', '16200000');
        $obj_writer->write_attribute('scaled', '1');
        $obj_writer->end_element();
        $obj_writer->end_element();
        // a:gradFill
        $obj_writer->start_element('a:gradFill');
        $obj_writer->write_attribute('rotWithShape', '1');
        // a:gsLst
        $obj_writer->start_element('a:gsLst');
        // a:gs
        $obj_writer->start_element('a:gs');
        $obj_writer->write_attribute('pos', '0');
        // a:schemeClr
        $obj_writer->start_element('a:schemeClr');
        $obj_writer->write_attribute('val', 'phClr');
        // a:shade
        $obj_writer->start_element('a:shade');
        $obj_writer->write_attribute('val', '51000');
        $obj_writer->end_element();
        // a:satMod
        $obj_writer->start_element('a:satMod');
        $obj_writer->write_attribute('val', '130000');
        $obj_writer->end_element();
        $obj_writer->end_element();
        $obj_writer->end_element();
        // a:gs
        $obj_writer->start_element('a:gs');
        $obj_writer->write_attribute('pos', '80000');
        // a:schemeClr
        $obj_writer->start_element('a:schemeClr');
        $obj_writer->write_attribute('val', 'phClr');
        // a:shade
        $obj_writer->start_element('a:shade');
        $obj_writer->write_attribute('val', '93000');
        $obj_writer->end_element();
        // a:satMod
        $obj_writer->start_element('a:satMod');
        $obj_writer->write_attribute('val', '130000');
        $obj_writer->end_element();
        $obj_writer->end_element();
        $obj_writer->end_element();
        // a:gs
        $obj_writer->start_element('a:gs');
        $obj_writer->write_attribute('pos', '100000');
        // a:schemeClr
        $obj_writer->start_element('a:schemeClr');
        $obj_writer->write_attribute('val', 'phClr');
        // a:shade
        $obj_writer->start_element('a:shade');
        $obj_writer->write_attribute('val', '94000');
        $obj_writer->end_element();
        // a:satMod
        $obj_writer->start_element('a:satMod');
        $obj_writer->write_attribute('val', '135000');
        $obj_writer->end_element();
        $obj_writer->end_element();
        $obj_writer->end_element();
        $obj_writer->end_element();
        // a:lin
        $obj_writer->start_element('a:lin');
        $obj_writer->write_attribute('ang', '16200000');
        $obj_writer->write_attribute('scaled', '0');
        $obj_writer->end_element();
        $obj_writer->end_element();
        $obj_writer->end_element();
        // a:lnStyleLst
        $obj_writer->start_element('a:lnStyleLst');
        // a:ln
        $obj_writer->start_element('a:ln');
        $obj_writer->write_attribute('w', '9525');
        $obj_writer->write_attribute('cap', 'flat');
        $obj_writer->write_attribute('cmpd', 'sng');
        $obj_writer->write_attribute('algn', 'ctr');
        // a:solidFill
        $obj_writer->start_element('a:solidFill');
        // a:schemeClr
        $obj_writer->start_element('a:schemeClr');
        $obj_writer->write_attribute('val', 'phClr');
        // a:shade
        $obj_writer->start_element('a:shade');
        $obj_writer->write_attribute('val', '95000');
        $obj_writer->end_element();
        // a:satMod
        $obj_writer->start_element('a:satMod');
        $obj_writer->write_attribute('val', '105000');
        $obj_writer->end_element();
        $obj_writer->end_element();
        $obj_writer->end_element();
        // a:prstDash
        $obj_writer->start_element('a:prstDash');
        $obj_writer->write_attribute('val', 'solid');
        $obj_writer->end_element();
        $obj_writer->end_element();
        // a:ln
        $obj_writer->start_element('a:ln');
        $obj_writer->write_attribute('w', '25400');
        $obj_writer->write_attribute('cap', 'flat');
        $obj_writer->write_attribute('cmpd', 'sng');
        $obj_writer->write_attribute('algn', 'ctr');
        // a:solidFill
        $obj_writer->start_element('a:solidFill');
        // a:schemeClr
        $obj_writer->start_element('a:schemeClr');
        $obj_writer->write_attribute('val', 'phClr');
        $obj_writer->end_element();
        $obj_writer->end_element();
        // a:prstDash
        $obj_writer->start_element('a:prstDash');
        $obj_writer->write_attribute('val', 'solid');
        $obj_writer->end_element();
        $obj_writer->end_element();
        // a:ln
        $obj_writer->start_element('a:ln');
        $obj_writer->write_attribute('w', '38100');
        $obj_writer->write_attribute('cap', 'flat');
        $obj_writer->write_attribute('cmpd', 'sng');
        $obj_writer->write_attribute('algn', 'ctr');
        // a:solidFill
        $obj_writer->start_element('a:solidFill');
        // a:schemeClr
        $obj_writer->start_element('a:schemeClr');
        $obj_writer->write_attribute('val', 'phClr');
        $obj_writer->end_element();
        $obj_writer->end_element();
        // a:prstDash
        $obj_writer->start_element('a:prstDash');
        $obj_writer->write_attribute('val', 'solid');
        $obj_writer->end_element();
        $obj_writer->end_element();
        $obj_writer->end_element();
        // a:effectStyleLst
        $obj_writer->start_element('a:effectStyleLst');
        // a:effectStyle
        $obj_writer->start_element('a:effectStyle');
        // a:effectLst
        $obj_writer->start_element('a:effectLst');
        // a:outerShdw
        $obj_writer->start_element('a:outerShdw');
        $obj_writer->write_attribute('blurRad', '40000');
        $obj_writer->write_attribute('dist', '20000');
        $obj_writer->write_attribute('dir', '5400000');
        $obj_writer->write_attribute('rotWithShape', '0');
        // a:srgbClr
        $obj_writer->start_element('a:srgbClr');
        $obj_writer->write_attribute('val', '000000');
        // a:alpha
        $obj_writer->start_element('a:alpha');
        $obj_writer->write_attribute('val', '38000');
        $obj_writer->end_element();
        $obj_writer->end_element();
        $obj_writer->end_element();
        $obj_writer->end_element();
        $obj_writer->end_element();
        // a:effectStyle
        $obj_writer->start_element('a:effectStyle');
        // a:effectLst
        $obj_writer->start_element('a:effectLst');
        // a:outerShdw
        $obj_writer->start_element('a:outerShdw');
        $obj_writer->write_attribute('blurRad', '40000');
        $obj_writer->write_attribute('dist', '23000');
        $obj_writer->write_attribute('dir', '5400000');
        $obj_writer->write_attribute('rotWithShape', '0');
        // a:srgbClr
        $obj_writer->start_element('a:srgbClr');
        $obj_writer->write_attribute('val', '000000');
        // a:alpha
        $obj_writer->start_element('a:alpha');
        $obj_writer->write_attribute('val', '35000');
        $obj_writer->end_element();
        $obj_writer->end_element();
        $obj_writer->end_element();
        $obj_writer->end_element();
        $obj_writer->end_element();
        // a:effectStyle
        $obj_writer->start_element('a:effectStyle');
        // a:effectLst
        $obj_writer->start_element('a:effectLst');
        // a:outerShdw
        $obj_writer->start_element('a:outerShdw');
        $obj_writer->write_attribute('blurRad', '40000');
        $obj_writer->write_attribute('dist', '23000');
        $obj_writer->write_attribute('dir', '5400000');
        $obj_writer->write_attribute('rotWithShape', '0');
        // a:srgbClr
        $obj_writer->start_element('a:srgbClr');
        $obj_writer->write_attribute('val', '000000');
        // a:alpha
        $obj_writer->start_element('a:alpha');
        $obj_writer->write_attribute('val', '35000');
        $obj_writer->end_element();
        $obj_writer->end_element();
        $obj_writer->end_element();
        $obj_writer->end_element();
        // a:scene3d
        $obj_writer->start_element('a:scene3d');
        // a:camera
        $obj_writer->start_element('a:camera');
        $obj_writer->write_attribute('prst', 'orthographicFront');
        // a:rot
        $obj_writer->start_element('a:rot');
        $obj_writer->write_attribute('lat', '0');
        $obj_writer->write_attribute('lon', '0');
        $obj_writer->write_attribute('rev', '0');
        $obj_writer->end_element();
        $obj_writer->end_element();
        // a:lightRig
        $obj_writer->start_element('a:lightRig');
        $obj_writer->write_attribute('rig', 'threePt');
        $obj_writer->write_attribute('dir', 't');
        // a:rot
        $obj_writer->start_element('a:rot');
        $obj_writer->write_attribute('lat', '0');
        $obj_writer->write_attribute('lon', '0');
        $obj_writer->write_attribute('rev', '1200000');
        $obj_writer->end_element();
        $obj_writer->end_element();
        $obj_writer->end_element();
        // a:sp3d
        $obj_writer->start_element('a:sp3d');
        // a:bevelT
        $obj_writer->start_element('a:bevelT');
        $obj_writer->write_attribute('w', '63500');
        $obj_writer->write_attribute('h', '25400');
        $obj_writer->end_element();
        $obj_writer->end_element();
        $obj_writer->end_element();
        $obj_writer->end_element();
        // a:bgFillStyleLst
        $obj_writer->start_element('a:bgFillStyleLst');
        // a:solidFill
        $obj_writer->start_element('a:solidFill');
        // a:schemeClr
        $obj_writer->start_element('a:schemeClr');
        $obj_writer->write_attribute('val', 'phClr');
        $obj_writer->end_element();
        $obj_writer->end_element();
        // a:gradFill
        $obj_writer->start_element('a:gradFill');
        $obj_writer->write_attribute('rotWithShape', '1');
        // a:gsLst
        $obj_writer->start_element('a:gsLst');
        // a:gs
        $obj_writer->start_element('a:gs');
        $obj_writer->write_attribute('pos', '0');
        // a:schemeClr
        $obj_writer->start_element('a:schemeClr');
        $obj_writer->write_attribute('val', 'phClr');
        // a:tint
        $obj_writer->start_element('a:tint');
        $obj_writer->write_attribute('val', '40000');
        $obj_writer->end_element();
        // a:satMod
        $obj_writer->start_element('a:satMod');
        $obj_writer->write_attribute('val', '350000');
        $obj_writer->end_element();
        $obj_writer->end_element();
        $obj_writer->end_element();
        // a:gs
        $obj_writer->start_element('a:gs');
        $obj_writer->write_attribute('pos', '40000');
        // a:schemeClr
        $obj_writer->start_element('a:schemeClr');
        $obj_writer->write_attribute('val', 'phClr');
        // a:tint
        $obj_writer->start_element('a:tint');
        $obj_writer->write_attribute('val', '45000');
        $obj_writer->end_element();
        // a:shade
        $obj_writer->start_element('a:shade');
        $obj_writer->write_attribute('val', '99000');
        $obj_writer->end_element();
        // a:satMod
        $obj_writer->start_element('a:satMod');
        $obj_writer->write_attribute('val', '350000');
        $obj_writer->end_element();
        $obj_writer->end_element();
        $obj_writer->end_element();
        // a:gs
        $obj_writer->start_element('a:gs');
        $obj_writer->write_attribute('pos', '100000');
        // a:schemeClr
        $obj_writer->start_element('a:schemeClr');
        $obj_writer->write_attribute('val', 'phClr');
        // a:shade
        $obj_writer->start_element('a:shade');
        $obj_writer->write_attribute('val', '20000');
        $obj_writer->end_element();
        // a:satMod
        $obj_writer->start_element('a:satMod');
        $obj_writer->write_attribute('val', '255000');
        $obj_writer->end_element();
        $obj_writer->end_element();
        $obj_writer->end_element();
        $obj_writer->end_element();
        // a:path
        $obj_writer->start_element('a:path');
        $obj_writer->write_attribute('path', 'circle');
        // a:fillToRect
        $obj_writer->start_element('a:fillToRect');
        $obj_writer->write_attribute('l', '50000');
        $obj_writer->write_attribute('t', '-80000');
        $obj_writer->write_attribute('r', '50000');
        $obj_writer->write_attribute('b', '180000');
        $obj_writer->end_element();
        $obj_writer->end_element();
        $obj_writer->end_element();
        // a:gradFill
        $obj_writer->start_element('a:gradFill');
        $obj_writer->write_attribute('rotWithShape', '1');
        // a:gsLst
        $obj_writer->start_element('a:gsLst');
        // a:gs
        $obj_writer->start_element('a:gs');
        $obj_writer->write_attribute('pos', '0');
        // a:schemeClr
        $obj_writer->start_element('a:schemeClr');
        $obj_writer->write_attribute('val', 'phClr');
        // a:tint
        $obj_writer->start_element('a:tint');
        $obj_writer->write_attribute('val', '80000');
        $obj_writer->end_element();
        // a:satMod
        $obj_writer->start_element('a:satMod');
        $obj_writer->write_attribute('val', '300000');
        $obj_writer->end_element();
        $obj_writer->end_element();
        $obj_writer->end_element();
        // a:gs
        $obj_writer->start_element('a:gs');
        $obj_writer->write_attribute('pos', '100000');
        // a:schemeClr
        $obj_writer->start_element('a:schemeClr');
        $obj_writer->write_attribute('val', 'phClr');
        // a:shade
        $obj_writer->start_element('a:shade');
        $obj_writer->write_attribute('val', '30000');
        $obj_writer->end_element();
        // a:satMod
        $obj_writer->start_element('a:satMod');
        $obj_writer->write_attribute('val', '200000');
        $obj_writer->end_element();
        $obj_writer->end_element();
        $obj_writer->end_element();
        $obj_writer->end_element();
        // a:path
        $obj_writer->start_element('a:path');
        $obj_writer->write_attribute('path', 'circle');
        // a:fillToRect
        $obj_writer->start_element('a:fillToRect');
        $obj_writer->write_attribute('l', '50000');
        $obj_writer->write_attribute('t', '50000');
        $obj_writer->write_attribute('r', '50000');
        $obj_writer->write_attribute('b', '50000');
        $obj_writer->end_element();
        $obj_writer->end_element();
        $obj_writer->end_element();
        $obj_writer->end_element();
        $obj_writer->end_element();
        $obj_writer->end_element();
        // a:objectDefaults
        $obj_writer->write_element('a:objectDefaults');
        // a:extraClrSchemeLst
        $obj_writer->write_element('a:extraClrSchemeLst');
        $obj_writer->end_element();
        // Return
        return $obj_writer->get_data();
    }
    /**
     * Write fonts to XML format.
     *
     * @param string[] $fontSet
     */
    private function write_fonts(Xml_Writer $obj_writer, string $latin_font, string $east_asian_font, string $complex_script_font, array $font_set): void
    {
        // a:latin
        $obj_writer->start_element('a:latin');
        $obj_writer->write_attribute('typeface', $latin_font);
        $obj_writer->end_element();
        // a:ea
        $obj_writer->start_element('a:ea');
        $obj_writer->write_attribute('typeface', $east_asian_font);
        $obj_writer->end_element();
        // a:cs
        $obj_writer->start_element('a:cs');
        $obj_writer->write_attribute('typeface', $complex_script_font);
        $obj_writer->end_element();
        foreach ($font_set as $font_script => $typeface) {
            $obj_writer->start_element('a:font');
            $obj_writer->write_attribute('script', $font_script);
            $obj_writer->write_attribute('typeface', $typeface);
            $obj_writer->end_element();
        }
    }
    /**
     * Write colour scheme to XML format.
     */
    private function write_colour_scheme(Xml_Writer $obj_writer, Spreadsheet_Theme $theme): void
    {
        $theme_array = $theme->get_theme_colors();
        // a:dk1
        $obj_writer->start_element('a:dk1');
        $obj_writer->start_element('a:sysClr');
        $obj_writer->write_attribute('val', 'windowText');
        $obj_writer->write_attribute('lastClr', $theme_array['dk1'] ?? '000000');
        $obj_writer->end_element();
        // a:sysClr
        $obj_writer->end_element();
        // a:dk1
        // a:lt1
        $obj_writer->start_element('a:lt1');
        $obj_writer->start_element('a:sysClr');
        $obj_writer->write_attribute('val', 'window');
        $obj_writer->write_attribute('lastClr', $theme_array['lt1'] ?? 'FFFFFF');
        $obj_writer->end_element();
        // a:sysClr
        $obj_writer->end_element();
        // a:lt1
        foreach ($theme_array as $colour_name => $colour_value) {
            if ($colour_name !== 'dk1' && $colour_name !== 'lt1') {
                $obj_writer->start_element('a:' . $colour_name);
                $obj_writer->start_element('a:srgbClr');
                $obj_writer->write_attribute('val', $colour_value);
                $obj_writer->end_element();
                // a:srgbClr
                $obj_writer->end_element();
                // a:$colourName
            }
        }
    }
}