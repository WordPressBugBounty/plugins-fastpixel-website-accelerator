<?php
namespace FASTPIXEL;

use Exception;
defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Diag_Test_Wp_Cache')) {
    class FASTPIXEL_Diag_Test_Wp_Cache extends FASTPIXEL_Diag_Test 
    {
        protected $order_id = 16;
        protected $name = 'WP_CACHE Constant';
        protected $functions;

        public function __construct()
        {
            parent::__construct();
            $this->functions = FASTPIXEL_Functions::get_instance();
        }

        public function test() {
            $result = $this->check_wp_config_directly();
            if ($result['wp_cache'] == true) {
                $this->passed = true;
                return;
            }
            if ((defined('WP_CACHE') && WP_CACHE == true) || (defined('FASTPIXEL_WP_CACHE_DEFINED'))) {
                $this->passed = true;
                return;
            }
            $update_wp_cache_url = admin_url('admin.php?page=' . FASTPIXEL_TEXTDOMAIN . '&fastpixel-action=fastpixel_update_wp_cache&fastpixel-nonce=' . wp_create_nonce('fastpixel_update_wp_cache'));
            if ($result['writable'] == false) {
                /* translators: %s should be an url */
                $message = esc_html__('Plugin is not working because WP_CACHE is not defined or set to FALSE. Due to wp-config.php is not writeable please enable WP_CACHE manually', 'fastpixel-website-accelerator');
                $this->add_notification_message($message, 'error');
            } else {
                $this->functions->update_config_file(true);
            }
        }

        protected function check_wp_config_directly() {
            //initializing filesystem
            global $wp_filesystem;
            if (empty($wp_filesystem)) {
                require_once ABSPATH . '/wp-admin/includes/file.php';
                WP_Filesystem();
            }
            $functions = FASTPIXEL_Functions::get_instance();
            $ret = [
                'wp_cache' => false,
                'writable' => true
            ];
            if ($functions->get_wp_config_path() && $functions->get_match_regexp()) {
                if (file_exists($functions->get_wp_config_path())) {
                    $wp_config_content = $wp_filesystem->get_contents($functions->get_wp_config_path());
                    preg_match_all($functions->get_match_regexp(), $wp_config_content, $matches);
                    //checking if entry exists
                    if (!empty($matches[0][0]) && !empty($matches[1][0])) {
                        $ret['wp_cache'] = preg_match('/true/i', $matches[1][0]) ? true : false;
                    }
                    //trying to open it for write for check
                    try {
                        $f = $wp_filesystem->is_writable($functions->get_wp_config_path());
                        if (false === $f) {
                            throw new Exception('Cant open file for writing');
                        } 
                    } catch (Exception $e) {
                        $ret['writable'] = false;
                    }
                }
            }
            return $ret;
        }

        public function l10n_name()
        {
            $this->name = esc_html__('WP_CACHE Constant', 'fastpixel-website-accelerator');
        }
    }
    new FASTPIXEL_Diag_Test_Wp_Cache();
}
