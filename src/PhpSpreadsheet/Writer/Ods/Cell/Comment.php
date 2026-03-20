<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer\Ods\Cell;

use Php_Office\Php_Spreadsheet\Cell\Cell;
use Php_Office\Php_Spreadsheet\Shared\Xml_Writer;
/**
 * @author     Alexander Pervakov <frost-nzcr4@jagmort.com>
 */
class Comment
{
    public static function write(Xml_Writer $obj_writer, Cell $cell): void
    {
        $comments = $cell->get_worksheet()->get_comments();
        if (!isset($comments[$cell->get_coordinate()])) {
            return;
        }
        $comment = $comments[$cell->get_coordinate()];
        $obj_writer->start_element('office:annotation');
        $obj_writer->write_attribute('svg:width', $comment->get_width());
        $obj_writer->write_attribute('svg:height', $comment->get_height());
        $obj_writer->write_attribute('svg:x', $comment->get_margin_left());
        $obj_writer->write_attribute('svg:y', $comment->get_margin_top());
        $obj_writer->write_element('dc:creator', $comment->get_author());
        $obj_writer->start_element('text:p');
        $text = $comment->get_text()->get_plain_text();
        $text_elements = explode("\n", $text);
        $new_line_owed = false;
        foreach ($text_elements as $text_segment) {
            if ($new_line_owed) {
                $obj_writer->write_element('text:line-break');
            }
            $new_line_owed = true;
            if ($text_segment !== '') {
                $obj_writer->write_element('text:span', $text_segment);
            }
        }
        $obj_writer->end_element();
        // text:p
        $obj_writer->end_element();
    }
}