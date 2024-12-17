<?php
defined('ABSPATH') || exit;
add_action('init', function () {
    load_plugin_textdomain('fastpixel-website-accelerator', false, plugin_basename(FASTPIXEL_PLUGIN_DIR) . DIRECTORY_SEPARATOR . 'languages');
});
if (file_exists(FASTPIXEL_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php')) {
    require FASTPIXEL_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
}
$includes = [
    'FASTPIXEL\FASTPIXEL_Debug'        => 'debug.php',
    'FASTPIXEL\FASTPIXEL_Url'          => 'url.php',
    'FASTPIXEL\FASTPIXEL_Functions'    => 'functions.php',
    'FASTPIXEL\FASTPIXEL_Config_Model' => 'config-model.php',
    'FASTPIXEL\FASTPIXEL_Cache'        => 'cache.php',
    'FASTPIXEL\FASTPIXEL_Plugin'       => 'plugin.php'
];
foreach ($includes as $class_name => $class_path) {
    if (!class_exists($class_name) && file_exists(FASTPIXEL_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . $class_path)) {
        require_once FASTPIXEL_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . $class_path;
    }
}
//require api functions
// // rest_api_init not called if permalinks set to 'Plain'
add_action('rest_api_init', function () {
    if (file_exists(FASTPIXEL_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'rest-api.php')) {
        require FASTPIXEL_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'rest-api.php';
    }
});
//require backend functions
if (file_exists(FASTPIXEL_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'autoload.php')) {
    require FASTPIXEL_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'autoload.php';
}
