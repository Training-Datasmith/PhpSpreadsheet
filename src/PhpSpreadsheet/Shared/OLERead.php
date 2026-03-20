<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Shared;

use Php_Office\Php_Spreadsheet\Reader\Exception as ReaderException;
class Ole_Read
{
    private string $data = '';
    // Size of a sector = 512 bytes
    public const BIG_BLOCK_SIZE = 0x200;
    // Size of a short sector = 64 bytes
    public const SMALL_BLOCK_SIZE = 0x40;
    // Size of a directory entry always = 128 bytes
    public const PROPERTY_STORAGE_BLOCK_SIZE = 0x80;
    // Minimum size of a standard stream = 4096 bytes, streams smaller than this are stored as short streams
    public const SMALL_BLOCK_THRESHOLD = 0x1000;
    // header offsets
    public const NUM_BIG_BLOCK_DEPOT_BLOCKS_POS = 0x2c;
    public const ROOT_START_BLOCK_POS = 0x30;
    public const SMALL_BLOCK_DEPOT_BLOCK_POS = 0x3c;
    public const EXTENSION_BLOCK_POS = 0x44;
    public const NUM_EXTENSION_BLOCK_POS = 0x48;
    public const BIG_BLOCK_DEPOT_BLOCKS_POS = 0x4c;
    // property storage offsets (directory offsets)
    public const SIZE_OF_NAME_POS = 0x40;
    public const TYPE_POS = 0x42;
    public const START_BLOCK_POS = 0x74;
    public const SIZE_POS = 0x78;
    public ?int $wrkbook = null;
    public ?int $summary_information = null;
    public ?int $document_summary_information = null;
    private int $num_big_block_depot_blocks;
    private int $root_start_block;
    private int $sbd_start_block;
    private int $extension_block;
    private int $num_extension_blocks;
    private string $big_block_chain;
    private string $small_block_chain;
    private string $entry;
    private int $rootentry;
    /** @var mixed[][] */
    private array $props = [];
    /**
     * Read the file.
     */
    public function read(string $filename): void
    {
        File::assert_file($filename);
        // Get the file identifier
        // Don't bother reading the whole file until we know it's a valid OLE file
        $this->data = (string) file_get_contents($filename, false, null, 0, 8);
        // Check OLE identifier
        $identifier_ole = pack('CCCCCCCC', 0xd0, 0xcf, 0x11, 0xe0, 0xa1, 0xb1, 0x1a, 0xe1);
        if ($this->data != $identifier_ole) {
            throw new Reader_Exception('The filename ' . $filename . ' is not recognised as an OLE file');
        }
        // Get the file data
        $this->data = (string) file_get_contents($filename);
        // Total number of sectors used for the SAT
        $this->num_big_block_depot_blocks = self::get_int4d($this->data, self::NUM_BIG_BLOCK_DEPOT_BLOCKS_POS);
        // SecID of the first sector of the directory stream
        $this->root_start_block = self::get_int4d($this->data, self::ROOT_START_BLOCK_POS);
        // SecID of the first sector of the SSAT (or -2 if not extant)
        $this->sbd_start_block = self::get_int4d($this->data, self::SMALL_BLOCK_DEPOT_BLOCK_POS);
        // SecID of the first sector of the MSAT (or -2 if no additional sectors are used)
        $this->extension_block = self::get_int4d($this->data, self::EXTENSION_BLOCK_POS);
        // Total number of sectors used by MSAT
        $this->num_extension_blocks = self::get_int4d($this->data, self::NUM_EXTENSION_BLOCK_POS);
        $big_block_depot_blocks = [];
        $pos = self::BIG_BLOCK_DEPOT_BLOCKS_POS;
        $bbd_blocks = $this->num_big_block_depot_blocks;
        if ($this->num_extension_blocks !== 0) {
            $bbd_blocks = (self::BIG_BLOCK_SIZE - self::BIG_BLOCK_DEPOT_BLOCKS_POS) / 4;
        }
        for ($i = 0; $i < $bbd_blocks; ++$i) {
            $big_block_depot_blocks[$i] = self::get_int4d($this->data, $pos);
            $pos += 4;
        }
        for ($j = 0; $j < $this->num_extension_blocks; ++$j) {
            $pos = ($this->extension_block + 1) * self::BIG_BLOCK_SIZE;
            $blocks_to_read = min($this->num_big_block_depot_blocks - $bbd_blocks, self::BIG_BLOCK_SIZE / 4 - 1);
            for ($i = $bbd_blocks; $i < $bbd_blocks + $blocks_to_read; ++$i) {
                $big_block_depot_blocks[$i] = self::get_int4d($this->data, $pos);
                $pos += 4;
            }
            $bbd_blocks += $blocks_to_read;
            if ($bbd_blocks < $this->num_big_block_depot_blocks) {
                $this->extension_block = self::get_int4d($this->data, $pos);
            }
        }
        $pos = 0;
        $this->big_block_chain = '';
        $bbs = self::BIG_BLOCK_SIZE / 4;
        for ($i = 0; $i < $this->num_big_block_depot_blocks; ++$i) {
            $pos = ($big_block_depot_blocks[$i] + 1) * self::BIG_BLOCK_SIZE;
            $this->big_block_chain .= substr($this->data, $pos, 4 * $bbs);
            $pos += 4 * $bbs;
        }
        $sbd_block = $this->sbd_start_block;
        $this->small_block_chain = '';
        while ($sbd_block != -2) {
            $pos = ($sbd_block + 1) * self::BIG_BLOCK_SIZE;
            $this->small_block_chain .= substr($this->data, $pos, 4 * $bbs);
            $pos += 4 * $bbs;
            $sbd_block = self::get_int4d($this->big_block_chain, $sbd_block * 4);
        }
        // read the directory stream
        $block = $this->root_start_block;
        $this->entry = $this->read_data($block);
        $this->read_property_sets();
    }
    /**
     * Extract binary stream data.
     */
    public function get_stream(?int $stream): ?string
    {
        if ($stream === null) {
            return null;
        }
        $stream_data = '';
        if ($this->props[$stream]['size'] < self::SMALL_BLOCK_THRESHOLD) {
            /** @var int */
            $temp = $this->props[$this->rootentry]['startBlock'];
            $rootdata = $this->read_data($temp);
            /** @var int */
            $block = $this->props[$stream]['startBlock'];
            while ($block != -2) {
                $pos = $block * self::SMALL_BLOCK_SIZE;
                $stream_data .= substr($rootdata, $pos, self::SMALL_BLOCK_SIZE);
                $block = self::get_int4d($this->small_block_chain, $block * 4);
            }
            return $stream_data;
        }
        /** @var int */
        $temp = $this->props[$stream]['size'];
        $num_blocks = $temp / self::BIG_BLOCK_SIZE;
        if ($temp % self::BIG_BLOCK_SIZE != 0) {
            ++$num_blocks;
        }
        if ($num_blocks == 0) {
            return '';
        }
        /** @var int */
        $block = $this->props[$stream]['startBlock'];
        while ($block != -2) {
            $pos = ($block + 1) * self::BIG_BLOCK_SIZE;
            $stream_data .= substr($this->data, $pos, self::BIG_BLOCK_SIZE);
            $block = self::get_int4d($this->big_block_chain, $block * 4);
        }
        return $stream_data;
    }
    /**
     * Read a standard stream (by joining sectors using information from SAT).
     *
     * @param int $block Sector ID where the stream starts
     *
     * @return string Data for standard stream
     */
    private function read_data(int $block): string
    {
        $data = '';
        while ($block != -2) {
            $pos = ($block + 1) * self::BIG_BLOCK_SIZE;
            $data .= substr($this->data, $pos, self::BIG_BLOCK_SIZE);
            $block = self::get_int4d($this->big_block_chain, $block * 4);
        }
        return $data;
    }
    /**
     * Read entries in the directory stream.
     */
    private function read_property_sets(): void
    {
        $offset = 0;
        // loop through entries, each entry is 128 bytes
        $entry_len = strlen($this->entry);
        while ($offset < $entry_len) {
            // entry data (128 bytes)
            $d = substr($this->entry, $offset, self::PROPERTY_STORAGE_BLOCK_SIZE);
            // size in bytes of name
            $name_size = ord($d[self::SIZE_OF_NAME_POS]) | ord($d[self::SIZE_OF_NAME_POS + 1]) << 8;
            // type of entry
            $type = ord($d[self::TYPE_POS]);
            // sectorID of first sector or short sector, if this entry refers to a stream (the case with workbook)
            // sectorID of first sector of the short-stream container stream, if this entry is root entry
            $start_block = self::get_int4d($d, self::START_BLOCK_POS);
            $size = self::get_int4d($d, self::SIZE_POS);
            $name = str_replace("\x00", '', substr($d, 0, $name_size));
            $this->props[] = ['name' => $name, 'type' => $type, 'startBlock' => $start_block, 'size' => $size];
            // tmp helper to simplify checks
            $up_name = strtoupper($name);
            // Workbook directory entry (BIFF5 uses Book, BIFF8 uses Workbook)
            if ($up_name === 'WORKBOOK' || $up_name === 'BOOK') {
                $this->wrkbook = count($this->props) - 1;
            } elseif ($up_name === 'ROOT ENTRY' || $up_name === 'R') {
                // Root entry
                $this->rootentry = count($this->props) - 1;
            }
            // Summary information
            if ($name == chr(5) . 'SummaryInformation') {
                $this->summary_information = count($this->props) - 1;
            }
            // Additional Document Summary information
            if ($name == chr(5) . 'DocumentSummaryInformation') {
                $this->document_summary_information = count($this->props) - 1;
            }
            $offset += self::PROPERTY_STORAGE_BLOCK_SIZE;
        }
    }
    /**
     * Read 4 bytes of data at specified position.
     */
    private static function get_int4d(string $data, int $pos): int
    {
        if ($pos < 0) {
            // Invalid position
            throw new Reader_Exception('Parameter pos=' . $pos . ' is invalid.');
        }
        $len = strlen($data);
        if ($len < $pos + 4) {
            $data .= str_repeat("\x00", $pos + 4 - $len);
        }
        // FIX: represent numbers correctly on 64-bit system
        // http://sourceforge.net/tracker/index.php?func=detail&aid=1487372&group_id=99160&atid=623334
        // Changed by Andreas Rehm 2006 to ensure correct result of the <<24 block on 32 and 64bit systems
        $_or_24 = ord($data[$pos + 3]);
        if ($_or_24 >= 128) {
            // negative number
            $_ord_24 = -abs(256 - $_or_24 << 24);
        } else {
            $_ord_24 = ($_or_24 & 127) << 24;
        }
        return ord($data[$pos]) | ord($data[$pos + 1]) << 8 | ord($data[$pos + 2]) << 16 | $_ord_24;
    }
}