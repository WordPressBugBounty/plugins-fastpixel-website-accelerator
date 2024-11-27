<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Backend_Functions')) {
    class FASTPIXEL_Backend_Functions
    {
        public static $instance;
        protected $functions;
        protected $excludes;
        protected $serve_stale;

        public function __construct()
        {
            self::$instance = $this;
            $this->functions = FASTPIXEL_Functions::get_instance();
            $this->serve_stale = $this->functions->get_option('fastpixel_serve_stale');
        }

        public static function get_instance()
        {
            if (empty(self::$instance)) {
                self::$instance = new FASTPIXEL_Notices();
            }
            return self::$instance;
        }

        public function cache_status_display($url, $data = null)
        {
            $cache_status = [
                'status' => '',
                'status_display' => '',
                'html_created_time' => ''
            ];
            $check_result = $this->functions->check_post_cache_status($url);
            //return early if check failed
            if ($check_result == false) {
                return $cache_status;
            }
            $excluded = apply_filters('fastpixel/backend_functions/cache_status_display/excluded', false, $data);
            if ($excluded) {
                $cache_status['status_display'] = '<span class="have-popup"><strong>' . esc_html__('Excluded', 'fastpixel-website-accelerator') . '</strong></span>';
                $cache_status['status_display'] .= '<span class="have-popup dashicons dashicons-editor-help"></span>';
                $cache_status['status_display'] .= '<div class="pop-up">' . esc_html__('URL is excluded or has dynamic content.', 'fastpixel-website-accelerator') . '</div>';
                $cache_status['status'] = 'excluded';
                return $cache_status;
            }

            $cache_status['html_created_time'] = $check_result['html_created_time'];
            //TODO: check if we will display paged urls
            /*//checking if page is paged
            if (preg_match('/\/page\/\d+/', $url)) {
                //checking for parent page invalidation date
                $parent_url = preg_replace('/page.+$/i', '', $url);
                $parent_meta = $this->functions->check_post_cache_status($parent_url);

                if ((isset($check_result['have_cache']) && $check_result['have_cache'])
                    && $parent_meta['local_invalidation_time'] > $check_result['local_invalidation_time'] &&
                    $check_result['html_created_time'] < $parent_meta['local_invalidation_time']) {
                    //updating page meta with parent meta
                    $check_result['need_cache'] = true;
                    $check_result['local_invalidation_time'] = $parent_meta['local_invalidation_time'];
                }
            }*/

            //checking page status
            if ($check_result['error'] != false && $check_result['error_time'] > $check_result['last_cache_request_time']) {
                $cache_status['status_display'] = '<span class="have-popup error"><strong>' . esc_html__('Error', 'fastpixel-website-accelerator') . '</strong></span>';
                $cache_status['status'] = 'error';
                /* translators: %s should be an error text */
                $cache_status['status_display'] .= '<div class="pop-up">' . sprintf(esc_html__('Error: %s', 'fastpixel-website-accelerator'), $check_result['error']) . '</div>';
            } else {
                if ($check_result['have_cache'] && !$check_result['need_cache']) {
                    $cache_status['status_display'] = '<span class="have-popup cached"><strong>' . esc_html__('Cached', 'fastpixel-website-accelerator') . '</strong></span>';
                    $cache_status['status'] = 'cached';
                    /* translators: %s should be a date string */
                    $cache_status['status_display'] .= '<div class="pop-up">' . sprintf(esc_html__('Cached at: %s', 'fastpixel-website-accelerator'), gmdate('Y-m-d H:i:s', $check_result['html_created_time'])) . '</div>';
                } else if ($check_result['have_cache'] && $check_result['need_cache'] && $this->serve_stale) {
                    $cache_status['status_display'] = '<div class="stale-container"><span class="have-popup cached invalidated"><strong>' . esc_html__('Stale', 'fastpixel-website-accelerator') . '</strong></span><span class="stale-loader"></span></div>';
                    $cache_status['status'] = 'stale';
                    /* translators: %s should be a date string */
                    $cache_status['status_display'] .= '<div class="pop-up">' . sprintf(esc_html__('Cached at: %s', 'fastpixel-website-accelerator'), gmdate('Y-m-d H:i:s', $check_result['html_created_time'])) . '<br/>' .
                        /* translators: %s should be a date string */
                        sprintf(esc_html__('Cache requested at: %s', 'fastpixel-website-accelerator'), gmdate('Y-m-d H:i:s', $check_result['last_cache_request_time'])) . '</div>';
                } else {
                    if ($check_result['last_cache_request_time'] && ($check_result['have_cache'] == false || $check_result['last_cache_request_time'] > $check_result['html_created_time'])) {
                        $cache_status['status_display'] = '<div class="queued-container"><span class="have-popup queued">' . esc_html__('Queued', 'fastpixel-website-accelerator') . '</span><span class="queued-loader"></span></div>';
                        $cache_status['status'] = 'queued';
                        /* translators: %s should be a date string */
                        $cache_status['status_display'] .= '<div class="pop-up">' . 
                            /* translators: %s is used to display date and time when page cache was requested */
                            sprintf(esc_html__('Cache requested at: %s', 'fastpixel-website-accelerator'), gmdate('Y-m-d H:i:s', $check_result['last_cache_request_time'])) . 
                            /* translators: */
                            ( $check_result['error'] != false ? '<br/>' . sprintf(esc_html__('Last Error: %s', 'fastpixel-website-accelerator'), $check_result['error']) : '' ) .
                        '</div>';
                    } else {
                        $cache_status['status_display'] = '<span class="not-cached">' . esc_html__('Not Cached', 'fastpixel-website-accelerator') . '</span>';
                        $cache_status['status_display'] .= '<span class="have-popup dashicons dashicons-editor-help"></span>';
                        $cache_status['status_display'] .= '<div class="pop-up">' . esc_html__('To efficiently use the resources on your website, pages are processed and cached as they are visited by outside visitors. You can manually force a page to be processed by clicking "Cache now"', 'fastpixel-website-accelerator') . '</div>';
                        $cache_status['status'] = 'not-cached';
                    }
                }
            }
            return $cache_status;
        }

        public function get_home_url() {
            if (function_exists('get_home_url')) {
                if (defined('ICL_SITEPRESS_VERSION')) { //WPML is installed, need to use it for getting home url
                    return apply_filters('wpml_home_url', get_home_url());
                } else {
                    return get_home_url();
                }
            }
            return false;
        }

        //TODO: Check if we need to display paginated urls
        public function paginate_links($args) {
            global $wp_rewrite;

            // Setting up default values based on the current URL.
            $pagenum_link = html_entity_decode($args['base']);
            $url_parts = explode('?', $pagenum_link);

            // Append the format placeholder to the base URL.
            $pagenum_link = trailingslashit($url_parts[0]) . '%_%';

            // URL base depends on permalink settings.
            $format = $wp_rewrite->using_index_permalinks() && !strpos($pagenum_link, 'index.php') ? 'index.php/' : '';
            $format .= $wp_rewrite->using_permalinks() ? user_trailingslashit(rtrim($wp_rewrite->pagination_base, '/') . '/%#%', 'paged') : '?paged=%#%';

            $args['base']   = $pagenum_link; // http://example.com/all_posts.php%_% : %_% is replaced by format (below).
            $args['format'] = $format; // ?page=%#% : %#% is replaced by the page number.

            $total = (int) $args['total'];
            $page_links = array();

            for ($n = 1; $n <= $total; $n++):
                $link = str_replace('%_%', 1 == $n ? '' : $args['format'], $args['base']);
                $link = str_replace('%#%', $n, $link);
                $page_links[] = $link;
            endfor;

            return $page_links;
        }
    }

    new FASTPIXEL_Backend_Functions();
}
