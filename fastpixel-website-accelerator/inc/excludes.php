<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Excludes')) {
    class FASTPIXEL_Excludes {
        public static $instance;
        protected $tests = [];

        public function __construct() {
            self::$instance = $this;
            $this->load_excludes();
        }

        public static function get_instance() {
            if (empty(self::$instance)) {
                self::$instance = new FASTPIXEL_Excludes();
            }
            return self::$instance;
        }

        protected function load_excludes()
        {
            if (file_exists(FASTPIXEL_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'excludes') && is_dir(FASTPIXEL_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'excludes')) {
                if ($handle = opendir(FASTPIXEL_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'excludes')) {
                    while (false !== ($entry = readdir($handle))) {
                        if (!in_array($entry, ['.', '..'])) {
                            include_once FASTPIXEL_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'excludes' . DIRECTORY_SEPARATOR . $entry;
                        }
                    }
                    closedir($handle);
                }
            }
        }

        public function add_test($test) {
            $this->tests[] = $test;
        }

        public function check_is_exclusion($url) {
            if (empty($url)) {
                return false;
            }
            $url = new FASTPIXEL_Url($url);
            foreach ($this->tests as $test) {
                $test_result = $test->check_is_exclusion($url);
                if ($test_result) {
                    return true;
                }
            }
            return false;
        }
    }
    new FASTPIXEL_Excludes();
}
