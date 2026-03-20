<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Shared;

use Php_Office\Php_Spreadsheet\Exception as SpException;
use Php_Office\Php_Spreadsheet\Worksheet\Protection;
class Password_Hasher
{
    public const MAX_PASSWORD_LENGTH = 255;
    /**
     * Get algorithm name for PHP.
     */
    private static function get_algorithm(string $algorithm_name): string
    {
        if (!$algorithm_name) {
            return '';
        }
        // Mapping between algorithm name in Excel and algorithm name in PHP
        $mapping = [Protection::ALGORITHM_MD2 => 'md2', Protection::ALGORITHM_MD4 => 'md4', Protection::ALGORITHM_MD5 => 'md5', Protection::ALGORITHM_SHA_1 => 'sha1', Protection::ALGORITHM_SHA_256 => 'sha256', Protection::ALGORITHM_SHA_384 => 'sha384', Protection::ALGORITHM_SHA_512 => 'sha512', Protection::ALGORITHM_RIPEMD_128 => 'ripemd128', Protection::ALGORITHM_RIPEMD_160 => 'ripemd160', Protection::ALGORITHM_WHIRLPOOL => 'whirlpool'];
        if (array_key_exists($algorithm_name, $mapping)) {
            return $mapping[$algorithm_name];
        }
        throw new Sp_Exception('Unsupported password algorithm: ' . $algorithm_name);
    }
    /**
     * Create a password hash from a given string.
     *
     * This method is based on the spec at:
     * https://interoperability.blob.core.windows.net/files/MS-OFFCRYPTO/[MS-OFFCRYPTO].pdf
     * 2.3.7.1 Binary Document Password Verifier Derivation Method 1
     *
     * It replaces a method based on the algorithm provided by
     * Daniel Rentz of OpenOffice and the PEAR package
     * Spreadsheet_Excel_Writer by Xavier Noguer <xnoguer@rezebra.com>.
     *
     * @param string $password Password to hash
     */
    private static function default_hash_password(string $password): string
    {
        $verifier = 0;
        $pwlen = strlen($password);
        $password_array = pack('c', $pwlen) . $password;
        for ($i = $pwlen; $i >= 0; --$i) {
            $intermediate1 = ($verifier & 0x4000) === 0 ? 0 : 1;
            $intermediate2 = 2 * $verifier;
            $intermediate2 = $intermediate2 & 0x7fff;
            $intermediate3 = $intermediate1 | $intermediate2;
            $verifier = $intermediate3 ^ ord($password_array[$i]);
        }
        $verifier ^= 0xce4b;
        return strtoupper(dechex($verifier));
    }
    /**
     * Create a password hash from a given string by a specific algorithm.
     *
     * 2.4.2.4 ISO Write Protection Method
     *
     * @see https://docs.microsoft.com/en-us/openspecs/office_file_formats/ms-offcrypto/1357ea58-646e-4483-92ef-95d718079d6f
     *
     * @param string $password Password to hash
     * @param string $algorithm Hash algorithm used to compute the password hash value
     * @param string $salt Pseudorandom base64-encoded string
     * @param int $spinCount Number of times to iterate on a hash of a password
     *
     * @return string Hashed password
     */
    public static function hash_password(string $password, string $algorithm = '', string $salt = '', int $spin_count = 10000): string
    {
        if (strlen($password) > self::MAX_PASSWORD_LENGTH) {
            throw new Sp_Exception('Password exceeds ' . self::MAX_PASSWORD_LENGTH . ' characters');
        }
        $php_algorithm = self::get_algorithm($algorithm);
        if (!$php_algorithm) {
            return self::default_hash_password($password);
        }
        $salt_value = base64_decode($salt);
        $encoded_password = mb_convert_encoding($password, 'UCS-2LE', 'UTF-8');
        $hash_value = hash($php_algorithm, $salt_value . $encoded_password, true);
        for ($i = 0; $i < $spin_count; ++$i) {
            $hash_value = hash($php_algorithm, $hash_value . pack('L', $i), true);
        }
        return base64_encode($hash_value);
    }
}