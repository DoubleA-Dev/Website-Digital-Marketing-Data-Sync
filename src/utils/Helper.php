<?php

namespace App\Utils;

if (! defined('ABSPATH')) {
    die('Access denied.'); // Exit if accessed directly
}

/**
 * Helper Class
 */
class Helper
{
    public static function sanitizeText(string $text): string
    {
        return htmlspecialchars(stripslashes(trim($text)));
    }
}
