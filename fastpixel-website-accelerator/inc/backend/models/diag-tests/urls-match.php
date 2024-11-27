<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Diag_Test_Urls_Match')) {
    class FASTPIXEL_Diag_Test_Urls_Match extends FASTPIXEL_Diag_Test
    {
        protected $order_id = 18;
        protected $name = 'Urls Match';
        protected $display_notifications = true;
        protected $rest_url = '';
        protected $visible_on_diagnostics_page = false;


        public function __construct()
        {
            parent::__construct();
        }

        public function test()
        {
            $this->skip_url_match_option(); //update option
            if (is_multisite()) { //don't check domain match if multisite
                $this->passed = true;
            } else if ($this->is_wpml()) { //don't check domain match if wpml
                $this->passed = true;
            } else {
                if (defined('FASTPIXEL_REST_URL')) {
                    $this->rest_url = FASTPIXEL_REST_URL;
                } else if (function_exists('get_rest_url')) {
                    $this->rest_url = get_rest_url(get_current_blog_id(), FASTPIXEL_TEXTDOMAIN . '/v1/update');
                }
                $rest_url = wp_parse_url($this->rest_url, PHP_URL_HOST);
                $siteurl = wp_parse_url(get_site_url(), PHP_URL_HOST);
                if ($rest_url == $siteurl || preg_match('/^'.$siteurl.'/i', $rest_url)) {
                    $this->passed = true;
                } else {
                    /* translators: %1$s and %2$s should be a domain names */
                    $this->add_notification_message(sprintf(esc_html__('Callback domain (%1$s) doesn\'t match site domain (%2$s). FastPixel cannot handle requests with different domains.', 'fastpixel-website-accelerator'), esc_url($rest_url), esc_url($siteurl)), 'error');
                }
            }
        }

        public function l10n_name()
        {
            $this->name = esc_html__('Urls Match', 'fastpixel-website-accelerator');
        }

        protected function is_wpml() {
            $functions = FASTPIXEL_Functions::get_instance();
            if (defined('ICL_SITEPRESS_VERSION')) {
                $icl_sitepress_settings = get_option('icl_sitepress_settings', []);
                if (isset($icl_sitepress_settings['language_negotiation_type']) && $icl_sitepress_settings['language_negotiation_type'] == 2) {
                    return true; //WPML url format is "different domain"
                }            
            }
            return false;
        }

        protected function skip_url_match_option() {
            $functions = FASTPIXEL_Functions::get_instance();
            if ($this->is_wpml()) {
                if (!$functions->get_option('fastpixel_skip_url_match', false)) { //setting SKIP option to true
                    $functions->update_option('fastpixel_skip_url_match', true);
                }
            } else {
                if ($functions->get_option('fastpixel_skip_url_match', false)) { //setting SKIP option to false
                    $functions->update_option('fastpixel_skip_url_match', false);
                }
            }
        }
    }
    new FASTPIXEL_Diag_Test_Urls_Match();
}
