<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer\Ods;

use Php_Office\Php_Spreadsheet\Shared\Xml_Writer;
class Styles extends Writer_Part
{
    /**
     * Write styles.xml to XML format.
     *
     * @return string XML Output
     */
    public function write(): string
    {
        $obj_writer = null;
        if ($this->get_parent_writer()->get_use_disk_caching()) {
            $obj_writer = new Xml_Writer(Xml_Writer::STORAGE_DISK, $this->get_parent_writer()->get_disk_caching_directory());
        } else {
            $obj_writer = new Xml_Writer(Xml_Writer::STORAGE_MEMORY);
        }
        // XML header
        $obj_writer->start_document('1.0', 'UTF-8');
        // Content
        $obj_writer->start_element('office:document-styles');
        $obj_writer->write_attribute('xmlns:office', 'urn:oasis:names:tc:opendocument:xmlns:office:1.0');
        $obj_writer->write_attribute('xmlns:style', 'urn:oasis:names:tc:opendocument:xmlns:style:1.0');
        $obj_writer->write_attribute('xmlns:text', 'urn:oasis:names:tc:opendocument:xmlns:text:1.0');
        $obj_writer->write_attribute('xmlns:table', 'urn:oasis:names:tc:opendocument:xmlns:table:1.0');
        $obj_writer->write_attribute('xmlns:draw', 'urn:oasis:names:tc:opendocument:xmlns:drawing:1.0');
        $obj_writer->write_attribute('xmlns:fo', 'urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0');
        $obj_writer->write_attribute('xmlns:xlink', 'http://www.w3.org/1999/xlink');
        $obj_writer->write_attribute('xmlns:dc', 'http://purl.org/dc/elements/1.1/');
        $obj_writer->write_attribute('xmlns:meta', 'urn:oasis:names:tc:opendocument:xmlns:meta:1.0');
        $obj_writer->write_attribute('xmlns:number', 'urn:oasis:names:tc:opendocument:xmlns:datastyle:1.0');
        $obj_writer->write_attribute('xmlns:presentation', 'urn:oasis:names:tc:opendocument:xmlns:presentation:1.0');
        $obj_writer->write_attribute('xmlns:svg', 'urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0');
        $obj_writer->write_attribute('xmlns:chart', 'urn:oasis:names:tc:opendocument:xmlns:chart:1.0');
        $obj_writer->write_attribute('xmlns:dr3d', 'urn:oasis:names:tc:opendocument:xmlns:dr3d:1.0');
        $obj_writer->write_attribute('xmlns:math', 'http://www.w3.org/1998/Math/MathML');
        $obj_writer->write_attribute('xmlns:form', 'urn:oasis:names:tc:opendocument:xmlns:form:1.0');
        $obj_writer->write_attribute('xmlns:script', 'urn:oasis:names:tc:opendocument:xmlns:script:1.0');
        $obj_writer->write_attribute('xmlns:ooo', 'http://openoffice.org/2004/office');
        $obj_writer->write_attribute('xmlns:ooow', 'http://openoffice.org/2004/writer');
        $obj_writer->write_attribute('xmlns:oooc', 'http://openoffice.org/2004/calc');
        $obj_writer->write_attribute('xmlns:dom', 'http://www.w3.org/2001/xml-events');
        $obj_writer->write_attribute('xmlns:rpt', 'http://openoffice.org/2005/report');
        $obj_writer->write_attribute('xmlns:of', 'urn:oasis:names:tc:opendocument:xmlns:of:1.2');
        $obj_writer->write_attribute('xmlns:xhtml', 'http://www.w3.org/1999/xhtml');
        $obj_writer->write_attribute('xmlns:grddl', 'http://www.w3.org/2003/g/data-view#');
        $obj_writer->write_attribute('xmlns:tableooo', 'http://openoffice.org/2009/table');
        $obj_writer->write_attribute('xmlns:css3t', 'http://www.w3.org/TR/css3-text/');
        $obj_writer->write_attribute('office:version', '1.2');
        $obj_writer->write_element('office:font-face-decls');
        $obj_writer->start_element('office:styles');
        $default_style = $this->get_parent_writer()->get_spreadsheet()->get_default_style();
        $obj_writer->start_element('style:default-style');
        $obj_writer->write_attribute('style:family', 'table-cell');
        $writer2 = new Cell\Style($obj_writer);
        $writer2->write_text_properties($default_style);
        $obj_writer->end_element();
        // style:default-style
        $obj_writer->start_element('style:style');
        $obj_writer->write_attribute('style:name', 'Default');
        $obj_writer->write_attribute('style:family', 'table-cell');
        $writer2->write_cell_properties($default_style);
        $obj_writer->end_element();
        // style:style 'Default' table-cell
        $obj_writer->end_element();
        // office:styles
        $obj_writer->start_element('office:automatic-styles');
        $obj_writer->start_element('style:page-layout');
        $obj_writer->write_attribute('style:name', 'Mpm1');
        $obj_writer->end_element();
        // style:page-layout
        $obj_writer->end_element();
        // office:automatic-styles
        $obj_writer->start_element('office:master-styles');
        $obj_writer->start_element('style:master-page');
        $obj_writer->write_attribute('style:name', 'Default');
        $obj_writer->write_attribute('style:page-layout-name', 'Mpm1');
        $obj_writer->end_element();
        //style:master-page
        $obj_writer->end_element();
        //office:master-styles
        $obj_writer->end_element();
        return $obj_writer->get_data();
    }
}