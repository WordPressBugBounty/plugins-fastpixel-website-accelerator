<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Url')) {
    class FASTPIXEL_Url {
        
        protected $debug = false;
        private $params_to_trim = [
            "fastpixeldebug",
            "fastpixeldisable",
            "nocache",
            "epc_nocache"
        ];
        //some known tracking params list
        private $tracking_params = [
            // Google Analytics (utm_)
            "utm_source",
            "utm_medium",
            "utm_campaign",
            "utm_term",
            "utm_content",

            //Google Merchant
            "srsltid",

            // HubSpot (hsa_)
            "hsa_acc",
            "hsa_ad",
            "hsa_cam",
            "hsa_grp",
            "hsa_kw",
            "hsa_mt",
            "hsa_net",
            "hsa_src",
            "hsa_tgt",

            // Facebook (fb_)
            "fbclid",
            "fb_source",
            "fb_ref",
            "fb_action_ids",
            "fb_action_types",
            "fb_campaign_id",

            // Twitter (tw_)
            "tw_campaign_name",
            "tw_campaign_id",
            "tw_creator",
            "tw_keyword",
            "tw_matchtype",
            "tw_network",
            "tw_placement",
            "tw_targeting_criteria",

            // LinkedIn (li_)
            "li_campaign",
            "li_source",
            "li_medium",
            "li_content",
            "li_term",

            // Bing (msclkid)
            "msclkid",

            // Pinterest (referrer)
            "referrer",

            // Snapchat (sc_)
            "sc_aadid",
            "sc_adset",
            "sc_campaign",
            "sc_cid",
            "sc_content",
            "sc_country",
            "sc_creative",
            "sc_l",
            "sc_medium",
            "sc_publisher",
            "sc_segment",
            "sc_source",
            "sc_term",
            "sc_video_id",

            // TikTok (tt_)
            "tt_campaign",
            "tt_content_id",
            "tt_medium",
            "tt_source",
            "tt_term",
            "tt_video_id",

            // Amazon (amzn_)
            "amzn_bkgd",
            "amzn_bkmk",
            "amzn_brand",
            "amzn_comp",
            "amzn_osd",
            "amzn_pdr",
            "amzn_ref",
            "amzn_slot",
            "amzn_wdgt",

            // Matomo (pk_)
            "pk_campaign",
            "pk_kwd",
            "pk_keyword",
            "pk_medium",
            "pk_source",
            "pk_content",
            "pk_cid",
            "pk_cpn",
            "pk_adid",

            // Mailchimp (mc_)
            "mc_cid",
            "mc_eid",

            // Crazy Egg (ce_)
            "ce_creative",
            "ce_variant",
            "ce_experiment_id",
            "ce_experiment_variation_id",

            // Kissmetrics (k_)
            "k_vid",
            "k_key",
            "kme",
            "kmc",
            "kma",
            "kmw",

            // Adobe Analytics (cid)
            "cid"
        ];
        private $original_url;
        private $url;
        private $scheme;
        private $port;
        private $host;
        private $path;
        private $query;
        private $url_path;
        protected $strip_params;

        public function __construct($url = null, $strip_params = false) {
            if (defined('WP_CLI')) {
                return;
            }
            $this->strip_params = $strip_params;
            if (!empty($url) && is_numeric($url) && function_exists('get_permalink')) {
                $this->original_url = get_permalink($url);
                if ($this->debug) {
                    FASTPIXEL_DEBUG::log('Class FASTPIXEL_Url: Getting URL by $post_id', $this->original_url);
                }
            } else if (is_string($url) && !empty($url) && filter_var($url, FILTER_VALIDATE_URL)) {
                $this->original_url = $url;
            } else {
                $this->original_url = $this->get_data_from_request();
                if ($this->debug) {
                    FASTPIXEL_DEBUG::log('Class FASTPIXEL_Url: Getting URL from Request', $this->original_url);
                }
            }
            /*
             * can't use here wordpress native function wp_parse_url because this function fires early in advanced-cache.php
             */
            // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- none available before WordPress is loaded.
            $parts = parse_url($this->original_url); //phpcs:ignore
            $this->scheme = isset($parts["scheme"]) ? strtolower($parts["scheme"]) : null;
            $this->port = isset($parts["port"]) ? $parts["port"] : null;
            $this->host = isset($parts["host"]) ? strtolower($parts["host"]) : null;
            $this->path = isset($parts["path"]) ? strtolower($parts["path"]) : "/";
            $this->query = $this->strip_params == false && isset($parts["query"]) ? $this->remove_tracking_params($parts["query"]) : null;
            $this->generate_path();
            $this->clear_url();
        }
        protected function get_data_from_request()
        {
            $functions = FASTPIXEL_Functions::get_instance();
            return $functions->esc_url(
                (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] ? $functions->sanitize_text_field($_SERVER['HTTP_X_FORWARDED_PROTO']) : 
                (isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] ? $functions->sanitize_text_field($_SERVER['REQUEST_SCHEME']) : 
                (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] ? 'https' : 'http'))) . '://' . $functions->sanitize_text_field($_SERVER['HTTP_HOST']) . $functions->sanitize_text_field($_SERVER['REQUEST_URI']));
        }
        public function get_path()
        {
            return $this->path;
        }
        public function get_url_path() {
            return strtolower($this->url_path);
        }
        private function generate_path() {
            $this->url_path = $this->host.(!empty($this->port) ? $this->port : '').(!empty($this->path) ? rtrim($this->path, '/') : '').'/'.(!empty($this->query) ? '_' : '').(!empty($this->query) ? preg_replace('/[^a-zA-Z0-9]/', '_', $this->query) . '/' : '');
        }
        public function get_url() {
            //temporary done dynamic url generation
            return $this->scheme . '://' . $this->host . (!empty($this->port) ? $this->port : '') . (!empty($this->path) ? rtrim($this->path, '/') : '') . '/' . (!empty($this->query) ? '?' . $this->query : '');
        }
        public function get_host() {
            return $this->host;
        }
        public function get_query() {
            return $this->query;
        }

        public function remove_tracking_params($query_params) {
            parse_str($query_params, $params_array);
            ksort($params_array);
            foreach($params_array as $key => $value) {
                if (in_array(strtolower($key), $this->tracking_params)) {
                    unset($params_array[$key]);
                }
                if (in_array(strtolower($key), $this->params_to_trim)) {
                    unset($params_array[$key]);
                }
            }
            return $this->http_build_query($params_array);
        }
        public function clear_url()
        {
            $url = preg_replace('/\?.*$/i', '', $this->original_url);
            $this->url = (rtrim($url, '/') . '/') . ($this->query ? '?' . $this->query : '');
        }
        public function add_query_param($name, $value = null) {
            if (empty($name)) {
                return false;
            }
            if (!empty($this->query)) {
                parse_str($this->query, $params_array);
            } else {
                $params_array = [];
            }
            if (!in_array($name, array_keys($params_array))) {
                $params_array[$name] = $value;
            }
            $this->query = $this->http_build_query($params_array);
        }

        public function http_build_query($input_array) {
            //making pairs
            $pairs = [];
            foreach ($input_array as $key => $value) {
                if (is_null($value) || empty($value)) {
                    $pairs[] = $key;
                } else if (is_array($value)) {
                    $pairs[] = $key . $this->recursive_param_array($value);
                } else {
                    $pairs[] = $key . '=' . urlencode($value);
                }
            }
            return implode('&', $pairs);
        }

        protected function recursive_param_array($arr)
        {
            $string = '';
            foreach ($arr as $key => $val) {
                if (is_array($val)) {
                    $string .= '[' . $key . ']' . $this->recursive_param_array($val);
                } else {
                    $string .= '[' . $key . '] = ' . urlencode($val);
                }
            }
            return $string;
        }

        public function params_stripped() {
            return $this->strip_params;
        }

        public function get_original_url() {
            return $this->original_url;
        }
    }
}
