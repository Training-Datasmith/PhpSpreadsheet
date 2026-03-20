<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer\Xlsx;

use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Comment;
use Php_Office\Php_Spreadsheet\Reader\Xlsx\Namespaces;
use Php_Office\Php_Spreadsheet\Shared\Xml_Writer;
use Php_Office\Php_Spreadsheet\Style\Alignment;
class Comments extends Writer_Part
{
    private const VALID_HORIZONTAL_ALIGNMENT = [Alignment::HORIZONTAL_CENTER, Alignment::HORIZONTAL_DISTRIBUTED, Alignment::HORIZONTAL_JUSTIFY, Alignment::HORIZONTAL_LEFT, Alignment::HORIZONTAL_RIGHT];
    /**
     * Write comments to XML format.
     *
     * @return string XML Output
     */
    public function write_comments(\Php_Office\Php_Spreadsheet\Worksheet\Worksheet $worksheet): string
    {
        // Create XML writer
        $obj_writer = null;
        if ($this->get_parent_writer()->get_use_disk_caching()) {
            $obj_writer = new Xml_Writer(Xml_Writer::STORAGE_DISK, $this->get_parent_writer()->get_disk_caching_directory());
        } else {
            $obj_writer = new Xml_Writer(Xml_Writer::STORAGE_MEMORY);
        }
        // XML header
        $obj_writer->start_document('1.0', 'UTF-8', 'yes');
        // Comments cache
        $comments = $worksheet->get_comments();
        // Authors cache
        $authors = [];
        $author_id = 0;
        foreach ($comments as $comment) {
            if (!isset($authors[$comment->get_author()])) {
                $authors[$comment->get_author()] = $author_id++;
            }
        }
        // comments
        $obj_writer->start_element('comments');
        $obj_writer->write_attribute('xmlns', Namespaces::MAIN);
        // Loop through authors
        $obj_writer->start_element('authors');
        foreach ($authors as $author => $index) {
            $obj_writer->write_element('author', $author);
        }
        $obj_writer->end_element();
        // Loop through comments
        $obj_writer->start_element('commentList');
        foreach ($comments as $key => $value) {
            $this->write_comment($obj_writer, $key, $value, $authors);
        }
        $obj_writer->end_element();
        $obj_writer->end_element();
        // Return
        return $obj_writer->get_data();
    }
    /**
     * Write comment to XML format.
     *
     * @param string $cellReference Cell reference
     * @param Comment $comment Comment
     * @param array<string, int> $authors Array of authors
     */
    private function write_comment(Xml_Writer $obj_writer, string $cell_reference, Comment $comment, array $authors): void
    {
        // comment
        $obj_writer->start_element('comment');
        $obj_writer->write_attribute('ref', $cell_reference);
        $obj_writer->write_attribute('authorId', (string) $authors[$comment->get_author()]);
        // text
        $obj_writer->start_element('text');
        $this->get_parent_writer()->get_writer_partstringtable()->write_rich_text($obj_writer, $comment->get_text());
        $obj_writer->end_element();
        $obj_writer->end_element();
    }
    /**
     * Write VML comments to XML format.
     *
     * @return string XML Output
     */
    public function write_vml_comments(\Php_Office\Php_Spreadsheet\Worksheet\Worksheet $worksheet): string
    {
        // Create XML writer
        $obj_writer = null;
        if ($this->get_parent_writer()->get_use_disk_caching()) {
            $obj_writer = new Xml_Writer(Xml_Writer::STORAGE_DISK, $this->get_parent_writer()->get_disk_caching_directory());
        } else {
            $obj_writer = new Xml_Writer(Xml_Writer::STORAGE_MEMORY);
        }
        // XML header
        $obj_writer->start_document('1.0', 'UTF-8', 'yes');
        // Comments cache
        $comments = $worksheet->get_comments();
        // xml
        $obj_writer->start_element('xml');
        $obj_writer->write_attribute('xmlns:v', Namespaces::URN_VML);
        $obj_writer->write_attribute('xmlns:o', Namespaces::URN_MSOFFICE);
        $obj_writer->write_attribute('xmlns:x', Namespaces::URN_EXCEL);
        // o:shapelayout
        $obj_writer->start_element('o:shapelayout');
        $obj_writer->write_attribute('v:ext', 'edit');
        // o:idmap
        $obj_writer->start_element('o:idmap');
        $obj_writer->write_attribute('v:ext', 'edit');
        $obj_writer->write_attribute('data', '1');
        $obj_writer->end_element();
        $obj_writer->end_element();
        // v:shapetype
        $obj_writer->start_element('v:shapetype');
        $obj_writer->write_attribute('id', '_x0000_t202');
        $obj_writer->write_attribute('coordsize', '21600,21600');
        $obj_writer->write_attribute('o:spt', '202');
        $obj_writer->write_attribute('path', 'm,l,21600r21600,l21600,xe');
        // v:stroke
        $obj_writer->start_element('v:stroke');
        $obj_writer->write_attribute('joinstyle', 'miter');
        $obj_writer->end_element();
        // v:path
        $obj_writer->start_element('v:path');
        $obj_writer->write_attribute('gradientshapeok', 't');
        $obj_writer->write_attribute('o:connecttype', 'rect');
        $obj_writer->end_element();
        $obj_writer->end_element();
        // Loop through comments
        foreach ($comments as $key => $value) {
            $this->write_vml_comment($obj_writer, $key, $value);
        }
        $obj_writer->end_element();
        // Return
        return $obj_writer->get_data();
    }
    /**
     * Write VML comment to XML format.
     *
     * @param string $cellReference Cell reference, eg: 'A1'
     * @param Comment $comment Comment
     */
    private function write_vml_comment(Xml_Writer $obj_writer, string $cell_reference, Comment $comment): void
    {
        // Metadata
        [$column, $row] = Coordinate::indexes_from_string($cell_reference);
        $id = 1024 + $column + $row;
        $id = substr("{$id}", 0, 4);
        // v:shape
        $obj_writer->start_element('v:shape');
        $obj_writer->write_attribute('id', '_x0000_s' . $id);
        $obj_writer->write_attribute('type', '#_x0000_t202');
        $obj_writer->write_attribute('style', 'position:absolute;margin-left:' . $comment->get_margin_left() . ';margin-top:' . $comment->get_margin_top() . ';width:' . $comment->get_width() . ';height:' . $comment->get_height() . ';z-index:1;visibility:' . ($comment->get_visible() ? 'visible' : 'hidden'));
        $obj_writer->write_attribute('fillcolor', '#' . $comment->get_fill_color()->get_rgb());
        $obj_writer->write_attribute('o:insetmode', 'auto');
        // v:fill
        $obj_writer->start_element('v:fill');
        $obj_writer->write_attribute('color2', '#' . $comment->get_fill_color()->get_rgb());
        if ($comment->has_background_image()) {
            $bg_image = $comment->get_background_image();
            $obj_writer->write_attribute('o:relid', 'rId' . $bg_image->get_image_index());
            $obj_writer->write_attribute('o:title', $bg_image->get_name());
            $obj_writer->write_attribute('type', 'frame');
        }
        $obj_writer->end_element();
        // v:shadow
        $obj_writer->start_element('v:shadow');
        $obj_writer->write_attribute('on', 't');
        $obj_writer->write_attribute('color', 'black');
        $obj_writer->write_attribute('obscured', 't');
        $obj_writer->end_element();
        // v:path
        $obj_writer->start_element('v:path');
        $obj_writer->write_attribute('o:connecttype', 'none');
        $obj_writer->end_element();
        // v:textbox
        $text_box_array = [Comment::TEXTBOX_DIRECTION_RTL => 'rtl', Comment::TEXTBOX_DIRECTION_LTR => 'ltr'];
        $textbox_rtl = $text_box_array[strtolower($comment->get_text_box_direction())] ?? 'auto';
        $obj_writer->start_element('v:textbox');
        $obj_writer->write_attribute('style', "mso-direction-alt:{$textbox_rtl}");
        // div
        $obj_writer->start_element('div');
        $obj_writer->write_attribute('style', $textbox_rtl === 'rtl' ? 'text-align:right;direction:rtl' : 'text-align:left');
        $obj_writer->end_element();
        $obj_writer->end_element();
        // x:ClientData
        $obj_writer->start_element('x:ClientData');
        $obj_writer->write_attribute('ObjectType', 'Note');
        // x:MoveWithCells
        $obj_writer->write_element('x:MoveWithCells', '');
        // x:SizeWithCells
        $obj_writer->write_element('x:SizeWithCells', '');
        // x:AutoFill
        $obj_writer->write_element('x:AutoFill', 'False');
        // x:TextHAlign horizontal alignment of text
        $alignment = strtolower($comment->get_alignment());
        if (in_array($alignment, self::VALID_HORIZONTAL_ALIGNMENT, true)) {
            $obj_writer->write_element('x:TextHAlign', ucfirst($alignment));
        }
        // x:Row
        $obj_writer->write_element('x:Row', (string) ($row - 1));
        // x:Column
        $obj_writer->write_element('x:Column', (string) ($column - 1));
        $obj_writer->end_element();
        $obj_writer->end_element();
    }
}