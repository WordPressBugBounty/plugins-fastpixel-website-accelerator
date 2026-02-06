<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Tab_Statistics')) {
    class FASTPIXEL_Tab_Statistics extends FASTPIXEL_UI_Tab
    {
        protected $name = 'Statistics';
        protected $slug = 'statistics';
        protected $order = 12;

        public function __construct() {
            parent::__construct();
            $this->name = esc_html__('Statistics', 'fastpixel-website-accelerator');
        }
        public function settings() {
            if (!$this->check_capabilities()) {
                return;
            }
        }
        
        public function get_settings()
        {
            return [];
        }
    }
    new FASTPIXEL_Tab_Statistics();
}
