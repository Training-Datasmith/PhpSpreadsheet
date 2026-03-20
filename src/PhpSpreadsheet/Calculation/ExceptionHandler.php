<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation;

class Exception_Handler
{
    /**
     * Register errorhandler.
     */
    public function __construct()
    {
        /** @var callable $callable */
        $callable = Exception::error_handler_callback(...);
        set_error_handler($callable, E_ALL);
    }
    /**
     * Unregister errorhandler.
     */
    public function __destruct()
    {
        restore_error_handler();
    }
}