<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Xlsx;

class Theme
{
    /**
     * Create a new Theme.
     *
     * @param string[] $colourMap
     */
    public function __construct(
        /**
         * Theme Name.
         */
        private readonly string $theme_name,
        /**
         * Colour Scheme Name.
         */
        private readonly string $colour_scheme_name,
        /**
         * Colour Map.
         */
        private array $colour_map
    )
    {
    }
    /**
     * Not called by Reader, never accessible any other time.
     *
     * @codeCoverageIgnore
     */
    public function get_theme_name(): string
    {
        return $this->theme_name;
    }
    /**
     * Not called by Reader, never accessible any other time.
     *
     * @codeCoverageIgnore
     */
    public function get_colour_scheme_name(): string
    {
        return $this->colour_scheme_name;
    }
    /**
     * Get colour Map Value by Position.
     */
    public function get_colour_by_index(int $index): ?string
    {
        return $this->colour_map[$index] ?? null;
    }
}