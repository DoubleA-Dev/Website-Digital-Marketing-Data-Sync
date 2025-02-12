<?php

namespace App\Utils;

use Monolog\Logger;

if (! defined('ABSPATH')) {
    die('Access denied.'); // Exit if accessed directly
}

/**
 * Log Trait
 */
trait LogTrait
{
    private Logger $log;

    public function __construct($logger)
    {
        $this->setLogger($logger);
    }

    private function setLogger(Logger $logger): void
    {
        $this->log = $logger;
    }
}
