<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Chart\Renderer;

/**
 * Jpgraph is not officially maintained in Composer, so the version there
 * could be out of date. For that reason, all unit test requiring Jpgraph
 * are skipped. So, do not measure code coverage for this class till that
 * is fixed.
 *
 * This implementation uses abandoned package
 * https://packagist.org/packages/jpgraph/jpgraph
 *
 * @codeCoverageIgnore
 */
class Jp_Graph extends Jp_Graph_Renderer_Base
{
    protected static function init(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        // JpGraph is no longer included with distribution, but user may install it.
        // So Scrutinizer's complaint that it can't find it is reasonable, but unfixable.
        \Jp_Graph\Jp_Graph::load();
        \Jp_Graph\Jp_Graph::module('bar');
        \Jp_Graph\Jp_Graph::module('contour');
        \Jp_Graph\Jp_Graph::module('line');
        \Jp_Graph\Jp_Graph::module('pie');
        \Jp_Graph\Jp_Graph::module('pie3d');
        \Jp_Graph\Jp_Graph::module('radar');
        \Jp_Graph\Jp_Graph::module('regstat');
        \Jp_Graph\Jp_Graph::module('scatter');
        \Jp_Graph\Jp_Graph::module('stock');
        $loaded = true;
    }
}