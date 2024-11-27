<?php
namespace FASTPIXEL;

use FASTPIXEL\FASTPIXEL_UI_Single;
use FASTPIXEL\FASTPIXEL_UI_Multi;

defined('ABSPATH') || exit;

add_action('init', function () {
    if (is_multisite()) {
        new FASTPIXEL_UI_Multi();
    } else {
        new FASTPIXEL_UI_Single();
    }
});
