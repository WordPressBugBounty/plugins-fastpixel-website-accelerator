<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Diag_Test_Ac_File')) {
    class FASTPIXEL_Diag_Test_Ac_File extends FASTPIXEL_Diag_Test 
    {
        protected $order_id = 10;
        protected $name = 'Advanced Cache File';
        protected $activation_check = true;
        protected $display_notifications = true;
        protected $functions;

        public function __construct() {
            parent::__construct();
            $this->functions = FASTPIXEL_Functions::get_instance();
        }

        public function test() {
            $generate_ac_file_url = esc_url(admin_url('admin.php?page=' . FASTPIXEL_TEXTDOMAIN . '-settings&fastpixel-action=fastpixel_generate_ac&fastpixel-nonce=' . wp_create_nonce('fastpixel_generate_ac')));
            if (file_exists(WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'advanced-cache.php')) {
                $ac_content = file_get_contents(WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'advanced-cache.php');
                if (preg_match('/FASTPIXEL_ADVANCED_CACHE/s', $ac_content) && preg_match('/FASTPIXEL_Config_Model/s', $ac_content)) {
                    $this->passed = true;
                } else if (preg_match('/FASTPIXEL_ADVANCED_CACHE/s', $ac_content) && !preg_match('/FASTPIXEL_Config_Model/s', $ac_content)) {
                    /* replacing advanced-cache */
                    $this->functions->update_ac_file();
                } else {
                    /* replacing advanced-cache */
                    $this->functions->update_ac_file();
                }
            } else {
                /* adding advanced-cache */
                $this->functions->update_ac_file();
            }
        }

        public function activation_test() {
            if (!file_exists(WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'advanced-cache.php') || strlen(file_get_contents(WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'advanced-cache.php')) == 0) {
                return true;
            } else {
                $ac_content = file_get_contents(WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'advanced-cache.php');
                if (preg_match('/FASTPIXEL_ADVANCED_CACHE/s', $ac_content)) {
                    return true;
                } else {
                    return false;
                }
            }
            return true;
        }

        public function l10n_name() {
            $this->name = esc_html__('Advanced Cache File', 'fastpixel-website-accelerator');
        }
    }
    new FASTPIXEL_Diag_Test_Ac_File();
}
