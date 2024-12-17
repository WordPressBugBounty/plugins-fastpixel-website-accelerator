<?php 
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Exclude_Amp')) {
    class FASTPIXEL_Exclude_Amp {
        public function __construct() {
            add_filter('fastpixel/excludes/post_types', array($this, 'exclude_post_type'));
            add_filter('fastpixel/purge_by_id/excluded_post_types', array( $this, 'exclude_post_type') );
        }

        public function check_is_exclusion($url) {
            if (\function_exists('is_amp_endpoint') && \is_amp_endpoint()) {
                return true;
            }
            //checking also url
            $request_url_path = $url->get_path();
            if (preg_match('/amp_validated_url/i', $request_url_path)) {
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
