<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Shared\OLE\PPS;

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
use Php_Office\Php_Spreadsheet\Shared\OLE\PPS;
/**
 * Class for creating Root PPS's for OLE containers.
 *
 * @author   Xavier Noguer <xnoguer@php.net>
 */
class Root extends PPS
{
    /**
     * @var resource
     */
    private $file_handle;
    private ?int $small_block_size = null;
    private ?int $big_block_size = null;
    /**
     * @param null|float|int $time_1st A timestamp
     * @param null|float|int $time_2nd A timestamp
     * @param File[] $raChild
     */
    public function __construct($time_1st, $time_2nd, array $ra_child)
    {
        parent::__construct(null, OLE::asc_to_ucs('Root Entry'), OLE::OLE_PPS_TYPE_ROOT, null, null, null, $time_1st, $time_2nd, null, $ra_child);
    }
    /**
     * Method for saving the whole OLE container (including files).
     * In fact, if called with an empty argument (or '-'), it saves to a
     * temporary file and then outputs it's contents to stdout.
     * If a resource pointer to a stream created by fopen() is passed
     * it will be used, but you have to close such stream by yourself.
     *
     * @param resource $fileHandle the name of the file or stream where to save the OLE container
     *
     * @return bool true on success
     */
    public function save($file_handle): bool
    {
        $this->file_handle = $file_handle;
        // Initial Setting for saving
        $this->big_block_size = (int) 2 ** (isset($this->big_block_size) ? self::adjust2($this->big_block_size) : 9);
        $this->small_block_size = (int) 2 ** (isset($this->small_block_size) ? self::adjust2($this->small_block_size) : 6);
        // Make an array of PPS's (for Save)
        $a_list = [];
        PPS::save_pps_set_pnt($a_list, [$this]);
        // calculate values for header
        [$i_sb_dcnt, $i_b_bcnt, $i_pp_scnt] = $this->calc_size($a_list);
        //, $rhInfo);
        // Save Header
        $this->save_header((int) $i_sb_dcnt, (int) $i_b_bcnt, (int) $i_pp_scnt);
        // Make Small Data string (write SBD)
        $this->_data = $this->make_small_data($a_list);
        // Write BB
        $this->save_big_data((int) $i_sb_dcnt, $a_list);
        // Write PPS
        $this->save_pps($a_list);
        // Write Big Block Depot and BDList and Adding Header informations
        $this->save_bbd((int) $i_sb_dcnt, (int) $i_b_bcnt, (int) $i_pp_scnt);
        return true;
    }
    /**
     * Calculate some numbers.
     *
     * @param PPS[] $raList Reference to an array of PPS's
     *
     * @return float[] The array of numbers
     */
    private function calc_size(array &$ra_list): array
    {
        // Calculate Basic Setting
        [$i_sb_dcnt, $i_b_bcnt, $i_pp_scnt] = [0, 0, 0];
        $i_s_bcnt = 0;
        $i_count = count($ra_list);
        for ($i = 0; $i < $i_count; ++$i) {
            if ($ra_list[$i]->Type == OLE::OLE_PPS_TYPE_FILE) {
                $ra_list[$i]->Size = $ra_list[$i]->get_data_len();
                if ($ra_list[$i]->Size < OLE::OLE_DATA_SIZE_SMALL) {
                    $i_s_bcnt += floor($ra_list[$i]->Size / $this->small_block_size) + ($ra_list[$i]->Size % $this->small_block_size ? 1 : 0);
                } else {
                    $i_b_bcnt += floor($ra_list[$i]->Size / $this->big_block_size) + ($ra_list[$i]->Size % $this->big_block_size ? 1 : 0);
                }
            }
        }
        $i_small_len = $i_s_bcnt * $this->small_block_size;
        $i_sl_cnt = floor($this->big_block_size / OLE::OLE_LONG_INT_SIZE);
        $i_sb_dcnt = floor($i_s_bcnt / $i_sl_cnt) + ($i_s_bcnt % $i_sl_cnt ? 1 : 0);
        $i_b_bcnt += floor($i_small_len / $this->big_block_size) + ($i_small_len % $this->big_block_size ? 1 : 0);
        $i_cnt = count($ra_list);
        $i_bd_cnt = $this->big_block_size / OLE::OLE_PPS_SIZE;
        $i_pp_scnt = floor($i_cnt / $i_bd_cnt) + ($i_cnt % $i_bd_cnt ? 1 : 0);
        return [$i_sb_dcnt, $i_b_bcnt, $i_pp_scnt];
    }
    /**
     * Helper function for calculating a magic value for block sizes.
     *
     * @param int $i2 The argument
     *
     * @see save()
     */
    private static function adjust2(int $i2): float
    {
        $i_wk = log($i2) / log(2);
        return $i_wk > floor($i_wk) ? floor($i_wk) + 1 : $i_wk;
    }
    /**
     * Save OLE header.
     */
    private function save_header(int $i_sb_dcnt, int $i_b_bcnt, int $i_pp_scnt): void
    {
        $FILE = $this->file_handle;
        // Calculate Basic Setting
        $i_bl_cnt = $this->big_block_size / OLE::OLE_LONG_INT_SIZE;
        $i1st_bd_l = ($this->big_block_size - 0x4c) / OLE::OLE_LONG_INT_SIZE;
        $i_bd_ex_l = 0;
        $i_all = $i_b_bcnt + $i_pp_scnt + $i_sb_dcnt;
        $i_all_w = $i_all;
        $i_bd_cnt_w = floor($i_all_w / $i_bl_cnt) + ($i_all_w % $i_bl_cnt ? 1 : 0);
        $i_bd_cnt = floor(($i_all + $i_bd_cnt_w) / $i_bl_cnt) + (($i_all_w + $i_bd_cnt_w) % $i_bl_cnt ? 1 : 0);
        // Calculate BD count
        if ($i_bd_cnt > $i1st_bd_l) {
            while (1) {
                ++$i_bd_ex_l;
                ++$i_all_w;
                $i_bd_cnt_w = floor($i_all_w / $i_bl_cnt) + ($i_all_w % $i_bl_cnt ? 1 : 0);
                $i_bd_cnt = floor(($i_all_w + $i_bd_cnt_w) / $i_bl_cnt) + (($i_all_w + $i_bd_cnt_w) % $i_bl_cnt ? 1 : 0);
                if ($i_bd_cnt <= $i_bd_ex_l * $i_bl_cnt + $i1st_bd_l) {
                    break;
                }
            }
        }
        // Save Header
        fwrite($FILE, "\xd0\xcf\x11ࡱ\x1a\xe1" . "\x00\x00\x00\x00" . "\x00\x00\x00\x00" . "\x00\x00\x00\x00" . "\x00\x00\x00\x00" . pack('v', 0x3b) . pack('v', 0x3) . pack('v', -2) . pack('v', 9) . pack('v', 6) . pack('v', 0) . "\x00\x00\x00\x00" . "\x00\x00\x00\x00" . pack('V', $i_bd_cnt) . pack('V', $i_b_bcnt + $i_sb_dcnt) . pack('V', 0) . pack('V', 0x1000) . pack('V', $i_sb_dcnt ? 0 : -2) . pack('V', $i_sb_dcnt));
        // Extra BDList Start, Count
        if ($i_bd_cnt < $i1st_bd_l) {
            fwrite($FILE, pack('V', -2) . pack('V', 0));
        } else {
            fwrite($FILE, pack('V', $i_all + $i_bd_cnt) . pack('V', $i_bd_ex_l));
        }
        // BDList
        for ($i = 0; $i < $i1st_bd_l && $i < $i_bd_cnt; ++$i) {
            fwrite($FILE, pack('V', $i_all + $i));
        }
        if ($i < $i1st_bd_l) {
            $j_b = $i1st_bd_l - $i;
            for ($j = 0; $j < $j_b; ++$j) {
                fwrite($FILE, pack('V', -1));
            }
        }
    }
    /**
     * Saving big data (PPS's with data bigger than \PhpOffice\PhpSpreadsheet\Shared\OLE::OLE_DATA_SIZE_SMALL).
     *
     * @param PPS[] $raList Reference to array of PPS's
     */
    private function save_big_data(int $i_st_blk, array &$ra_list): void
    {
        $FILE = $this->file_handle;
        // cycle through PPS's
        $i_count = count($ra_list);
        for ($i = 0; $i < $i_count; ++$i) {
            if ($ra_list[$i]->Type != OLE::OLE_PPS_TYPE_DIR) {
                $ra_list[$i]->Size = $ra_list[$i]->get_data_len();
                if ($ra_list[$i]->Size >= OLE::OLE_DATA_SIZE_SMALL || $ra_list[$i]->Type == OLE::OLE_PPS_TYPE_ROOT && isset($ra_list[$i]->_data)) {
                    fwrite($FILE, $ra_list[$i]->_data);
                    if ($ra_list[$i]->Size % $this->big_block_size) {
                        fwrite($FILE, str_repeat("\x00", $this->big_block_size - $ra_list[$i]->Size % $this->big_block_size));
                    }
                    // Set For PPS
                    $ra_list[$i]->start_block = $i_st_blk;
                    $i_st_blk += (int) floor($ra_list[$i]->Size / $this->big_block_size) + ($ra_list[$i]->Size % $this->big_block_size ? 1 : 0);
                }
            }
        }
    }
    /**
     * get small data (PPS's with data smaller than \PhpOffice\PhpSpreadsheet\Shared\OLE::OLE_DATA_SIZE_SMALL).
     *
     * @param PPS[] $raList Reference to array of PPS's
     */
    private function make_small_data(array &$ra_list): string
    {
        $s_res = '';
        $FILE = $this->file_handle;
        $i_sm_blk = 0;
        $i_count = count($ra_list);
        for ($i = 0; $i < $i_count; ++$i) {
            // Make SBD, small data string
            if ($ra_list[$i]->Type == OLE::OLE_PPS_TYPE_FILE) {
                if ($ra_list[$i]->Size <= 0) {
                    continue;
                }
                if ($ra_list[$i]->Size < OLE::OLE_DATA_SIZE_SMALL) {
                    $i_smb_cnt = (int) floor($ra_list[$i]->Size / $this->small_block_size) + ($ra_list[$i]->Size % $this->small_block_size ? 1 : 0);
                    // Add to SBD
                    $j_b = $i_smb_cnt - 1;
                    for ($j = 0; $j < $j_b; ++$j) {
                        fwrite($FILE, pack('V', $j + $i_sm_blk + 1));
                    }
                    fwrite($FILE, pack('V', -2));
                    // Add to Data String(this will be written for RootEntry)
                    $s_res .= $ra_list[$i]->_data;
                    if ($ra_list[$i]->Size % $this->small_block_size) {
                        $s_res .= str_repeat("\x00", $this->small_block_size - $ra_list[$i]->Size % $this->small_block_size);
                    }
                    // Set for PPS
                    $ra_list[$i]->start_block = $i_sm_blk;
                    $i_sm_blk += $i_smb_cnt;
                }
            }
        }
        $i_sb_cnt = floor($this->big_block_size / OLE::OLE_LONG_INT_SIZE);
        if ($i_sm_blk % $i_sb_cnt) {
            $i_b = $i_sb_cnt - $i_sm_blk % $i_sb_cnt;
            for ($i = 0; $i < $i_b; ++$i) {
                fwrite($FILE, pack('V', -1));
            }
        }
        return $s_res;
    }
    /**
     * Saves all the PPS's WKs.
     *
     * @param PPS[] $raList Reference to an array with all PPS's
     */
    private function save_pps(array &$ra_list): void
    {
        // Save each PPS WK
        $i_c = count($ra_list);
        for ($i = 0; $i < $i_c; ++$i) {
            fwrite($this->file_handle, $ra_list[$i]->get_pps_wk());
        }
        // Adjust for Block
        $i_cnt = count($ra_list);
        $i_b_cnt = $this->big_block_size / OLE::OLE_PPS_SIZE;
        if ($i_cnt % $i_b_cnt) {
            fwrite($this->file_handle, str_repeat("\x00", ($i_b_cnt - $i_cnt % $i_b_cnt) * OLE::OLE_PPS_SIZE));
        }
    }
    /**
     * Saving Big Block Depot.
     */
    private function save_bbd(int $i_sbd_size, int $i_bsize, int $i_pps_cnt): void
    {
        $FILE = $this->file_handle;
        // Calculate Basic Setting
        $i_bb_cnt = $this->big_block_size / OLE::OLE_LONG_INT_SIZE;
        $i1st_bd_l = ($this->big_block_size - 0x4c) / OLE::OLE_LONG_INT_SIZE;
        $i_bd_ex_l = 0;
        $i_all = $i_bsize + $i_pps_cnt + $i_sbd_size;
        $i_all_w = $i_all;
        $i_bd_cnt_w = floor($i_all_w / $i_bb_cnt) + ($i_all_w % $i_bb_cnt ? 1 : 0);
        $i_bd_cnt = floor(($i_all + $i_bd_cnt_w) / $i_bb_cnt) + (($i_all_w + $i_bd_cnt_w) % $i_bb_cnt ? 1 : 0);
        // Calculate BD count
        if ($i_bd_cnt > $i1st_bd_l) {
            while (1) {
                ++$i_bd_ex_l;
                ++$i_all_w;
                $i_bd_cnt_w = floor($i_all_w / $i_bb_cnt) + ($i_all_w % $i_bb_cnt ? 1 : 0);
                $i_bd_cnt = floor(($i_all_w + $i_bd_cnt_w) / $i_bb_cnt) + (($i_all_w + $i_bd_cnt_w) % $i_bb_cnt ? 1 : 0);
                if ($i_bd_cnt <= $i_bd_ex_l * $i_bb_cnt + $i1st_bd_l) {
                    break;
                }
            }
        }
        // Making BD
        // Set for SBD
        if ($i_sbd_size > 0) {
            for ($i = 0; $i < $i_sbd_size - 1; ++$i) {
                fwrite($FILE, pack('V', $i + 1));
            }
            fwrite($FILE, pack('V', -2));
        }
        // Set for B
        for ($i = 0; $i < $i_bsize - 1; ++$i) {
            fwrite($FILE, pack('V', $i + $i_sbd_size + 1));
        }
        fwrite($FILE, pack('V', -2));
        // Set for PPS
        for ($i = 0; $i < $i_pps_cnt - 1; ++$i) {
            fwrite($FILE, pack('V', $i + $i_sbd_size + $i_bsize + 1));
        }
        fwrite($FILE, pack('V', -2));
        // Set for BBD itself ( 0xFFFFFFFD : BBD)
        for ($i = 0; $i < $i_bd_cnt; ++$i) {
            fwrite($FILE, pack('V', 0xfffffffd));
        }
        // Set for ExtraBDList
        for ($i = 0; $i < $i_bd_ex_l; ++$i) {
            fwrite($FILE, pack('V', 0xfffffffc));
        }
        // Adjust for Block
        if (($i_all_w + $i_bd_cnt) % $i_bb_cnt) {
            $i_block = $i_bb_cnt - ($i_all_w + $i_bd_cnt) % $i_bb_cnt;
            for ($i = 0; $i < $i_block; ++$i) {
                fwrite($FILE, pack('V', -1));
            }
        }
        // Extra BDList
        if ($i_bd_cnt > $i1st_bd_l) {
            $i_n = 0;
            $i_nb = 0;
            for ($i = $i1st_bd_l; $i < $i_bd_cnt; $i++, ++$i_n) {
                if ($i_n >= $i_bb_cnt - 1) {
                    $i_n = 0;
                    ++$i_nb;
                    fwrite($FILE, pack('V', $i_all + $i_bd_cnt + $i_nb));
                }
                fwrite($FILE, pack('V', $i_bsize + $i_sbd_size + $i_pps_cnt + $i));
            }
            if (($i_bd_cnt - $i1st_bd_l) % ($i_bb_cnt - 1)) {
                $i_b = $i_bb_cnt - 1 - ($i_bd_cnt - $i1st_bd_l) % ($i_bb_cnt - 1);
                for ($i = 0; $i < $i_b; ++$i) {
                    fwrite($FILE, pack('V', -1));
                }
            }
            fwrite($FILE, pack('V', -2));
        }
    }
}