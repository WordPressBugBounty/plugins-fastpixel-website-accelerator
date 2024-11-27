<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Action_Fastpixel_Generate_Ac')) {
    class FASTPIXEL_Action_Fastpixel_Generate_Ac extends FASTPIXEL_Action_Model {

        public function __construct($action_name) 
        {
            parent::__construct($action_name);
        }
        public function do_action()
        {
            //manual nonce validation
            $nonce = false;
            if (isset($_REQUEST['fastpixel-nonce'])) {
                $nonce = sanitize_key($_REQUEST['fastpixel-nonce']);
            }
            if (!empty($nonce) && wp_verify_nonce($nonce, 'fastpixel_generate_ac') ) {
                $functions = FASTPIXEL_Functions::get_instance();
                $result = $functions->update_ac_file();
                $notices = FASTPIXEL_Notices::get_instance();
                if ($result) {
                    $notices->add_flash_notice(esc_html__('The new FastPixel add-on file advanced-cache.php has been generated.', 'fastpixel-website-accelerator'), 'success');
                } else {
                    $notices->add_flash_notice(esc_html__('The advanced-cache.php add-on file cannot be created.', 'fastpixel-website-accelerator'), 'error');
                }
            }
            $this->add_redirect(wp_get_referer());
        }
    }
}
