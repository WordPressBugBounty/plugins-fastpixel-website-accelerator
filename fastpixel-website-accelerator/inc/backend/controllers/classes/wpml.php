<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_WPML_Backend')) {
    class FASTPIXEL_WPML_Backend
    {
        protected $debug = false;
        public static $instance;
        protected $functions;
        protected $languages = [];
        protected $languages_string = '';
        protected $home_url = '';

        public function __construct()
        {
            self::$instance = $this;
            //initializing functions and config
            $this->functions = FASTPIXEL_Functions::get_instance();
            add_action('plugins_loaded', function () {
                if (defined('ICL_SITEPRESS_VERSION')) {
                    add_filter('fastpixel/backend/always_purge_url', [$this, 'modify'], 14, 2);
                    $this->languages = apply_filters('wpml_active_languages', []);
                    if (!empty($this->languages)) {
                        $this->languages_string = implode('|', array_keys($this->languages));
                        //getting home url
                        $home = get_option('home');
                        //removing trailing slash
                        if (!empty($home) && preg_match('/\/$/', $home)) {
                            $home = substr($home, 0, -1);
                        }
                        $this->home_url = $home;
                    }
                }
            });
        }

        public static function get_instance()
        {
            if (empty(self::$instance)) {
                self::$instance = new FASTPIXEL_WPML_Backend();
            }
            return self::$instance;
        }

        public function modify($url, $options)
        {
            if (empty($this->home_url) || empty($this->languages_string)) {
                return $url;
            }
            //getting url language code
            preg_match('/' . preg_quote($this->home_url, '/') . '\/('.$this->languages_string.')\/.*/i', $url, $matches);
            $language_code = !empty($matches[1]) ? $matches[1] : false;
            //getting original language code
            preg_match('/\/('.$this->languages_string.')\/.*/i', $options['original_url'], $matches);
            $original_language_code = !empty($matches[1]) ? $matches[1] : false;
            //checking if string starts from slash and checking if it contains language code
            if (!empty($language_code) && !empty($original_language_code) && $language_code != $original_language_code) {
                $url = $this->home_url . $options['original_url'];
            } else if (!empty($language_code) && empty($original_language_code)) {
                $url = $this->home_url . $options['original_url'];
            }
            return $url;
        }
    }
    new FASTPIXEL_WPML_Backend();
}
