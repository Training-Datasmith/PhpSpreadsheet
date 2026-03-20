<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Xls;

use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Reader\Xls;
use Php_Office\Php_Spreadsheet\Shared\Escher\Dg_Container;
use Php_Office\Php_Spreadsheet\Shared\Escher\Dg_Container\Spgr_Container;
use Php_Office\Php_Spreadsheet\Shared\Escher\Dg_Container\Spgr_Container\Sp_Container;
use Php_Office\Php_Spreadsheet\Shared\Escher\Dgg_Container;
use Php_Office\Php_Spreadsheet\Shared\Escher\Dgg_Container\Bstore_Container;
use Php_Office\Php_Spreadsheet\Shared\Escher\Dgg_Container\Bstore_Container\BSE;
use Php_Office\Php_Spreadsheet\Shared\Escher\Dgg_Container\Bstore_Container\BSE\Blip;
/**
 * @template T of BSE|BstoreContainer|DgContainer|DggContainer|\PhpOffice\PhpSpreadsheet\Shared\Escher|SpContainer|SpgrContainer
 */
class Escher
{
    public const DGGCONTAINER = 0xf000;
    public const BSTORECONTAINER = 0xf001;
    public const DGCONTAINER = 0xf002;
    public const SPGRCONTAINER = 0xf003;
    public const SPCONTAINER = 0xf004;
    public const DGG = 0xf006;
    public const BSE = 0xf007;
    public const DG = 0xf008;
    public const SPGR = 0xf009;
    public const SP = 0xf00a;
    public const OPT = 0xf00b;
    public const CLIENTTEXTBOX = 0xf00d;
    public const CLIENTANCHOR = 0xf010;
    public const CLIENTDATA = 0xf011;
    public const BLIPJPEG = 0xf01d;
    public const BLIPPNG = 0xf01e;
    public const SPLITMENUCOLORS = 0xf11e;
    public const TERTIARYOPT = 0xf122;
    /**
     * Escher stream data (binary).
     */
    private string $data;
    /**
     * Size in bytes of the Escher stream data.
     */
    private int $data_size;
    /**
     * Current position of stream pointer in Escher stream data.
     */
    private int $pos;
    /**
     * Create a new Escher instance.
     *
     * @param T $object
     */
    public function __construct(
        /**
         * The object to be returned by the reader. Modified during load.
         */
        private readonly BSE|Bstore_Container|Dg_Container|Dgg_Container|\Php_Office\Php_Spreadsheet\Shared\Escher|Sp_Container|Spgr_Container $object
    )
    {
    }
    private const WHICH_ROUTINE = [self::DGGCONTAINER => 'readDggContainer', self::DGG => 'readDgg', self::BSTORECONTAINER => 'readBstoreContainer', self::BSE => 'readBSE', self::BLIPJPEG => 'readBlipJPEG', self::BLIPPNG => 'readBlipPNG', self::OPT => 'readOPT', self::TERTIARYOPT => 'readTertiaryOPT', self::SPLITMENUCOLORS => 'readSplitMenuColors', self::DGCONTAINER => 'readDgContainer', self::DG => 'readDg', self::SPGRCONTAINER => 'readSpgrContainer', self::SPCONTAINER => 'readSpContainer', self::SPGR => 'readSpgr', self::SP => 'readSp', self::CLIENTTEXTBOX => 'readClientTextbox', self::CLIENTANCHOR => 'readClientAnchor', self::CLIENTDATA => 'readClientData'];
    /**
     * Load Escher stream data. May be a partial Escher stream.
     *
     * @return T
     */
    public function load(string $data): BSE|Bstore_Container|Dg_Container|Dgg_Container|\Php_Office\Php_Spreadsheet\Shared\Escher|Sp_Container|Spgr_Container
    {
        $this->data = $data;
        // total byte size of Excel data (workbook global substream + sheet substreams)
        $this->data_size = strlen($this->data);
        $this->pos = 0;
        // Parse Escher stream
        while ($this->pos < $this->data_size) {
            // offset: 2; size: 2: Record Type
            $fbt = Xls::get_u_int2d($this->data, $this->pos + 2);
            $routine = self::WHICH_ROUTINE[$fbt] ?? 'readDefault';
            if (method_exists($this, $routine)) {
                $this->{$routine}();
            }
        }
        return $this->object;
    }
    /**
     * Read a generic record.
     */
    private function read_default(): void
    {
        // offset 0; size: 2; recVer and recInstance
        //$verInstance = Xls::getUInt2d($this->data, $this->pos);
        // offset: 2; size: 2: Record Type
        //$fbt = Xls::getUInt2d($this->data, $this->pos + 2);
        // bit: 0-3; mask: 0x000F; recVer
        //$recVer = (0x000F & $verInstance) >> 0;
        $length = Xls::get_int4d($this->data, $this->pos + 4);
        //$recordData = substr($this->data, $this->pos + 8, $length);
        // move stream pointer to next record
        $this->pos += 8 + $length;
    }
    /**
     * Read DggContainer record (Drawing Group Container).
     */
    private function read_dgg_container(): void
    {
        $length = Xls::get_int4d($this->data, $this->pos + 4);
        $record_data = substr($this->data, $this->pos + 8, $length);
        // move stream pointer to next record
        $this->pos += 8 + $length;
        // record is a container, read contents
        $dgg_container = new Dgg_Container();
        $this->apply_attribute('setDggContainer', $dgg_container);
        $reader = new self($dgg_container);
        $reader->load($record_data);
    }
    /**
     * Read Dgg record (Drawing Group).
     */
    private function read_dgg(): void
    {
        $length = Xls::get_int4d($this->data, $this->pos + 4);
        //$recordData = substr($this->data, $this->pos + 8, $length);
        // move stream pointer to next record
        $this->pos += 8 + $length;
    }
    /**
     * Read BstoreContainer record (Blip Store Container).
     */
    private function read_bstore_container(): void
    {
        $length = Xls::get_int4d($this->data, $this->pos + 4);
        $record_data = substr($this->data, $this->pos + 8, $length);
        // move stream pointer to next record
        $this->pos += 8 + $length;
        // record is a container, read contents
        $bstore_container = new Bstore_Container();
        $this->apply_attribute('setBstoreContainer', $bstore_container);
        $reader = new self($bstore_container);
        $reader->load($record_data);
    }
    /**
     * Read BSE record.
     */
    private function read_bse(): void
    {
        // offset: 0; size: 2; recVer and recInstance
        // bit: 4-15; mask: 0xFFF0; recInstance
        $rec_instance = (0xfff0 & Xls::get_u_int2d($this->data, $this->pos)) >> 4;
        $length = Xls::get_int4d($this->data, $this->pos + 4);
        $record_data = substr($this->data, $this->pos + 8, $length);
        // move stream pointer to next record
        $this->pos += 8 + $length;
        // add BSE to BstoreContainer
        $BSE = new BSE();
        $this->apply_attribute('addBSE', $BSE);
        $BSE->set_blip_type($rec_instance);
        // offset: 0; size: 1; btWin32 (MSOBLIPTYPE)
        //$btWin32 = ord($recordData[0]);
        // offset: 1; size: 1; btWin32 (MSOBLIPTYPE)
        //$btMacOS = ord($recordData[1]);
        // offset: 2; size: 16; MD4 digest
        //$rgbUid = substr($recordData, 2, 16);
        // offset: 18; size: 2; tag
        //$tag = Xls::getUInt2d($recordData, 18);
        // offset: 20; size: 4; size of BLIP in bytes
        //$size = Xls::getInt4d($recordData, 20);
        // offset: 24; size: 4; number of references to this BLIP
        //$cRef = Xls::getInt4d($recordData, 24);
        // offset: 28; size: 4; MSOFO file offset
        //$foDelay = Xls::getInt4d($recordData, 28);
        // offset: 32; size: 1; unused1
        //$unused1 = ord($recordData[32]);
        // offset: 33; size: 1; size of nameData in bytes (including null terminator)
        $cb_name = ord($record_data[33]);
        // offset: 34; size: 1; unused2
        //$unused2 = ord($recordData[34]);
        // offset: 35; size: 1; unused3
        //$unused3 = ord($recordData[35]);
        // offset: 36; size: $cbName; nameData
        //$nameData = substr($recordData, 36, $cbName);
        // offset: 36 + $cbName, size: var; the BLIP data
        $blip_data = substr($record_data, 36 + $cb_name);
        // record is a container, read contents
        $reader = new self($BSE);
        $reader->load($blip_data);
    }
    /**
     * Read BlipJPEG record. Holds raw JPEG image data.
     */
    private function read_blip_jpeg(): void
    {
        // offset: 0; size: 2; recVer and recInstance
        // bit: 4-15; mask: 0xFFF0; recInstance
        $rec_instance = (0xfff0 & Xls::get_u_int2d($this->data, $this->pos)) >> 4;
        $length = Xls::get_int4d($this->data, $this->pos + 4);
        $record_data = substr($this->data, $this->pos + 8, $length);
        // move stream pointer to next record
        $this->pos += 8 + $length;
        $pos = 0;
        // offset: 0; size: 16; rgbUid1 (MD4 digest of)
        //$rgbUid1 = substr($recordData, 0, 16);
        $pos += 16;
        // offset: 16; size: 16; rgbUid2 (MD4 digest), only if $recInstance = 0x46B or 0x6E3
        if (in_array($rec_instance, [0x46b, 0x6e3])) {
            //$rgbUid2 = substr($recordData, 16, 16);
            $pos += 16;
        }
        // offset: var; size: 1; tag
        //$tag = ord($recordData[$pos]);
        ++$pos;
        // offset: var; size: var; the raw image data
        $data = substr($record_data, $pos);
        $blip = new Blip();
        $blip->set_data($data);
        $this->apply_attribute('setBlip', $blip);
    }
    /**
     * Read BlipPNG record. Holds raw PNG image data.
     */
    private function read_blip_png(): void
    {
        // offset: 0; size: 2; recVer and recInstance
        // bit: 4-15; mask: 0xFFF0; recInstance
        $rec_instance = (0xfff0 & Xls::get_u_int2d($this->data, $this->pos)) >> 4;
        $length = Xls::get_int4d($this->data, $this->pos + 4);
        $record_data = substr($this->data, $this->pos + 8, $length);
        // move stream pointer to next record
        $this->pos += 8 + $length;
        $pos = 0;
        // offset: 0; size: 16; rgbUid1 (MD4 digest of)
        //$rgbUid1 = substr($recordData, 0, 16);
        $pos += 16;
        // offset: 16; size: 16; rgbUid2 (MD4 digest), only if $recInstance = 0x46B or 0x6E3
        if ($rec_instance == 0x6e1) {
            //$rgbUid2 = substr($recordData, 16, 16);
            $pos += 16;
        }
        // offset: var; size: 1; tag
        //$tag = ord($recordData[$pos]);
        ++$pos;
        // offset: var; size: var; the raw image data
        $data = substr($record_data, $pos);
        $blip = new Blip();
        $blip->set_data($data);
        $this->apply_attribute('setBlip', $blip);
    }
    /**
     * Read OPT record. This record may occur within DggContainer record or SpContainer.
     */
    private function read_opt(): void
    {
        // offset: 0; size: 2; recVer and recInstance
        // bit: 4-15; mask: 0xFFF0; recInstance
        $rec_instance = (0xfff0 & Xls::get_u_int2d($this->data, $this->pos)) >> 4;
        $length = Xls::get_int4d($this->data, $this->pos + 4);
        $record_data = substr($this->data, $this->pos + 8, $length);
        // move stream pointer to next record
        $this->pos += 8 + $length;
        $this->read_office_art_rgfopte($record_data, $rec_instance);
    }
    /**
     * Read TertiaryOPT record.
     */
    private function read_tertiary_opt(): void
    {
        // offset: 0; size: 2; recVer and recInstance
        // bit: 4-15; mask: 0xFFF0; recInstance
        //$recInstance = (0xFFF0 & Xls::getUInt2d($this->data, $this->pos)) >> 4;
        $length = Xls::get_int4d($this->data, $this->pos + 4);
        //$recordData = substr($this->data, $this->pos + 8, $length);
        // move stream pointer to next record
        $this->pos += 8 + $length;
    }
    /**
     * Read SplitMenuColors record.
     */
    private function read_split_menu_colors(): void
    {
        $length = Xls::get_int4d($this->data, $this->pos + 4);
        //$recordData = substr($this->data, $this->pos + 8, $length);
        // move stream pointer to next record
        $this->pos += 8 + $length;
    }
    /**
     * Read DgContainer record (Drawing Container).
     */
    private function read_dg_container(): void
    {
        $length = Xls::get_int4d($this->data, $this->pos + 4);
        $record_data = substr($this->data, $this->pos + 8, $length);
        // move stream pointer to next record
        $this->pos += 8 + $length;
        // record is a container, read contents
        $dg_container = new Dg_Container();
        $this->apply_attribute('setDgContainer', $dg_container);
        $reader = new self($dg_container);
        $reader->load($record_data);
    }
    /**
     * Read Dg record (Drawing).
     */
    private function read_dg(): void
    {
        $length = Xls::get_int4d($this->data, $this->pos + 4);
        //$recordData = substr($this->data, $this->pos + 8, $length);
        // move stream pointer to next record
        $this->pos += 8 + $length;
    }
    /**
     * Read SpgrContainer record (Shape Group Container).
     */
    private function read_spgr_container(): void
    {
        // context is either context DgContainer or SpgrContainer
        $length = Xls::get_int4d($this->data, $this->pos + 4);
        $record_data = substr($this->data, $this->pos + 8, $length);
        // move stream pointer to next record
        $this->pos += 8 + $length;
        // record is a container, read contents
        $spgr_container = new Spgr_Container();
        if ($this->object instanceof Dg_Container) {
            // DgContainer
            $this->object->set_spgr_container($spgr_container);
        } elseif ($this->object instanceof Spgr_Container) {
            // SpgrContainer
            $this->object->add_child($spgr_container);
        }
        $reader = new self($spgr_container);
        $reader->load($record_data);
    }
    /**
     * Read SpContainer record (Shape Container).
     */
    private function read_sp_container(): void
    {
        $length = Xls::get_int4d($this->data, $this->pos + 4);
        $record_data = substr($this->data, $this->pos + 8, $length);
        // add spContainer to spgrContainer
        $sp_container = new Sp_Container();
        $this->apply_attribute('addChild', $sp_container);
        // move stream pointer to next record
        $this->pos += 8 + $length;
        // record is a container, read contents
        $reader = new self($sp_container);
        $reader->load($record_data);
    }
    /**
     * Read Spgr record (Shape Group).
     */
    private function read_spgr(): void
    {
        $length = Xls::get_int4d($this->data, $this->pos + 4);
        //$recordData = substr($this->data, $this->pos + 8, $length);
        // move stream pointer to next record
        $this->pos += 8 + $length;
    }
    /**
     * Read Sp record (Shape).
     */
    private function read_sp(): void
    {
        // offset: 0; size: 2; recVer and recInstance
        // bit: 4-15; mask: 0xFFF0; recInstance
        //$recInstance = (0xFFF0 & Xls::getUInt2d($this->data, $this->pos)) >> 4;
        $length = Xls::get_int4d($this->data, $this->pos + 4);
        //$recordData = substr($this->data, $this->pos + 8, $length);
        // move stream pointer to next record
        $this->pos += 8 + $length;
    }
    /**
     * Read ClientTextbox record.
     */
    private function read_client_textbox(): void
    {
        // offset: 0; size: 2; recVer and recInstance
        // bit: 4-15; mask: 0xFFF0; recInstance
        //$recInstance = (0xFFF0 & Xls::getUInt2d($this->data, $this->pos)) >> 4;
        $length = Xls::get_int4d($this->data, $this->pos + 4);
        //$recordData = substr($this->data, $this->pos + 8, $length);
        // move stream pointer to next record
        $this->pos += 8 + $length;
    }
    /**
     * Read ClientAnchor record. This record holds information about where the shape is anchored in worksheet.
     */
    private function read_client_anchor(): void
    {
        $length = Xls::get_int4d($this->data, $this->pos + 4);
        $record_data = substr($this->data, $this->pos + 8, $length);
        // move stream pointer to next record
        $this->pos += 8 + $length;
        // offset: 2; size: 2; upper-left corner column index (0-based)
        $c1 = Xls::get_u_int2d($record_data, 2);
        // offset: 4; size: 2; upper-left corner horizontal offset in 1/1024 of column width
        $start_offset_x = Xls::get_u_int2d($record_data, 4);
        // offset: 6; size: 2; upper-left corner row index (0-based)
        $r1 = Xls::get_u_int2d($record_data, 6);
        // offset: 8; size: 2; upper-left corner vertical offset in 1/256 of row height
        $start_offset_y = Xls::get_u_int2d($record_data, 8);
        // offset: 10; size: 2; bottom-right corner column index (0-based)
        $c2 = Xls::get_u_int2d($record_data, 10);
        // offset: 12; size: 2; bottom-right corner horizontal offset in 1/1024 of column width
        $end_offset_x = Xls::get_u_int2d($record_data, 12);
        // offset: 14; size: 2; bottom-right corner row index (0-based)
        $r2 = Xls::get_u_int2d($record_data, 14);
        // offset: 16; size: 2; bottom-right corner vertical offset in 1/256 of row height
        $end_offset_y = Xls::get_u_int2d($record_data, 16);
        $this->apply_attribute('setStartCoordinates', Coordinate::string_from_column_index($c1 + 1) . ($r1 + 1));
        $this->apply_attribute('setStartOffsetX', $start_offset_x);
        $this->apply_attribute('setStartOffsetY', $start_offset_y);
        $this->apply_attribute('setEndCoordinates', Coordinate::string_from_column_index($c2 + 1) . ($r2 + 1));
        $this->apply_attribute('setEndOffsetX', $end_offset_x);
        $this->apply_attribute('setEndOffsetY', $end_offset_y);
    }
    private function apply_attribute(string $name, mixed $value): void
    {
        if (method_exists($this->object, $name)) {
            $this->object->{$name}($value);
        }
    }
    /**
     * Read ClientData record.
     */
    private function read_client_data(): void
    {
        $length = Xls::get_int4d($this->data, $this->pos + 4);
        //$recordData = substr($this->data, $this->pos + 8, $length);
        // move stream pointer to next record
        $this->pos += 8 + $length;
    }
    /**
     * Read OfficeArtRGFOPTE table of property-value pairs.
     *
     * @param string $data Binary data
     * @param int $n Number of properties
     */
    private function read_office_art_rgfopte(string $data, int $n): void
    {
        $spliced_complex_data = substr($data, 6 * $n);
        // loop through property-value pairs
        for ($i = 0; $i < $n; ++$i) {
            // read 6 bytes at a time
            $fopte = substr($data, 6 * $i, 6);
            // offset: 0; size: 2; opid
            $opid = Xls::get_u_int2d($fopte, 0);
            // bit: 0-13; mask: 0x3FFF; opid.opid
            $opid_opid = (0x3fff & $opid) >> 0;
            // bit: 14; mask 0x4000; 1 = value in op field is BLIP identifier
            //$opidFBid = (0x4000 & $opid) >> 14;
            // bit: 15; mask 0x8000; 1 = this is a complex property, op field specifies size of complex data
            $opid_f_complex = (0x8000 & $opid) >> 15;
            // offset: 2; size: 4; the value for this property
            $op = Xls::get_int4d($fopte, 2);
            if ($opid_f_complex) {
                $complex_data = substr($spliced_complex_data, 0, $op);
                $spliced_complex_data = substr($spliced_complex_data, $op);
                // we store string value with complex data
                $value = $complex_data;
            } else {
                // we store integer value
                $value = $op;
            }
            if (method_exists($this->object, 'setOPT')) {
                $this->object->set_opt($opid_opid, $value);
            }
        }
    }
}