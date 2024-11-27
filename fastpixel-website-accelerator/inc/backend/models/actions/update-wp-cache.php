<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Action_Fastpixel_Update_Wp_Cache')) {
    class FASTPIXEL_Action_Fastpixel_Update_Wp_Cache extends FASTPIXEL_Action_Model {

        public function __construct($action_name) 
        {
            parent::__construct($action_name);
        }
        public function do_action()
        {
            //manual nonce validation
            $nonce = false;
            if(isset($_REQUEST['fastpixel-nonce'])) {
                $nonce = sanitize_key($_REQUEST['fastpixel-nonce']);
            }
            if (!empty($nonce) && wp_verify_nonce($nonce, 'fastpixel_update_wp_cache') ) {
                $functions = FASTPIXEL_Functions::get_instance();
                $result = $functions->update_config_file(true);
                $notices = FASTPIXEL_Notices::get_instance();
                if ($result) {
                    define('FASTPIXEL_WP_CACHE_DEFINED', true);
                    //removing WP_CACHE notice
                    $flash_notices = $functions->get_option('fastpixel_flash_notices');
                    if (is_array($flash_notices) && !empty($flash_notices)) {
                        $_notices = [];
                        foreach($flash_notices as $notice) {
                            if (preg_match('/WP_CACHE/', $notice['notice'])) {
                                continue;
                            }
                            $_notices[] = $notice;
                        }
                        $functions->update_option('fastpixel_flash_notices', $_notices);
                    }
                    $notices->add_flash_notice(esc_html__('Wordpress wp-config.php file has been updated.', 'fastpixel-website-accelerator'), 'success');
                } else {
                    $notices->add_flash_notice(esc_html__('Cannot update wp-config.php. Please check file permissions.', 'fastpixel-website-accelerator'), 'error');
                }
            }
            $this->add_redirect(wp_get_referer());
        }
    }
}
