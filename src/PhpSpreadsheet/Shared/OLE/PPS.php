<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Shared\OLE;

// vim: set expandtab tabstop=4 shiftwidth=4:
// +----------------------------------------------------------------------+
// | PHP Version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2002 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.02 of the PHP license,      |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Author: Xavier Noguer <xnoguer@php.net>                              |
// | Based on OLE::Storage_Lite by Kawai, Takanori                        |
// +----------------------------------------------------------------------+
//
use Php_Office\Php_Spreadsheet\Shared\OLE;
/**
 * Class for creating PPS's for OLE containers.
 *
 * @author   Xavier Noguer <xnoguer@php.net>
 */
class PPS
{
    private const ALL_ONE_BITS = PHP_INT_SIZE > 4 ? 0xffffffff : -1;
    /**
     * The PPS index.
     */
    public int $No;
    /**
     * The PPS name (in Unicode).
     */
    public string $Name;
    /**
     * The PPS type. Dir, Root or File.
     */
    public int $Type;
    /**
     * The index of the previous PPS.
     */
    public int $prev_pps;
    /**
     * The index of the next PPS.
     */
    public int $next_pps;
    /**
     * The index of it's first child if this is a Dir or Root PPS.
     */
    public int $dir_pps;
    /**
     * A timestamp.
     */
    public float|int $Time1st;
    /**
     * A timestamp.
     */
    public float|int $Time2nd;
    /**
     * Starting block (small or big) for this PPS's data  inside the container.
     */
    public ?int $start_block = null;
    /**
     * The size of the PPS's data (in bytes).
     */
    public int $Size;
    /**
     * The PPS's data (only used if it's not using a temporary file).
     */
    public string $_data = '';
    /**
     * Pointer to OLE container.
     */
    public OLE $ole;
    /**
     * The constructor.
     *
     * @param ?int $No The PPS index
     * @param ?string $name The PPS name
     * @param ?int $type The PPS type. Dir, Root or File
     * @param ?int $prev The index of the previous PPS
     * @param ?int $next The index of the next PPS
     * @param ?int $dir The index of it's first child if this is a Dir or Root PPS
     * @param null|float|int $time_1st A timestamp
     * @param null|float|int $time_2nd A timestamp
     * @param ?string $data The (usually binary) source data of the PPS
     * @param mixed[] $children Array containing children PPS for this PPS
     */
    public function __construct(
        ?int $No,
        ?string $name,
        ?int $type,
        ?int $prev,
        ?int $next,
        ?int $dir,
        $time_1st,
        $time_2nd,
        ?string $data,
        /**
         * Array of child PPS's (only used by Root and Dir PPS's).
         */
        public array $children
    )
    {
        $this->No = (int) $No;
        $this->Name = (string) $name;
        $this->Type = (int) $type;
        $this->prev_pps = (int) $prev;
        $this->next_pps = (int) $next;
        $this->dir_pps = (int) $dir;
        $this->Time1st = $time_1st ?? 0;
        $this->Time2nd = $time_2nd ?? 0;
        $this->_data = (string) $data;
        $this->Size = strlen((string) $data);
    }
    /**
     * Returns the amount of data saved for this PPS.
     *
     * @return int The amount of data (in bytes)
     */
    public function get_data_len(): int
    {
        //if (!isset($this->_data)) {
        //    return 0;
        //}
        return strlen($this->_data);
    }
    /**
     * Returns a string with the PPS's WK (What is a WK?).
     *
     * @return string The binary string
     */
    public function get_pps_wk(): string
    {
        $ret = str_pad($this->Name, 64, "\x00");
        // 128
        return $ret . (pack('v', strlen($this->Name) + 2) . pack('c', $this->Type) . pack('c', 0x0) . pack('V', $this->prev_pps) . pack('V', $this->next_pps) . pack('V', $this->dir_pps) . "\x00\t\x02\x00" . "\x00\x00\x00\x00" . "\xc0\x00\x00\x00" . "\x00\x00\x00F" . "\x00\x00\x00\x00" . OLE::local_date_to_ole($this->Time1st) . OLE::local_date_to_ole($this->Time2nd) . pack('V', $this->start_block ?? 0) . pack('V', $this->Size) . pack('V', 0));
    }
    /**
     * Updates index and pointers to previous, next and children PPS's for this
     * PPS. I don't think it'll work with Dir PPS's.
     *
     * @param self[] $raList Reference to the array of PPS's for the whole OLE
     *                          container
     *
     * @return int The index for this PPS
     */
    public static function save_pps_set_pnt(array &$ra_list, mixed $to_save, int $depth = 0): int
    {
        if (!is_array($to_save) || empty($to_save)) {
            return self::ALL_ONE_BITS;
        }
        /** @var self[] $to_save */
        if (count($to_save) == 1) {
            $cnt = count($ra_list);
            // If the first entry, it's the root... Don't clone it!
            $ra_list[$cnt] = $depth == 0 ? $to_save[0] : clone $to_save[0];
            $ra_list[$cnt]->No = $cnt;
            $ra_list[$cnt]->prev_pps = self::ALL_ONE_BITS;
            $ra_list[$cnt]->next_pps = self::ALL_ONE_BITS;
            $ra_list[$cnt]->dir_pps = self::save_pps_set_pnt($ra_list, @$ra_list[$cnt]->children, $depth++);
        } else {
            $i_pos = (int) floor(count($to_save) / 2);
            $a_prev = array_slice($to_save, 0, $i_pos);
            $a_next = array_slice($to_save, $i_pos + 1);
            $cnt = count($ra_list);
            // If the first entry, it's the root... Don't clone it!
            $ra_list[$cnt] = $depth == 0 ? $to_save[$i_pos] : clone $to_save[$i_pos];
            $ra_list[$cnt]->No = $cnt;
            $ra_list[$cnt]->prev_pps = self::save_pps_set_pnt($ra_list, $a_prev, $depth++);
            $ra_list[$cnt]->next_pps = self::save_pps_set_pnt($ra_list, $a_next, $depth++);
            $ra_list[$cnt]->dir_pps = self::save_pps_set_pnt($ra_list, @$ra_list[$cnt]->children, $depth++);
        }
        return $cnt;
    }
}