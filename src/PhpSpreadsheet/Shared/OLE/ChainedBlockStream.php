<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Shared\OLE;

use Php_Office\Php_Spreadsheet\Shared\OLE;
class Chained_Block_Stream
{
    /** @var mixed */
    public $context;
    /**
     * The OLE container of the file that is being read.
     */
    public ?OLE $ole = null;
    /**
     * Parameters specified by fopen().
     *
     * @var mixed[]
     */
    public array $params = [];
    /**
     * The binary data of the file.
     */
    public string $data;
    /**
     * The file pointer.
     *
     * @var int byte offset
     */
    public int $pos = 0;
    /**
     * Implements support for fopen().
     * For creating streams using this wrapper, use OLE_PPS_File::getStream().
     *
     * @param string $path resource name including scheme, e.g.
     *                                    ole-chainedblockstream://oleInstanceId=1
     * @param string $mode only "r" is supported
     * @param int $options mask of STREAM_REPORT_ERRORS and STREAM_USE_PATH
     * @param ?string $openedPath absolute path of the opened stream (out parameter)
     *
     * @return bool true on success
     */
    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        if ($mode[0] !== 'r') {
            if ($options & STREAM_REPORT_ERRORS) {
                trigger_error('Only reading is supported', E_USER_WARNING);
            }
            return false;
        }
        // 25 is length of "ole-chainedblockstream://"
        parse_str(substr($path, 25), $this->params);
        if (!isset($this->params['oleInstanceId'], $this->params['blockId'], $GLOBALS['_OLE_INSTANCES'][$this->params['oleInstanceId']])) {
            //* @phpstan-ignore-line
            if ($options & STREAM_REPORT_ERRORS) {
                trigger_error('OLE stream not found', E_USER_WARNING);
            }
            return false;
        }
        $this->ole = $GLOBALS['_OLE_INSTANCES'][$this->params['oleInstanceId']];
        $block_id = $this->params['blockId'];
        $this->data = '';
        if (isset($this->params['size']) && $this->params['size'] < $this->ole->big_block_threshold && $block_id != $this->ole->root->start_block) {
            // Block id refers to small blocks
            $root_pos = $this->ole->get_block_offset((int) $this->ole->root->start_block);
            while ($block_id != -2) {
                /** @var int $blockId */
                $pos = $root_pos + $block_id * $this->ole->big_block_size;
                $block_id = $this->ole->sbat[$block_id];
                fseek($this->ole->_file_handle, $pos);
                $this->data .= fread($this->ole->_file_handle, $this->ole->big_block_size);
            }
        } else {
            // Block id refers to big blocks
            while ($block_id != -2) {
                $pos = $this->ole->get_block_offset($block_id);
                fseek($this->ole->_file_handle, $pos);
                $this->data .= fread($this->ole->_file_handle, $this->ole->big_block_size);
                $block_id = $this->ole->bbat[$block_id];
            }
        }
        if (isset($this->params['size'])) {
            $this->data = substr($this->data, 0, $this->params['size']);
            //* @phpstan-ignore-line
        }
        if ($options & STREAM_USE_PATH) {
            $opened_path = $path;
        }
        return true;
    }
    /**
     * Implements support for fclose().
     */
    public function stream_close(): void
    {
        $this->ole = null;
        unset($GLOBALS['_OLE_INSTANCES']);
    }
    /**
     * Implements support for fread(), fgets() etc.
     *
     * @param int $count maximum number of bytes to read
     *
     * @return false|string
     */
    public function stream_read(int $count): bool|string
    {
        if ($this->stream_eof()) {
            return false;
        }
        $s = substr($this->data, $this->pos, $count);
        $this->pos += $count;
        return $s;
    }
    /**
     * Implements support for feof().
     *
     * @return bool TRUE if the file pointer is at EOF; otherwise FALSE
     */
    public function stream_eof(): bool
    {
        return $this->pos >= strlen($this->data);
    }
    /**
     * Returns the position of the file pointer, i.e. its offset into the file
     * stream. Implements support for ftell().
     */
    public function stream_tell(): int
    {
        return $this->pos;
    }
    /**
     * Implements support for fseek().
     *
     * @param int $offset byte offset
     * @param int $whence SEEK_SET, SEEK_CUR or SEEK_END
     */
    public function stream_seek(int $offset, int $whence): bool
    {
        if ($whence == SEEK_SET && $offset >= 0) {
            $this->pos = $offset;
        } elseif ($whence == SEEK_CUR && -$offset <= $this->pos) {
            $this->pos += $offset;
        } elseif ($whence == SEEK_END && -$offset <= count($this->data)) {
            // @phpstan-ignore-line
            $this->pos = strlen($this->data) + $offset;
        } else {
            return false;
        }
        return true;
    }
    /**
     * Implements support for fstat(). Currently the only supported field is
     * "size".
     *
     * @return array{size: int}
     */
    public function stream_stat(): array
    {
        return ['size' => strlen($this->data)];
    }
    // Methods used by stream_wrapper_register() that are not implemented:
    // bool stream_flush ( void )
    // int stream_write ( string data )
    // bool rename ( string path_from, string path_to )
    // bool mkdir ( string path, int mode, int options )
    // bool rmdir ( string path, int options )
    // bool dir_opendir ( string path, int options )
    // array url_stat ( string path, int flags )
    // string dir_readdir ( void )
    // bool dir_rewinddir ( void )
    // bool dir_closedir ( void )
}