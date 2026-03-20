<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Security;

use Php_Office\Php_Spreadsheet\Reader;
class Xml_Scanner
{
    private const ENCODING_PATTERN = '/encoding\s*=\s*(["\'])(.+?)\1/s';
    private const ENCODING_UTF7 = '/encoding\s*=\s*(["\'])UTF-7\1/si';
    /** @var ?callable */
    private $callback;
    public function __construct(private readonly string $pattern = '<!DOCTYPE')
    {
    }
    public static function get_instance(Reader\I_Reader $reader): self
    {
        $pattern = $reader instanceof Reader\Html ? '<!ENTITY' : '<!DOCTYPE';
        return new self($pattern);
    }
    public function set_additional_callback(callable $callback): void
    {
        $this->callback = $callback;
    }
    private static function force_string(mixed $arg): string
    {
        return is_string($arg) ? $arg : '';
    }
    private function to_utf8(string $xml): string
    {
        $charset = $this->find_char_set($xml);
        $found_utf7 = $charset === 'UTF-7';
        if ($charset !== 'UTF-8') {
            $test_start = '/^.{0,4}\s*<?xml/s';
            $start_with_xml1 = preg_match($test_start, $xml);
            $xml = self::force_string(mb_convert_encoding($xml, 'UTF-8', $charset));
            if ($start_with_xml1 === 1 && preg_match($test_start, $xml) !== 1) {
                throw new Reader\Exception('Double encoding not permitted');
            }
            $found_utf7 = $found_utf7 || preg_match(self::ENCODING_UTF7, $xml) === 1;
            $xml = preg_replace(self::ENCODING_PATTERN, '', $xml) ?? $xml;
        } else {
            $found_utf7 = $found_utf7 || preg_match(self::ENCODING_UTF7, $xml) === 1;
        }
        if ($found_utf7) {
            throw new Reader\Exception('UTF-7 encoding not permitted');
        }
        if (substr($xml, 0, Reader\Csv::UTF8_BOM_LEN) === Reader\Csv::UTF8_BOM) {
            return substr($xml, Reader\Csv::UTF8_BOM_LEN);
        }
        return $xml;
    }
    private function find_char_set(string $xml): string
    {
        if (str_starts_with($xml, "Lo\xa7\x94")) {
            throw new Reader\Exception('EBCDIC encoding not permitted');
        }
        $encoding = Reader\Csv::guess_encoding_bom('', $xml);
        if ($encoding !== '') {
            return $encoding;
        }
        $xml = str_replace("\x00", '', $xml);
        if (preg_match(self::ENCODING_PATTERN, $xml, $matches)) {
            return strtoupper($matches[2]);
        }
        return 'UTF-8';
    }
    /**
     * Scan the XML for use of <!ENTITY to prevent XXE/XEE attacks.
     *
     * @param false|string $xml
     */
    public function scan($xml): string
    {
        // Don't rely purely on libxml_disable_entity_loader()
        $pattern = '/\0*' . implode('\0*', mb_str_split($this->pattern, 1, 'UTF-8')) . '\0*/';
        $xml = "{$xml}";
        if (preg_match($pattern, $xml)) {
            throw new Reader\Exception('Detected use of ENTITY in XML, spreadsheet file load() aborted to prevent XXE/XEE attacks');
        }
        $xml = $this->to_utf8($xml);
        if (preg_match($pattern, $xml)) {
            throw new Reader\Exception('Detected use of ENTITY in XML, spreadsheet file load() aborted to prevent XXE/XEE attacks');
        }
        if ($this->callback !== null) {
            return call_user_func($this->callback, $xml);
        }
        /** @var string $xml */
        return $xml;
    }
    /**
     * Scan the XML for use of <!ENTITY to prevent XXE/XEE attacks.
     */
    public function scan_file(string $filestream): string
    {
        return $this->scan(file_get_contents($filestream));
    }
}