<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Shared;

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
use Php_Office\Php_Spreadsheet\Exception;
use Php_Office\Php_Spreadsheet\Reader\Exception as ReaderException;
use Php_Office\Php_Spreadsheet\Shared\OLE\Chained_Block_Stream;
use Php_Office\Php_Spreadsheet\Shared\OLE\PPS\Root;
/*
 * Array for storing OLE instances that are accessed from
 * OLE_ChainedBlockStream::stream_open().
 *
 * @var array
 */
$GLOBALS['_OLE_INSTANCES'] = [];
/**
 * OLE package base class.
 *
 * @author   Xavier Noguer <xnoguer@php.net>
 * @author   Christian Schmidt <schmidt@php.net>
 */
class OLE
{
    public const OLE_PPS_TYPE_ROOT = 5;
    public const OLE_PPS_TYPE_DIR = 1;
    public const OLE_PPS_TYPE_FILE = 2;
    public const OLE_DATA_SIZE_SMALL = 0x1000;
    public const OLE_LONG_INT_SIZE = 4;
    public const OLE_PPS_SIZE = 0x80;
    /**
     * The file handle for reading an OLE container.
     *
     * @var resource
     */
    public $_file_handle;
    /**
     * Array of PPS's found on the OLE container.
     *
     * @var array<OLE\PPS|OLE\PPS\File|Root>
     */
    public array $_list = [];
    /**
     * Root directory of OLE container.
     */
    public Root $root;
    /**
     * Big Block Allocation Table.
     *
     * @var mixed[] (blockId => nextBlockId)
     */
    public array $bbat;
    /**
     * Short Block Allocation Table.
     *
     * @var mixed[] (blockId => nextBlockId)
     */
    public array $sbat;
    /**
     * Size of big blocks. This is usually 512.
     *
     * @var int<1, max> number of octets per block
     */
    public int $big_block_size;
    /**
     * Size of small blocks. This is usually 64.
     *
     * @var int number of octets per block
     */
    public int $small_block_size;
    /**
     * Threshold for big blocks.
     */
    public int $big_block_threshold;
    /**
     * Reads an OLE container from the contents of the file given.
     *
     * @acces public
     *
     * @return bool true on success, PEAR_Error on failure
     */
    public function read(string $filename): bool
    {
        $fh = @fopen($filename, 'rb');
        if ($fh === false) {
            throw new Reader_Exception("Can't open file {$filename}");
        }
        $this->_file_handle = $fh;
        $signature = fread($fh, 8);
        if ("\xd0\xcf\x11ࡱ\x1a\xe1" != $signature) {
            throw new Reader_Exception("File doesn't seem to be an OLE container.");
        }
        fseek($fh, 28);
        if (fread($fh, 2) != "\xfe\xff") {
            // This shouldn't be a problem in practice
            throw new Reader_Exception('Only Little-Endian encoding is supported.');
        }
        // Size of blocks and short blocks in bytes
        /** @var int<1, max> */
        $temp = 2 ** self::read_int2($fh);
        $this->big_block_size = $temp;
        $this->small_block_size = 2 ** self::read_int2($fh);
        // Skip UID, revision number and version number
        fseek($fh, 44);
        // Number of blocks in Big Block Allocation Table
        $bbat_block_count = self::read_int4($fh);
        // Root chain 1st block
        $directory_first_block_id = self::read_int4($fh);
        // Skip unused bytes
        fseek($fh, 56);
        // Streams shorter than this are stored using small blocks
        $this->big_block_threshold = self::read_int4($fh);
        // Block id of first sector in Short Block Allocation Table
        $sbat_first_block_id = self::read_int4($fh);
        // Number of blocks in Short Block Allocation Table
        $sbbat_block_count = self::read_int4($fh);
        // Block id of first sector in Master Block Allocation Table
        $mbat_first_block_id = self::read_int4($fh);
        // Number of blocks in Master Block Allocation Table
        $mbbat_block_count = self::read_int4($fh);
        $this->bbat = [];
        // Remaining 4 * 109 bytes of current block is beginning of Master
        // Block Allocation Table
        $mbat_blocks = [];
        for ($i = 0; $i < 109; ++$i) {
            $mbat_blocks[] = self::read_int4($fh);
        }
        // Read rest of Master Block Allocation Table (if any is left)
        $pos = $this->get_block_offset($mbat_first_block_id);
        for ($i = 0; $i < $mbbat_block_count; ++$i) {
            fseek($fh, $pos);
            for ($j = 0; $j < $this->big_block_size / 4 - 1; ++$j) {
                $mbat_blocks[] = self::read_int4($fh);
            }
            // Last block id in each block points to next block
            $pos = $this->get_block_offset(self::read_int4($fh));
        }
        // Read Big Block Allocation Table according to chain specified by $mbatBlocks
        for ($i = 0; $i < $bbat_block_count; ++$i) {
            $pos = $this->get_block_offset($mbat_blocks[$i]);
            fseek($fh, $pos);
            for ($j = 0; $j < $this->big_block_size / 4; ++$j) {
                $this->bbat[] = self::read_int4($fh);
            }
        }
        // Read short block allocation table (SBAT)
        $this->sbat = [];
        $short_block_count = $sbbat_block_count * $this->big_block_size / 4;
        $sbat_fh = $this->get_stream($sbat_first_block_id);
        for ($block_id = 0; $block_id < $short_block_count; ++$block_id) {
            $this->sbat[$block_id] = self::read_int4($sbat_fh);
        }
        fclose($sbat_fh);
        $this->read_pps_wks($directory_first_block_id);
        return true;
    }
    /**
     * @param int $blockId byte offset from beginning of file
     */
    public function get_block_offset(int $block_id): int
    {
        return 512 + $block_id * $this->big_block_size;
    }
    /**
     * Returns a stream for use with fread() etc. External callers should
     * use \PhpOffice\PhpSpreadsheet\Shared\OLE\PPS\File::getStream().
     *
     * @param int|OLE\PPS $blockIdOrPps block id or PPS
     *
     * @return resource read-only stream
     */
    public function get_stream($block_id_or_pps)
    {
        static $is_registered = false;
        if (!$is_registered) {
            stream_wrapper_register('ole-chainedblockstream', Chained_Block_Stream::class);
            $is_registered = true;
        }
        // Store current instance in global array, so that it can be accessed
        // in OLE_ChainedBlockStream::stream_open().
        // Object is removed from self::$instances in OLE_Stream::close().
        $GLOBALS['_OLE_INSTANCES'][] = $this;
        //* @phpstan-ignore-line
        $keys = array_keys($GLOBALS['_OLE_INSTANCES']);
        //* @phpstan-ignore-line
        $instance_id = end($keys);
        $path = 'ole-chainedblockstream://oleInstanceId=' . $instance_id;
        if ($block_id_or_pps instanceof OLE\PPS) {
            $path .= '&blockId=' . $block_id_or_pps->start_block;
            $path .= '&size=' . $block_id_or_pps->Size;
        } else {
            $path .= '&blockId=' . $block_id_or_pps;
        }
        return fopen($path, 'rb') ?: throw new Exception("Unable to open stream {$path}");
    }
    /**
     * Reads a signed char.
     *
     * @param resource $fileHandle file handle
     */
    private static function read_int1($file_handle): int
    {
        [, $tmp] = unpack('c', fread($file_handle, 1) ?: '') ?: [0, 0];
        /** @var int $tmp */
        return $tmp;
    }
    /**
     * Reads an unsigned short (2 octets).
     *
     * @param resource $fileHandle file handle
     */
    private static function read_int2($file_handle): int
    {
        [, $tmp] = unpack('v', fread($file_handle, 2) ?: '') ?: [0, 0];
        /** @var int $tmp */
        return $tmp;
    }
    private const SIGNED_4OCTET_LIMIT = 2147483648;
    private const SIGNED_4OCTET_SUBTRACT = 2 * self::SIGNED_4OCTET_LIMIT;
    /**
     * Reads long (4 octets), interpreted as if signed on 32-bit system.
     *
     * @param resource $fileHandle file handle
     */
    private static function read_int4($file_handle): int
    {
        [, $tmp] = unpack('V', fread($file_handle, 4) ?: '') ?: [0, 0];
        /** @var int $tmp */
        if ($tmp >= self::SIGNED_4OCTET_LIMIT) {
            $tmp -= self::SIGNED_4OCTET_SUBTRACT;
        }
        return $tmp;
    }
    /**
     * Gets information about all PPS's on the OLE container from the PPS WK's
     * creates an OLE_PPS object for each one.
     *
     * @param int $blockId the block id of the first block
     *
     * @return bool true on success, PEAR_Error on failure
     */
    public function read_pps_wks(int $block_id): bool
    {
        $fh = $this->get_stream($block_id);
        for ($pos = 0; true; $pos += 128) {
            fseek($fh, $pos, SEEK_SET);
            $name_utf16 = (string) fread($fh, 64);
            $name_length = self::read_int2($fh);
            $name_utf16 = substr($name_utf16, 0, $name_length - 2);
            // Simple conversion from UTF-16LE to ISO-8859-1
            $name = str_replace("\x00", '', $name_utf16);
            $type = self::read_int1($fh);
            switch ($type) {
                case self::OLE_PPS_TYPE_ROOT:
                    $pps = new Root(null, null, []);
                    $this->root = $pps;
                    break;
                case self::OLE_PPS_TYPE_DIR:
                    $pps = new OLE\PPS(null, null, null, null, null, null, null, null, null, []);
                    break;
                case self::OLE_PPS_TYPE_FILE:
                    $pps = new OLE\PPS\File($name);
                    break;
                default:
                    throw new Exception('Unsupported PPS type');
            }
            fseek($fh, 1, SEEK_CUR);
            $pps->Type = $type;
            $pps->Name = $name;
            $pps->prev_pps = self::read_int4($fh);
            $pps->next_pps = self::read_int4($fh);
            $pps->dir_pps = self::read_int4($fh);
            fseek($fh, 20, SEEK_CUR);
            $pps->Time1st = self::ole2local_date((string) fread($fh, 8));
            $pps->Time2nd = self::ole2local_date((string) fread($fh, 8));
            $pps->start_block = self::read_int4($fh);
            $pps->Size = self::read_int4($fh);
            $pps->No = count($this->_list);
            $this->_list[] = $pps;
            // check if the PPS tree (starting from root) is complete
            if (isset($this->root) && $this->pps_tree_complete($this->root->No)) {
                break;
            }
        }
        fclose($fh);
        // Initialize $pps->children on directories
        foreach ($this->_list as $pps) {
            if ($pps->Type == self::OLE_PPS_TYPE_DIR || $pps->Type == self::OLE_PPS_TYPE_ROOT) {
                $nos = [$pps->dir_pps];
                $pps->children = [];
                while (!empty($nos)) {
                    $no = array_pop($nos);
                    if ($no != -1) {
                        $child_pps = $this->_list[$no];
                        $nos[] = $child_pps->prev_pps;
                        $nos[] = $child_pps->next_pps;
                        $pps->children[] = $child_pps;
                    }
                }
            }
        }
        return true;
    }
    /**
     * It checks whether the PPS tree is complete (all PPS's read)
     * starting with the given PPS (not necessarily root).
     *
     * @param int $index The index of the PPS from which we are checking
     *
     * @return bool Whether the PPS tree for the given PPS is complete
     */
    private function pps_tree_complete(int $index): bool
    {
        if (!isset($this->_list[$index])) {
            return false;
        }
        $pps = $this->_list[$index];
        return ($pps->prev_pps == -1 || $this->pps_tree_complete($pps->prev_pps)) && ($pps->next_pps == -1 || $this->pps_tree_complete($pps->next_pps)) && ($pps->dir_pps == -1 || $this->pps_tree_complete($pps->dir_pps));
    }
    /**
     * Checks whether a PPS is a File PPS or not.
     * If there is no PPS for the index given, it will return false.
     *
     * @param int $index The index for the PPS
     *
     * @return bool true if it's a File PPS, false otherwise
     */
    public function is_file(int $index): bool
    {
        if (isset($this->_list[$index])) {
            return $this->_list[$index]->Type == self::OLE_PPS_TYPE_FILE;
        }
        return false;
    }
    /**
     * Checks whether a PPS is a Root PPS or not.
     * If there is no PPS for the index given, it will return false.
     *
     * @param int $index the index for the PPS
     *
     * @return bool true if it's a Root PPS, false otherwise
     */
    public function is_root(int $index): bool
    {
        if (isset($this->_list[$index])) {
            return $this->_list[$index]->Type == self::OLE_PPS_TYPE_ROOT;
        }
        return false;
    }
    /**
     * Gives the total number of PPS's found in the OLE container.
     *
     * @return int The total number of PPS's found in the OLE container
     */
    public function pps_total(): int
    {
        return count($this->_list);
    }
    /**
     * Gets data from a PPS
     * If there is no PPS for the index given, it will return an empty string.
     *
     * @param int $index The index for the PPS
     * @param int $position The position from which to start reading
     *                          (relative to the PPS)
     * @param int $length The amount of bytes to read (at most)
     *
     * @return string The binary string containing the data requested
     *
     * @see OLE_PPS_File::getStream()
     */
    public function get_data(int $index, int $position, int $length): string
    {
        // if position is not valid return empty string
        if (!isset($this->_list[$index]) || $position >= $this->_list[$index]->Size || $position < 0) {
            return '';
        }
        $fh = $this->get_stream($this->_list[$index]);
        $data = (string) stream_get_contents($fh, $length, $position);
        fclose($fh);
        return $data;
    }
    /**
     * Gets the data length from a PPS
     * If there is no PPS for the index given, it will return 0.
     *
     * @param int $index The index for the PPS
     *
     * @return int The amount of bytes in data the PPS has
     */
    public function get_data_length(int $index): int
    {
        if (isset($this->_list[$index])) {
            return $this->_list[$index]->Size;
        }
        return 0;
    }
    /**
     * Utility function to transform ASCII text to Unicode.
     *
     * @param string $ascii The ASCII string to transform
     *
     * @return string The string in Unicode
     */
    public static function asc_to_ucs(string $ascii): string
    {
        $rawname = '';
        $i_max = strlen($ascii);
        for ($i = 0; $i < $i_max; ++$i) {
            $rawname .= $ascii[$i] . "\x00";
        }
        return $rawname;
    }
    /**
     * Utility function
     * Returns a string for the OLE container with the date given.
     *
     * @param float|int $date A timestamp
     *
     * @return string The string for the OLE container
     */
    public static function local_date_to_ole($date): string
    {
        if (!$date) {
            return "\x00\x00\x00\x00\x00\x00\x00\x00";
        }
        $date_time = Date::date_time_from_timestamp("{$date}");
        // days from 1-1-1601 until the beginning of UNIX era
        $days = 134774;
        // calculate seconds
        $big_date = $days * 24 * 3600 + (float) $date_time->format('U');
        // multiply just to make MS happy
        $big_date *= 10000000;
        // Make HEX string
        $res = '';
        $factor = 2 ** 56;
        while ($factor >= 1) {
            $hex = (int) floor($big_date / $factor);
            $res = pack('c', $hex) . $res;
            $big_date = fmod($big_date, $factor);
            $factor /= 256;
        }
        return $res;
    }
    /**
     * Returns a timestamp from an OLE container's date.
     *
     * @param string $oleTimestamp A binary string with the encoded date
     *
     * @return float|int The Unix timestamp corresponding to the string
     */
    public static function ole2local_date(string $ole_timestamp): float|int
    {
        if (strlen($ole_timestamp) != 8) {
            throw new Reader_Exception('Expecting 8 byte string');
        }
        // convert to units of 100 ns since 1601:
        /** @var int[] */
        $unpacked_timestamp = unpack('v4', $ole_timestamp) ?: [];
        $timestamp_high = (float) $unpacked_timestamp[4] * 65536 + (float) $unpacked_timestamp[3];
        $timestamp_low = (float) $unpacked_timestamp[2] * 65536 + (float) $unpacked_timestamp[1];
        // translate to seconds since 1601:
        $timestamp_high /= 10000000;
        $timestamp_low /= 10000000;
        // days from 1601 to 1970:
        $days = 134774;
        // translate to seconds since 1970:
        $unix_timestamp = floor(65536.0 * 65536.0 * $timestamp_high + $timestamp_low - $days * 24 * 3600 + 0.5);
        return Int_Or_Float::evaluate($unix_timestamp);
    }
}