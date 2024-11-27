<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Backend_Controller')) {
    class FASTPIXEL_Backend_Controller {

        protected $functions;
        protected $notices;

        public function __construct()
        {
            $this->functions = FASTPIXEL_Functions::get_instance();
            $this->notices = FASTPIXEL_Notices::get_instance();
        }

        public function check_capabilities()
        {
            // check user capabilities
            if (!current_user_can('manage_options')) {
                return false;
            }
            return true;
        }
        protected function do_redirect($redirect = 'self')
        {
            if (empty($redirect) || $redirect == 'self') {
                $url = admin_url('admin.php?page=' . FASTPIXEL_TEXTDOMAIN);
                $url = remove_query_arg('fastpixel-action', $url); // has url
            } else {
                $url = $redirect;
            }
            wp_redirect(esc_url_raw($url));
            exit();
        }
    }
    new FASTPIXEL_Backend_Controller();
}
