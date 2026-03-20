<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet;

use Php_Office\Php_Spreadsheet\Calculation\Calculation;
use Php_Office\Php_Spreadsheet\Chart\Renderer\I_Renderer;
use Php_Office\Php_Spreadsheet\Collection\Memory;
use Psr\Simple_Cache\Cache_Interface;
use ReflectionClass;
class Settings
{
    /**
     * Class name of the chart renderer used for rendering charts
     * eg: PhpOffice\PhpSpreadsheet\Chart\Renderer\JpGraph.
     *
     * @var null|class-string<IRenderer>
     */
    private static ?string $chart_renderer = null;
    /**
     * The cache implementation to be used for cell collection.
     */
    private static ?Cache_Interface $cache = null;
    private static mixed $http_client = null;
    private static mixed $request_factory = null;
    /**
     * Set the locale code to use for formula translations and any special formatting.
     *
     * @param string $locale The locale code to use (e.g. "fr" or "pt_br" or "en_uk")
     *
     * @return bool Success or failure
     */
    public static function set_locale(string $locale): bool
    {
        return Calculation::get_instance()->set_locale($locale);
    }
    public static function get_locale(): string
    {
        return Calculation::get_instance()->get_locale();
    }
    /**
     * Identify to PhpSpreadsheet the external library to use for rendering charts.
     *
     * @param class-string<IRenderer> $rendererClassName Class name of the chart renderer
     *    eg: PhpOffice\PhpSpreadsheet\Chart\Renderer\JpGraph
     */
    public static function set_chart_renderer(string $renderer_class_name): void
    {
        // We want phpstan to validate caller, but still need this test
        if (!is_a($renderer_class_name, I_Renderer::class, true)) {
            //* @phpstan-ignore-line
            throw new Exception('Chart renderer must implement ' . I_Renderer::class);
        }
        self::$chart_renderer = $renderer_class_name;
    }
    public static function unset_chart_renderer(): void
    {
        self::$chart_renderer = null;
    }
    /**
     * Return the Chart Rendering Library that PhpSpreadsheet is currently configured to use.
     *
     * @return null|class-string<IRenderer> Class name of the chart renderer
     *    eg: PhpOffice\PhpSpreadsheet\Chart\Renderer\JpGraph
     */
    public static function get_chart_renderer(): ?string
    {
        return self::$chart_renderer;
    }
    public static function html_entity_flags(): int
    {
        return ENT_COMPAT;
    }
    /**
     * Sets the implementation of cache that should be used for cell collection.
     */
    public static function set_cache(?Cache_Interface $cache): void
    {
        self::$cache = $cache;
    }
    /**
     * Gets the implementation of cache that is being used for cell collection.
     */
    public static function get_cache(): Cache_Interface
    {
        if (!self::$cache) {
            self::$cache = self::use_simple_cache_version3() ? new Memory\Simple_Cache3() : new Memory\Simple_Cache1();
        }
        return self::$cache;
    }
    public static function use_simple_cache_version3(): bool
    {
        return (new ReflectionClass(Cache_Interface::class))->get_method('get')->get_return_type() !== null;
    }
    /**
     * Set the HTTP client implementation to be used for network request.
     *
     * @deprecated 5.4.0 No replacement.
     *
     * @codeCoverageIgnore
     */
    public static function set_http_client(mixed $http_client, mixed $request_factory): void
    {
        self::$http_client = $http_client;
        self::$request_factory = $request_factory;
    }
    /**
     * Unset the HTTP client configuration.
     *
     * @deprecated 5.4.0 No replacement.
     *
     * @codeCoverageIgnore
     */
    public static function unset_http_client(): void
    {
        self::$http_client = null;
        self::$request_factory = null;
    }
    /**
     * Get the HTTP client implementation to be used for network request.
     *
     * @deprecated 5.4.0 No replacement.
     *
     * @codeCoverageIgnore
     */
    public static function get_http_client(): mixed
    {
        return self::$http_client;
    }
    /**
     * Get the HTTP request factory.
     *
     * @deprecated 5.4.0 No replacement.
     *
     * @codeCoverageIgnore
     */
    public static function get_request_factory(): mixed
    {
        return self::$request_factory;
    }
}