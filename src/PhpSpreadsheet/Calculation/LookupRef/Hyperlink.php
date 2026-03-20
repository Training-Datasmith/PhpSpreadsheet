<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Lookup_Ref;

use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Cell\Cell;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
class Hyperlink
{
    /**
     * HYPERLINK.
     *
     * Excel Function:
     *        =HYPERLINK(linkURL, [displayName])
     *
     * @param mixed $linkURL Expect string. Value to check, is also the value returned when no error
     * @param mixed $displayName Expect string. Value to return when testValue is an error condition
     * @param ?Cell $cell The cell to set the hyperlink in
     *
     * @return string The value of $displayName (or $linkURL if $displayName was blank)
     */
    public static function set(mixed $link_url = '', mixed $display_name = null, ?Cell $cell = null): string
    {
        $worksheet = null;
        $coordinate = '';
        if ($cell !== null) {
            $coordinate = $cell->get_coordinate();
            $worksheet = $cell->get_worksheet_or_null();
        }
        $link_url = $link_url === null ? '' : String_Helper::convert_to_string(Functions::flatten_single_value($link_url));
        $display_name = $display_name === null ? '' : Functions::flatten_single_value($display_name);
        if (!is_object($cell) || trim($link_url) == '') {
            return Excel_Error::REF();
        }
        $display_name = String_Helper::convert_to_string($display_name, false);
        if (trim($display_name) === '') {
            $display_name = $link_url;
        }
        $worksheet?->get_cell($coordinate)->get_hyperlink()->set_url($link_url)->set_tooltip($display_name)->set_display('');
        return $display_name;
    }
}