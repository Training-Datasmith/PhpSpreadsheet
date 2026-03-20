<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer\Ods;

use Php_Office\Php_Spreadsheet\Shared\Xml_Writer;
class Meta_Inf extends Writer_Part
{
    /**
     * Write META-INF/manifest.xml to XML format.
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
        // Manifest
        $obj_writer->start_element('manifest:manifest');
        $obj_writer->write_attribute('xmlns:manifest', 'urn:oasis:names:tc:opendocument:xmlns:manifest:1.0');
        $obj_writer->write_attribute('manifest:version', '1.2');
        $obj_writer->start_element('manifest:file-entry');
        $obj_writer->write_attribute('manifest:full-path', '/');
        $obj_writer->write_attribute('manifest:version', '1.2');
        $obj_writer->write_attribute('manifest:media-type', 'application/vnd.oasis.opendocument.spreadsheet');
        $obj_writer->end_element();
        $obj_writer->start_element('manifest:file-entry');
        $obj_writer->write_attribute('manifest:full-path', 'meta.xml');
        $obj_writer->write_attribute('manifest:media-type', 'text/xml');
        $obj_writer->end_element();
        $obj_writer->start_element('manifest:file-entry');
        $obj_writer->write_attribute('manifest:full-path', 'settings.xml');
        $obj_writer->write_attribute('manifest:media-type', 'text/xml');
        $obj_writer->end_element();
        $obj_writer->start_element('manifest:file-entry');
        $obj_writer->write_attribute('manifest:full-path', 'content.xml');
        $obj_writer->write_attribute('manifest:media-type', 'text/xml');
        $obj_writer->end_element();
        $obj_writer->start_element('manifest:file-entry');
        $obj_writer->write_attribute('manifest:full-path', 'Thumbnails/thumbnail.png');
        $obj_writer->write_attribute('manifest:media-type', 'image/png');
        $obj_writer->end_element();
        $obj_writer->start_element('manifest:file-entry');
        $obj_writer->write_attribute('manifest:full-path', 'styles.xml');
        $obj_writer->write_attribute('manifest:media-type', 'text/xml');
        $obj_writer->end_element();
        $obj_writer->end_element();
        return $obj_writer->get_data();
    }
}