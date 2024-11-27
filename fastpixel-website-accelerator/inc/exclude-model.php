<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Exclude')) {
    abstract class FASTPIXEL_Exclude
    {

        public function __construct()
        {
            $excludes_controller = FASTPIXEL_Excludes::get_instance();
            $excludes_controller->add_test($this);
        }
        abstract public function check_is_exclusion($url);
    }
}
