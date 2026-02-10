<?php
/**
 * Plugin Name: FastPixel Website Accelerator
 * Plugin URI:  https://fastpixel.io
 * Description: Faster WordPress Made Easy – Solve all your website speed problems effortlessly with just a few clicks.
 * Author:      ShortPixel
 * Version:     1.2.3
 * Text Domain: fastpixel-website-accelerator
 * Domain Path: /languages
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
 * Author URI:  https://shortpixel.com
 * Network:     true
 *
 * @package   fastpixel
 * @copyright 2024 fastpixel.io
 * @license   GPLv2 or later
 * @link      https://fastpixel.io
 */

defined('ABSPATH') || exit;

define('FASTPIXEL_VERSION', '1.2.3');
define('FASTPIXEL_NAME', 'FastPixel');
if (!defined('FASTPIXEL_PLUGIN_DIR'))
    define('FASTPIXEL_PLUGIN_DIR', __DIR__);
if (!defined('FASTPIXEL_PLUGIN_URL'))
    define('FASTPIXEL_PLUGIN_URL', plugin_dir_url(__FILE__));
if (!defined('FASTPIXEL_PLUGIN_FILE'))
    define('FASTPIXEL_PLUGIN_FILE', __FILE__);
if (!defined('FASTPIXEL_TEXTDOMAIN'))
    define('FASTPIXEL_TEXTDOMAIN', 'fastpixel-website-accelerator');
if (!defined('FASTPIXEL_API_HOST'))
    define('FASTPIXEL_API_HOST', 'https://api.fastpixel.io');
if (!defined('FASTPIXEL_DEBUG'))
    define('FASTPIXEL_DEBUG', false); //15 all, false no logging, first two bits for destination, (1 log to system default, 3 log to file) then add 4 for front and 8 for request
if (!defined('FASTPIXEL_USE_SK'))
    define('FASTPIXEL_USE_SK', true);
if( !defined('FASTPIXEL_CACHE_DIR')) {
    //this is also defined in debug.php for the upgrade from 1.1.0 to 1.2.0
    define('FASTPIXEL_CACHE_DIR', rtrim(WP_CONTENT_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . FASTPIXEL_TEXTDOMAIN);
}
if (defined('FASTPIXEL_ADVANCED_CACHE') && defined('FASTPIXEL_PLUGIN_DIR') && !file_exists(FASTPIXEL_PLUGIN_DIR . '/inc/autoload.php')) {
    if (file_exists(WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'advanced-cache.php') && function_exists('wp_delete_file')) {
        wp_delete_file(WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'advanced-cache.php');
    }
    return;
}
require_once FASTPIXEL_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'autoload.php';
