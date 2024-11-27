<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Tab_Cache_Status')) {
    class FASTPIXEL_Tab_Cache_Status extends FASTPIXEL_UI_Tab {
        
        protected $slug = 'cache-status';
        protected $order = 2;
        private $table;

        public function __construct() {
            parent::__construct();
            $this->name = esc_html__('Cache Status', 'fastpixel-website-accelerator');
            $this->table = new FASTPIXEL_Posts_Table();
        }

        public function settings() {}

        public function get_table() {
            return $this->table;
        }
        public function get_link()
        {
            return esc_url(admin_url('admin.php?page=' . FASTPIXEL_TEXTDOMAIN));
        }
    }
    new FASTPIXEL_Tab_Cache_Status();
}
