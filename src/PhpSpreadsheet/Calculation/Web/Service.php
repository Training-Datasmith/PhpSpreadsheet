<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Web;

use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Cell\Cell;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
class Service
{
    /**
     * WEBSERVICE.
     *
     * Returns data from a web service on the Internet or Intranet.
     *
     * Excel Function:
     *        Webservice(url)
     *
     * @return string the output resulting from a call to the webservice
     */
    public static function web_service(mixed $url, ?Cell $cell = null): ?string
    {
        if (is_array($url)) {
            $url = Functions::flatten_single_value($url);
        }
        $url = trim(String_Helper::convert_to_string($url, false));
        if (mb_strlen($url) > 2048) {
            return Excel_Error::VALUE();
            // Invalid URL length
        }
        $parsed = parse_url($url);
        $scheme = $parsed['scheme'] ?? '';
        if ($scheme !== 'http' && $scheme !== 'https') {
            return Excel_Error::VALUE();
            // Invalid protocol
        }
        $domain_white_list = $cell?->get_worksheet()->get_parent()?->get_domain_white_list() ?? [];
        $host = $parsed['host'] ?? '';
        if (!in_array($host, $domain_white_list, true)) {
            return $cell === null ? null : Functions::NOT_YET_IMPLEMENTED;
            // will be converted to oldCalculatedValue or null
        }
        // Get results from the webservice
        $ctx_array = ['http' => ['user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36']];
        if ($scheme === 'https') {
            $ctx_array['ssl'] = ['crypto_method' => Stream_crypto_method_tl_Sv1_3_client];
        }
        $ctx = stream_context_create($ctx_array);
        $output = @file_get_contents($url, false, $ctx);
        if ($output === false || mb_strlen($output) > 32767) {
            return Excel_Error::VALUE();
            // Output not a string or too long
        }
        return $output;
    }
    /**
     * URLENCODE.
     *
     * Returns data from a web service on the Internet or Intranet.
     *
     * Excel Function:
     *        urlEncode(text)
     *
     * @return string the url encoded output
     */
    public static function url_encode(mixed $text): string
    {
        if (!is_string($text)) {
            return Excel_Error::VALUE();
        }
        return str_replace('+', '%20', urlencode($text));
    }
}