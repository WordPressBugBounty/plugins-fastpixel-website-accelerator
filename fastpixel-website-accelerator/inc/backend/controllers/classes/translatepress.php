<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_TranslatePress_Backend')) {
    class FASTPIXEL_TranslatePress_Backend
    {
        protected $debug = false;
        public static $instance;
        protected $functions;
        protected $enabled = false;
        protected $trp;
        protected $published_languages = [];
        protected $selected_language;
        protected $url_converter;

        public function __construct()
        {
            self::$instance = $this;
            //initializing functions and config
            $this->functions = FASTPIXEL_Functions::get_instance();
        
            add_action('plugins_loaded', function () {
                if (class_exists('TRP_Translate_Press')) {
                    $this->enabled = true;
                    $this->trp = \TRP_Translate_Press::get_trp_instance();
                    if (method_exists($this->trp, 'get_component')) {
                        $trp_languages = $this->trp->get_component('languages');
                        $trp_settings = $this->trp->get_component('settings');
                        $this->url_converter = $this->trp->get_component('url_converter');
                        if (empty($trp_languages || empty($trp_settings) || empty($this->url_converter))) {
                            return;
                        }
                        if (method_exists($trp_languages, 'get_language_names')) {
                            $this->published_languages = $trp_languages->get_language_names($trp_settings->get_settings()['publish-languages']);
                        }
                        if (method_exists($trp_settings, 'get_settings')) {
                            $this->selected_language = $trp_settings->get_settings()['default-language'];
                        }
                        if (empty($this->published_languages) || empty($this->selected_language)) {
                            return;
                        }
                        if (!empty($_REQUEST['fastpixel_trp_language'])) {
                            $this->selected_language = sanitize_text_field($_REQUEST['fastpixel_trp_language']); //phpcs:ignore
                        }
                        //adding language selector for status page
                        add_action('fastpixel/status_page/extra_filters', [$this, 'add_language_selector'], 10, 1);
                        //adding url conversion for status page
                        add_action('fastpixel/status_page/permalink', [$this, 'convert_url'], 10, 1);
                        //adding url conversion for status page when ajax status check
                        add_action('fastpixel/status_page/ajax/permalink', [$this, 'convert_url_ajax'], 10, 2);
                        //adding extra javascript params for status page
                        add_filter('fastpixel/status_page/extra_params', [$this, 'javascript_parameters'], 10, 1);

                        //adding url conversion for backend single purge by url
                        add_filter('fastpixel/backend/purge/single/permalink', [$this, 'purge_cache_by_url'], 12, 2);
                        //adding url conversion for backend single purge by id (post, term)
                        add_filter('fastpixel/purge_post_object/url', [$this, 'convert_url_ajax'], 12, 2);
                        add_filter('fastpixel/purge_term_object/url', [$this, 'convert_url_ajax'], 12, 2);
                    }
                }
            });
        }

        public static function get_instance()
        {
            if (empty(self::$instance)) {
                self::$instance = new FASTPIXEL_TranslatePress_Backend();
            }
            return self::$instance;
        }

        public function add_language_selector($html)
        {
            if (!empty($this->trp)) {
                $selector_html = '<select name="fastpixel_trp_language" id="fastpixel_trp_language">';
                foreach ($this->published_languages as $code => $name) {
                    $selector_html .= '<option value="' . $code . '" ' . (strtolower($code) == strtolower($this->selected_language) ? 'selected="selected"' : '') . '>' . $name . '</option>';
                }
                $selector_html .= '</select>';
                $html .= $selector_html;
            }
            return $html;
        }

        public function convert_url($url, $language = null)
        {
            if (empty($language)) {
                $language = $this->selected_language;
            }
            if (!empty($this->url_converter) && method_exists($this->url_converter, 'get_url_for_language')) {
                $url = $this->url_converter->get_url_for_language($language, $url, false);
            }
            return $url;
        }

        public function convert_url_ajax($url, $data) {
            if (!empty($data['extra_params']['fastpixel_trp_language'])) {
                $url = $this->convert_url($url, $data['extra_params']['fastpixel_trp_language']);
            }
            return $url;
        }

        public function purge_cache_by_url($url, $data)
        {
            if (!empty($data['extra_params']['fastpixel_trp_language'])) {
                $url = $this->convert_url($url, $data['extra_params']['fastpixel_trp_language']);
            }
            return $url;
        }

        public function javascript_parameters($params) {
            $params = [
                'fastpixel_trp_language' => $this->selected_language,
            ];
            return $params;
        }
    }
    new FASTPIXEL_TranslatePress_Backend();
}
