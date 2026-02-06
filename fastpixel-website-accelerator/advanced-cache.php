<?php
defined( 'ABSPATH' ) or die( 'no jokes please' );
define('FASTPIXEL_ADVANCED_CACHE', TRUE);

if(!defined('FASTPIXEL_PLUGIN_DIR')){
    define('FASTPIXEL_PLUGIN_DIR', '%%FASTPIXEL_PLUGIN_DIR%%');
    define('FASTPIXEL_TEXTDOMAIN', '%%FASTPIXEL_TEXTDOMAIN%%');
    define('FASTPIXEL_LOGGED_IN_COOKIE', '%%FASTPIXEL_LOGGED_IN_COOKIE%%');
    if (!defined('FASTPIXEL_API_HOST')) define('FASTPIXEL_API_HOST', '%%FASTPIXEL_API_HOST%%');
    define('FASTPIXEL_REST_URL', '%%FASTPIXEL_REST_URL%%');
    if (!defined('FASTPIXEL_DEBUG'))    define('FASTPIXEL_DEBUG', '%%FASTPIXEL_DEBUG%%');
    define('FASTPIXEL_USE_SK', '%%FASTPIXEL_USE_SK%%');
    define('FASTPIXEL_CACHE_DIR', '%%FASTPIXEL_CACHE_DIR%%');
    if (!is_admin() && file_exists(FASTPIXEL_PLUGIN_DIR)) {
        $includes = [
            'FASTPIXEL\FASTPIXEL_Debug'        => 'debug.php',
            'FASTPIXEL\FASTPIXEL_Functions'    => 'functions.php',
            'FASTPIXEL\FASTPIXEL_Url'          => 'url.php',
            'FASTPIXEL\FASTPIXEL_Config_Model' => 'config-model.php',
            'FASTPIXEL\FASTPIXEL_Diag_Test'    => 'backend/models/diag.php',
            'FASTPIXEL\FASTPIXEL_Diag'         => 'backend/controllers/diag.php',
            'FASTPIXEL\FASTPIXEL_Cache'        => 'cache.php',
        ];
        foreach ($includes as $class_name => $class_path) {
            if (!class_exists($class_name) && file_exists(FASTPIXEL_PLUGIN_DIR . '/inc/' . $class_path)) {
                require_once(FASTPIXEL_PLUGIN_DIR . '/inc/' . $class_path);
            }
        }
        if (class_exists('FASTPIXEL\FASTPIXEL_Cache')) {
            $fastpixel_cache = FASTPIXEL\FASTPIXEL_Cache::get_instance();
        }
    }
}