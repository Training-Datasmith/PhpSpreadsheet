<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Chart\Renderer;

use mitoteam\jpgraph\Mt_Jp_Graph;
/**
 * Jpgraph is not officially maintained by Composer at packagist.org.
 *
 * This renderer implementation uses package
 * https://packagist.org/packages/mitoteam/jpgraph
 *
 * This package is up to date for June 2023 and has PHP 8.2 support.
 */
class Mt_Jp_Graph_Renderer extends Jp_Graph_Renderer_Base
{
    protected static function init(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        Mt_Jp_Graph::load(['bar', 'contour', 'line', 'pie', 'pie3d', 'radar', 'regstat', 'scatter', 'stock'], true);
        // enable Extended mode
        $loaded = true;
    }
}