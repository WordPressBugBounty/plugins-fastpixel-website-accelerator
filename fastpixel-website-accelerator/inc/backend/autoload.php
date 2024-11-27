<?php
defined('ABSPATH') || exit;

//include models
$models = array(
    FASTPIXEL_PLUGIN_DIR . '/inc/backend/models/api-key.php',
    FASTPIXEL_PLUGIN_DIR . '/inc/backend/models/action.php',
    FASTPIXEL_PLUGIN_DIR . '/inc/backend/models/diag.php',
    FASTPIXEL_PLUGIN_DIR . '/inc/backend/models/ui-tab.php',
    FASTPIXEL_PLUGIN_DIR . '/inc/backend/models/ui.php',
    
    //include posts table model
    FASTPIXEL_PLUGIN_DIR . '/inc/backend/models/posts-ui.php'
);
foreach($models as $model) {
    if (file_exists($model)) {
        include_once $model;
    }
}

//include action models
if ($handle = opendir(FASTPIXEL_PLUGIN_DIR . '/inc/backend/models/actions/')) {
    while (false !== ($entry = readdir($handle))) {
        if (!in_array($entry, ['.', '..'])) {
            try {
                include_once FASTPIXEL_PLUGIN_DIR . '/inc/backend/models/actions/' . $entry;
            } catch(Exception $e) {
                FASTPIXEL\FASTPIXEL_Debug::log('Error occure while including action model -> ' . $e->getMessage());
            }
        }
    }
    closedir($handle);
}

//include controllers
$controllers = array(
    FASTPIXEL_PLUGIN_DIR . '/inc/backend/controllers/functions.php',
    FASTPIXEL_PLUGIN_DIR . '/inc/backend/controllers/notices.php',
    FASTPIXEL_PLUGIN_DIR . '/inc/backend/controllers/controller.php',
    FASTPIXEL_PLUGIN_DIR . '/inc/backend/controllers/actions.php',
    //ui controllers
    FASTPIXEL_PLUGIN_DIR . '/inc/backend/controllers/ui-single.php',
    FASTPIXEL_PLUGIN_DIR . '/inc/backend/controllers/ui-multi.php',
    FASTPIXEL_PLUGIN_DIR . '/inc/backend/controllers/ui.php',

    FASTPIXEL_PLUGIN_DIR . '/inc/backend/controllers/cache.php',
    FASTPIXEL_PLUGIN_DIR . '/inc/backend/controllers/diag.php'
);
foreach ($controllers as $controller) {
    if (file_exists($controller)) {
        include_once $controller;
    }
}

//include classes
if ($handle = opendir(FASTPIXEL_PLUGIN_DIR . '/inc/backend/controllers/classes/')) {
    while (false !== ($entry = readdir($handle))) {
        if (!in_array($entry, ['.', '..'])) {
            try {
                include_once FASTPIXEL_PLUGIN_DIR . '/inc/backend/controllers/classes/' . $entry;
            } catch(Exception $e) {
                FASTPIXEL\FASTPIXEL_Debug::log('Error occure while including class -> ' . $e->getMessage());
            }
        }
    }
    closedir($handle);
}
