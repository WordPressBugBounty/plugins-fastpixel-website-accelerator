<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Cron_Jobs')) {
    class FASTPIXEL_Cron_Jobs
    {
        protected $debug = false;
        public static $instance;
        protected $functions;
        protected $config;
        protected $cron_path;
        protected $cron_wait_time = 60 * 10; // 10 mins
        public function __construct() {
            self::$instance = $this;
            if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
                return; // If WP Cron is disabled, do not proceed
            }
            //initializing functions and config
            $this->functions = FASTPIXEL_Functions::get_instance();
            $this->config    = FASTPIXEL_Config_Model::get_instance();
            $this->cron_path = $this->functions->get_cache_dir() . DIRECTORY_SEPARATOR . 'cron';

            if (defined('DOING_CRON') && DOING_CRON) {
                $this->update_timestamp();
            } else {
                $this->check_timestamp();
            }
        }

        public static function get_instance()
        {
            if (empty(self::$instance)) {
                self::$instance = new FASTPIXEL_Cron_Jobs();
            }
            return self::$instance;
        }

        protected function update_timestamp() {
            if (!file_exists($this->functions->get_cache_dir())) {
                return false;
            }
            file_put_contents($this->cron_path, json_encode(['time' => gmdate('Y-m-d H:i:s', time())]));
        }

        protected function check_timestamp() {
            $filemtime = file_exists($this->cron_path) ? filemtime($this->cron_path) : 0;
            if (time() > ($filemtime + $this->cron_wait_time)) {
                add_filter('fastpixel/cache_files/run_cron', function () {
                    return true;
                });
            }
        }
    }
    new FASTPIXEL_Cron_Jobs();
}
