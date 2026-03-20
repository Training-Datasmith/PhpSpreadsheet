<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer\Xls;

use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Shared\Escher as SharedEscher;
use Php_Office\Php_Spreadsheet\Shared\Escher\Dg_Container;
use Php_Office\Php_Spreadsheet\Shared\Escher\Dg_Container\Spgr_Container;
use Php_Office\Php_Spreadsheet\Shared\Escher\Dg_Container\Spgr_Container\Sp_Container;
use Php_Office\Php_Spreadsheet\Shared\Escher\Dgg_Container;
use Php_Office\Php_Spreadsheet\Shared\Escher\Dgg_Container\Bstore_Container;
use Php_Office\Php_Spreadsheet\Shared\Escher\Dgg_Container\Bstore_Container\BSE;
use Php_Office\Php_Spreadsheet\Shared\Escher\Dgg_Container\Bstore_Container\BSE\Blip;
class Escher
{
    /**
     * The written binary data.
     */
    private string $data;
    /**
     * Shape offsets. Positions in binary stream where a new shape record begins.
     *
     * @var int[]
     */
    private array $sp_offsets;
    /**
     * Shape types.
     *
     * @var mixed[]
     */
    private array $sp_types;
    /**
     * Constructor.
     */
    public function __construct(
        /**
         * The object we are writing.
         */
        private readonly Blip|BSE|Bstore_Container|Dg_Container|Dgg_Container|Escher|Sp_Container|Spgr_Container|Shared_Escher $object
    )
    {
    }
    /**
     * Process the object to be written.
     */
    public function close(): string
    {
        // initialize
        $this->data = '';
        switch ($this->object::class) {
            case Shared_Escher::class:
                if ($dgg_container = $this->object->get_dgg_container()) {
                    $writer = new self($dgg_container);
                    $this->data = $writer->close();
                } elseif ($dg_container = $this->object->get_dg_container()) {
                    $writer = new self($dg_container);
                    $this->data = $writer->close();
                    $this->sp_offsets = $writer->get_sp_offsets();
                    $this->sp_types = $writer->get_sp_types();
                }
                break;
            case Dgg_Container::class:
                // this is a container record
                // initialize
                $inner_data = '';
                // write the dgg
                $rec_ver = 0x0;
                $rec_instance = 0x0;
                $rec_type = 0xf006;
                $rec_ver_instance = $rec_ver;
                $rec_ver_instance |= $rec_instance << 4;
                // dgg data
                $dgg_data = pack(
                    'VVVV',
                    $this->object->get_sp_id_max(),
                    // maximum shape identifier increased by one
                    $this->object->get_c_dg_saved() + 1,
                    // number of file identifier clusters increased by one
                    $this->object->get_c_sp_saved(),
                    $this->object->get_c_dg_saved()
                );
                // add file identifier clusters (one per drawing)
                $idc_ls = $this->object->get_idc_ls();
                foreach ($idc_ls as $dg_id => $max_reduced_sp_id) {
                    /** @var int $maxReducedSpId */
                    $dgg_data .= pack('VV', $dg_id, $max_reduced_sp_id + 1);
                }
                $header = pack('vvV', $rec_ver_instance, $rec_type, strlen($dgg_data));
                $inner_data .= $header . $dgg_data;
                // write the bstoreContainer
                if ($bstore_container = $this->object->get_bstore_container()) {
                    $writer = new self($bstore_container);
                    $inner_data .= $writer->close();
                }
                // write the record
                $rec_ver = 0xf;
                $rec_instance = 0x0;
                $rec_type = 0xf000;
                $length = strlen($inner_data);
                $rec_ver_instance = $rec_ver;
                $rec_ver_instance |= $rec_instance << 4;
                $header = pack('vvV', $rec_ver_instance, $rec_type, $length);
                $this->data = $header . $inner_data;
                break;
            case Bstore_Container::class:
                // this is a container record
                // initialize
                $inner_data = '';
                // treat the inner data
                if ($bse_collection = $this->object->get_bse_collection()) {
                    foreach ($bse_collection as $BSE) {
                        $writer = new self($BSE);
                        $inner_data .= $writer->close();
                    }
                }
                // write the record
                $rec_ver = 0xf;
                $rec_instance = count($this->object->get_bse_collection());
                $rec_type = 0xf001;
                $length = strlen($inner_data);
                $rec_ver_instance = $rec_ver;
                $rec_ver_instance |= $rec_instance << 4;
                $header = pack('vvV', $rec_ver_instance, $rec_type, $length);
                $this->data = $header . $inner_data;
                break;
            case BSE::class:
                // this is a semi-container record
                // initialize
                $inner_data = '';
                // here we treat the inner data
                if ($blip = $this->object->get_blip()) {
                    $writer = new self($blip);
                    $inner_data .= $writer->close();
                }
                // initialize
                $data = '';
                $bt_win32 = $this->object->get_blip_type();
                $bt_mac_os = $this->object->get_blip_type();
                $data .= pack('CC', $bt_win32, $bt_mac_os);
                $rgb_uid = pack('VVVV', 0, 0, 0, 0);
                // todo
                $data .= $rgb_uid;
                $tag = 0;
                $size = strlen($inner_data);
                $c_ref = 1;
                $fo_delay = 0;
                //todo
                $unused1 = 0x0;
                $cb_name = 0x0;
                $unused2 = 0x0;
                $unused3 = 0x0;
                $data .= pack('vVVVCCCC', $tag, $size, $c_ref, $fo_delay, $unused1, $cb_name, $unused2, $unused3);
                $data .= $inner_data;
                // write the record
                $rec_ver = 0x2;
                $rec_instance = $this->object->get_blip_type();
                $rec_type = 0xf007;
                $length = strlen($data);
                $rec_ver_instance = $rec_ver;
                $rec_ver_instance |= $rec_instance << 4;
                $header = pack('vvV', $rec_ver_instance, $rec_type, $length);
                $this->data = $header;
                $this->data .= $data;
                break;
            case Blip::class:
                // this is an atom record
                // write the record
                switch ($this->object->get_parent()->get_blip_type()) {
                    case BSE::BLIPTYPE_JPEG:
                        // initialize
                        $inner_data = '';
                        $rgb_uid1 = pack('VVVV', 0, 0, 0, 0);
                        // todo
                        $inner_data .= $rgb_uid1;
                        $tag = 0xff;
                        // todo
                        $inner_data .= pack('C', $tag);
                        $inner_data .= $this->object->get_data();
                        $rec_ver = 0x0;
                        $rec_instance = 0x46a;
                        $rec_type = 0xf01d;
                        $length = strlen($inner_data);
                        $rec_ver_instance = $rec_ver;
                        $rec_ver_instance |= $rec_instance << 4;
                        $header = pack('vvV', $rec_ver_instance, $rec_type, $length);
                        $this->data = $header;
                        $this->data .= $inner_data;
                        break;
                    case BSE::BLIPTYPE_PNG:
                        // initialize
                        $inner_data = '';
                        $rgb_uid1 = pack('VVVV', 0, 0, 0, 0);
                        // todo
                        $inner_data .= $rgb_uid1;
                        $tag = 0xff;
                        // todo
                        $inner_data .= pack('C', $tag);
                        $inner_data .= $this->object->get_data();
                        $rec_ver = 0x0;
                        $rec_instance = 0x6e0;
                        $rec_type = 0xf01e;
                        $length = strlen($inner_data);
                        $rec_ver_instance = $rec_ver;
                        $rec_ver_instance |= $rec_instance << 4;
                        $header = pack('vvV', $rec_ver_instance, $rec_type, $length);
                        $this->data = $header;
                        $this->data .= $inner_data;
                        break;
                }
                break;
            case Dg_Container::class:
                // this is a container record
                // initialize
                $inner_data = '';
                // write the dg
                $rec_ver = 0x0;
                $rec_instance = $this->object->get_dg_id();
                $rec_type = 0xf008;
                $length = 8;
                $rec_ver_instance = $rec_ver;
                $rec_ver_instance |= $rec_instance << 4;
                $header = pack('vvV', $rec_ver_instance, $rec_type, $length);
                // number of shapes in this drawing (including group shape)
                $count_shapes = count($this->object->get_spgr_container_or_throw()->get_children());
                $inner_data .= $header . pack('VV', $count_shapes, $this->object->get_last_sp_id());
                // write the spgrContainer
                if ($spgr_container = $this->object->get_spgr_container()) {
                    $writer = new self($spgr_container);
                    $inner_data .= $writer->close();
                    // get the shape offsets relative to the spgrContainer record
                    $sp_offsets = $writer->get_sp_offsets();
                    $sp_types = $writer->get_sp_types();
                    // save the shape offsets relative to dgContainer
                    foreach ($sp_offsets as &$sp_offset) {
                        $sp_offset += 24;
                        // add length of dgContainer header data (8 bytes) plus dg data (16 bytes)
                    }
                    $this->sp_offsets = $sp_offsets;
                    $this->sp_types = $sp_types;
                }
                // write the record
                $rec_ver = 0xf;
                $rec_instance = 0x0;
                $rec_type = 0xf002;
                $length = strlen($inner_data);
                $rec_ver_instance = $rec_ver;
                $rec_ver_instance |= $rec_instance << 4;
                $header = pack('vvV', $rec_ver_instance, $rec_type, $length);
                $this->data = $header . $inner_data;
                break;
            case Spgr_Container::class:
                // this is a container record
                // initialize
                $inner_data = '';
                // initialize spape offsets
                $total_size = 8;
                $sp_offsets = [];
                $sp_types = [];
                // treat the inner data
                foreach ($this->object->get_children() as $sp_container) {
                    /** @var Blip|BSE|BstoreContainer|DgContainer|DggContainer|SharedEscher|SpContainer|SpgrContainer $spContainer */
                    $writer = new self($sp_container);
                    $sp_data = $writer->close();
                    $inner_data .= $sp_data;
                    // save the shape offsets (where new shape records begin)
                    $total_size += strlen($sp_data);
                    $sp_offsets[] = $total_size;
                    $sp_types = array_merge($sp_types, $writer->get_sp_types());
                }
                // write the record
                $rec_ver = 0xf;
                $rec_instance = 0x0;
                $rec_type = 0xf003;
                $length = strlen($inner_data);
                $rec_ver_instance = $rec_ver;
                $rec_ver_instance |= $rec_instance << 4;
                $header = pack('vvV', $rec_ver_instance, $rec_type, $length);
                $this->data = $header . $inner_data;
                $this->sp_offsets = $sp_offsets;
                $this->sp_types = $sp_types;
                break;
            case Sp_Container::class:
                // initialize
                $data = '';
                // build the data
                // write group shape record, if necessary?
                if ($this->object->get_spgr()) {
                    $rec_ver = 0x1;
                    $rec_instance = 0x0;
                    $rec_type = 0xf009;
                    $length = 0x10;
                    $rec_ver_instance = $rec_ver;
                    $rec_ver_instance |= $rec_instance << 4;
                    $header = pack('vvV', $rec_ver_instance, $rec_type, $length);
                    $data .= $header . pack('VVVV', 0, 0, 0, 0);
                }
                $this->sp_types[] = $this->object->get_sp_type();
                // write the shape record
                $rec_ver = 0x2;
                $rec_instance = $this->object->get_sp_type();
                // shape type
                $rec_type = 0xf00a;
                $length = 0x8;
                $rec_ver_instance = $rec_ver;
                $rec_ver_instance |= $rec_instance << 4;
                $header = pack('vvV', $rec_ver_instance, $rec_type, $length);
                $data .= $header . pack('VV', $this->object->get_sp_id(), $this->object->get_spgr() ? 0x5 : 0xa00);
                // the options
                if ($this->object->get_opt_collection()) {
                    $opt_data = '';
                    $rec_ver = 0x3;
                    $rec_instance = count($this->object->get_opt_collection());
                    $rec_type = 0xf00b;
                    foreach ($this->object->get_opt_collection() as $property => $value) {
                        $opt_data .= pack('vV', $property, $value);
                    }
                    $length = strlen($opt_data);
                    $rec_ver_instance = $rec_ver;
                    $rec_ver_instance |= $rec_instance << 4;
                    $header = pack('vvV', $rec_ver_instance, $rec_type, $length);
                    $data .= $header . $opt_data;
                }
                // the client anchor
                if ($this->object->get_start_coordinates()) {
                    $rec_ver = 0x0;
                    $rec_instance = 0x0;
                    $rec_type = 0xf010;
                    // start coordinates
                    [$column, $row] = Coordinate::indexes_from_string($this->object->get_start_coordinates());
                    $c1 = $column - 1;
                    $r1 = $row - 1;
                    // start offsetX
                    $start_offset_x = $this->object->get_start_offset_x();
                    // start offsetY
                    $start_offset_y = $this->object->get_start_offset_y();
                    // end coordinates
                    [$column, $row] = Coordinate::indexes_from_string($this->object->get_end_coordinates());
                    $c2 = $column - 1;
                    $r2 = $row - 1;
                    // end offsetX
                    $end_offset_x = $this->object->get_end_offset_x();
                    // end offsetY
                    $end_offset_y = $this->object->get_end_offset_y();
                    $client_anchor_data = pack('vvvvvvvvv', $this->object->get_sp_flag(), $c1, $start_offset_x, $r1, $start_offset_y, $c2, $end_offset_x, $r2, $end_offset_y);
                    $length = strlen($client_anchor_data);
                    $rec_ver_instance = $rec_ver;
                    $rec_ver_instance |= $rec_instance << 4;
                    $header = pack('vvV', $rec_ver_instance, $rec_type, $length);
                    $data .= $header . $client_anchor_data;
                }
                // the client data, just empty for now
                if (!$this->object->get_spgr()) {
                    $client_data_data = '';
                    $rec_ver = 0x0;
                    $rec_instance = 0x0;
                    $rec_type = 0xf011;
                    $length = strlen($client_data_data);
                    $rec_ver_instance = $rec_ver;
                    $rec_ver_instance |= $rec_instance << 4;
                    $header = pack('vvV', $rec_ver_instance, $rec_type, $length);
                    $data .= $header . $client_data_data;
                }
                // write the record
                $rec_ver = 0xf;
                $rec_instance = 0x0;
                $rec_type = 0xf004;
                $length = strlen($data);
                $rec_ver_instance = $rec_ver;
                $rec_ver_instance |= $rec_instance << 4;
                $header = pack('vvV', $rec_ver_instance, $rec_type, $length);
                $this->data = $header . $data;
                break;
        }
        return $this->data;
    }
    /**
     * Gets the shape offsets.
     *
     * @return int[]
     */
    public function get_sp_offsets(): array
    {
        return $this->sp_offsets;
    }
    /**
     * Gets the shape types.
     *
     * @return mixed[]
     */
    public function get_sp_types(): array
    {
        return $this->sp_types;
    }
}