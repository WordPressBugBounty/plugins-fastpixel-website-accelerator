<?php 
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Exclude_Amp')) {
    class FASTPIXEL_Exclude_Amp {
        public function __construct() {
            add_filter('fastpixel/init/excluded', [$this, 'check_is_exclusion'], 10, 2);
            add_filter('fastpixel/is_cache_request_allowed/excluded', [$this, 'check_is_exclusion'], 10, 2);
            add_filter('fastpixel/is_cache_request_allowed/excluded/post_types', [$this, 'exclude_post_type']);
            add_filter('fastpixel/backend/purge/single/post/excluded_post_types', [$this, 'exclude_post_type']);
        }

        public function check_is_exclusion($status, $url) {
            if ($status) {
                return $status;
            }
            if (\function_exists('is_amp_endpoint') && \is_amp_endpoint()) {
                return true;
            }
            //checking also path
            $request_url_path = $url->get_url_path();
            if (preg_match('/amp_validated_url/i', $request_url_path) ||
                preg_match('/amp_validate/i', $request_url_path)) {
                return true;
            }
        }

        public function exclude_post_type($post_types) {
            $post_types[] = 'amp_validated_url';
            return $post_types;
        }
    }
    new FASTPIXEL_Exclude_Amp();
}
