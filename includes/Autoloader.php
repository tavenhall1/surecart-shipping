<?php
/**
 * Autoloader for plugin classes.
 *
 * @package SureCartShippo
 */

namespace SureCartShippo;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Autoloader class.
 */
class Autoloader
{
    /**
     * Register the autoloader.
     */
    public static function register()
    {
        spl_autoload_register([__CLASS__, 'autoload']);
    }

    /**
     * Autoload classes.
     *
     * @param string $class The class name.
     */
    public static function autoload($class)
    {
        // Check if the class is in our namespace.
        $prefix = 'SureCartShippo\\';
        $len = strlen($prefix);

        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }

        // Get the relative class name.
        $relative_class = substr($class, $len);

        // Replace namespace separators with directory separators.
        $file = str_replace('\\', '/', $relative_class) . '.php';

        // Build the full file path.
        $path = SURECART_SHIPPO_PLUGIN_DIR . 'includes/' . $file;

        // If the file exists, require it.
        if (file_exists($path)) {
            require_once $path;
        }
    }
}
