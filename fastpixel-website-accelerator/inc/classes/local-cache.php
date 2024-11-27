<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Local_Cache')) {
    class FASTPIXEL_Local_Cache
    {
        protected $debug = false;
        public static $instance;
        protected $functions;
        protected $config;

        public function __construct() {
            self::$instance = $this;
            //initializing functions and config
            $this->functions = FASTPIXEL_Functions::get_instance();
            $this->config    = FASTPIXEL_Config_Model::get_instance();
            //starting output beffering on fastpixel/init
            add_action('fastpixel/cachefiles/exists', function($exists = false) {
                if (!$exists) {
                    if ($this->debug) {
                        FASTPIXEL_Debug::log('Class FASTPIXEL_Local_Cache: Starting output buffering', $_SERVER['REQUEST_URI']);
                    }
                    ob_start(); //starting buffering output
                    add_action('fastpixel/shutdown/request/before', [$this, 'save'], 10, 1); //saving buffer, if page passed validation
                }
            });
            add_action('fastpixel/cachefiles/saved', [$this, 'delete_file_on_api_request'], 10, 1);
            add_action('fastpixel/post/trashed', [$this, 'delete_file_on_trashed'], 10, 1);
            add_action('fastpixel/admin/purge_cache_by_url', [$this, 'delete_file_on_purge'], 10, 1);
            add_action('fastpixel/admin/purge_cache_by_id', [$this, 'delete_file_on_purge'], 10, 1);
        }

        public static function get_instance()
        {
            if (empty(self::$instance)) {
                self::$instance = new FASTPIXEL_Local_Cache();
            }
            return self::$instance;
        }
      

        public function save($url)
        {
            if (empty($url) || !is_a($url, 'FASTPIXEL\FASTPIXEL_Url')) {
                if ($this->debug) {
                    FASTPIXEL_Debug::log('Class FASTPIXEL_Local_Cache: Url is empty or not instance of FASTPIXEL\FASTPIXEL_Url');
                }
                return false;
            }
            if (ob_get_level() == 0) {
                if ($this->debug) {
                    FASTPIXEL_Debug::log('Class FASTPIXEL_Local_Cache: ob_get_level returned 0, seems that buffer wasn\'t started');
                }
                return false;
            }
            //getting buffered output
            $page_content = ob_get_contents();
            if ($this->debug) {
                FASTPIXEL_Debug::log('Class FASTPIXEL_Local_Cache: Getting output buffer', strlen($page_content));
            }
            if (strlen($page_content) > 0) {
                $this->file($url, 'add', $page_content);
            } else {
                if ($this->debug) {
                    FASTPIXEL_Debug::log('Class FASTPIXEL_Local_Cache: Buffer is empty');
                }
            }
            ob_end_flush();
        }

        protected function file($url, $action = 'add', $data = '')
        {
            $path = $this->functions->check_path($url);
            if (!$path) {
                return false;
            }
            $modified_time = time();

            //initializing filesystem
            global $wp_filesystem;
            if (empty($wp_filesystem)) {
                require_once ABSPATH . '/wp-admin/includes/file.php';
                WP_Filesystem();
            }
            $local_path = $path . DIRECTORY_SEPARATOR . 'index_local.html';
            switch ($action) {
                case 'add':
                    if (empty($data)) {
                        if ($this->debug) {
                            FASTPIXEL_Debug::log('Class FASTPIXEL_Local_Cache: Buffer is empty, exit');
                        }
                        return false;
                    }
                    // Save the response body.
                    if (!$wp_filesystem->put_contents($local_path, $data)) {
                        if ($this->debug) {
                            FASTPIXEL_Debug::log('Class FASTPIXEL_Local_Cache: Error occured while saving Buffer to file');
                        }
                    }
                    $wp_filesystem->touch($local_path, $modified_time);
                    if ($this->debug) {
                        FASTPIXEL_Debug::log('Class FASTPIXEL_Local_Cache: Saved output buffer to $path', $local_path);
                    }
                    break;
                case 'delete':
                    if ($wp_filesystem->exists($local_path)) {
                        $wp_filesystem->delete($local_path, false, 'f');
                    }
                    break;
            }
            return true;
        }

        public function delete_file_on_api_request($url) {
            if ($this->debug) {
                FASTPIXEL_Debug::log('Class FASTPIXEL_Local_Cache: Deleting local cache on rest-api request');
            }
            $this->file($url, 'delete');
        }

        public function delete_file_on_purge($url)
        {
            if ($this->debug) {
                FASTPIXEL_Debug::log('Class FASTPIXEL_Local_Cache: Deleting local cache on post cache purge');
            }
            $this->file($url, 'delete');
        }
        public function delete_file_on_trashed($post_id)
        {
            if ($this->debug) {
                FASTPIXEL_Debug::log('Class FASTPIXEL_Local_Cache: Deleting local cache on post cache purge');
            }
            if (!empty($post_id) && is_numeric($post_id)) {
                $url = get_permalink($post_id);
                if ($url) {
                    $this->file($url, 'delete');
                }
            }
        }
    }
    new FASTPIXEL_Local_Cache();
}