<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Chart\Renderer;

use Php_Office\Php_Spreadsheet\Chart\Chart;
interface I_Renderer
{
    /**
     * IRenderer constructor.
     */
    public function __construct(Chart $chart);
    /**
     * Render the chart to given file (or stream).
     *
     * @param ?string $filename Name of the file render to
     *
     * @return bool true on success
     */
    public function render(?string $filename): bool;
}