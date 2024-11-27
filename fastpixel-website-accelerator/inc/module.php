<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Module')) {
    abstract class FASTPIXEL_Module
    {
        protected $enabled = false;

        public function __construct() {
            $config_instance = FASTPIXEL_Config_Model::get_instance();
            $enabled_modules = $config_instance->get_option('fastpixel_enabled_modules');
            if (in_array(get_class($this), $enabled_modules)) {
                $this->enabled = true;
            }
            //init should be executed immidiately
            $this->init();
        }
        abstract public function init();
    }
}
