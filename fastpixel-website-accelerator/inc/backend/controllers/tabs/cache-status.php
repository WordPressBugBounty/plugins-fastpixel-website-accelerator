<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Tab_Cache_Status')) {
    class FASTPIXEL_Tab_Cache_Status extends FASTPIXEL_UI_Tab {
        
        protected $slug = 'cache-status';
        protected $order = 1;
        private $table;

        public function __construct() {
            parent::__construct();
            $this->name = esc_html__('Dashboard', 'fastpixel-website-accelerator');
            $this->table = new FASTPIXEL_Posts_Table();
            add_action('fastpixel/tabs/loaded', [$this, 'maybe_auto_request_cache'], 5);
        }

        public function settings() {}

        public function get_table() {
            return $this->table;
        }

        public function get_homepage_performance_box_html()
        {
            if (!class_exists('FASTPIXEL\FASTPIXEL_Performance_Score')) {
                return '';
            }

            return FASTPIXEL_Performance_Score::get_instance()->get_dashboard_widget_html();
        }

        public function get_view_html()
        {
            $table = $this->get_table();
            $table->prepare_items();

            ob_start();

            // Render the speedometer content first, then decide visibility from the actual
            // rendered widget. This avoids the container showing empty when the result state
            // changes (e.g. flips to "pending") between the visibility check and the render.
            $performance_box = $this->get_homepage_performance_box_html();
            $show_speedometer = strpos($performance_box, 'fastpixel-performance-widget') !== false;

            $panel_style = $show_speedometer ? '' : ' style="display:none;"';
            $top_class = $show_speedometer ? 'fastpixel-top-panels' : 'fastpixel-top-panels fastpixel-no-speedometer';

            echo '<div class="' . esc_attr($top_class) . '">';
            echo '<div class="fastpixel-top-panel fastpixel-pageviews-panel">';
            echo $this->get_pageviews_panel_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '</div>';
            echo '<div class="fastpixel-top-panel fastpixel-speedometer-panel" id="fastpixel-speedometer-panel"' . $panel_style . '>';
            echo $performance_box; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '</div>';
            echo '<div class="fastpixel-top-panel fastpixel-account-panel">';
            echo $this->get_account_panel_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '</div>';
            echo '</div>';

            $table->display();

            return (string) ob_get_clean();
        }

        protected function get_pageviews_data()
        {
            $functions = FASTPIXEL_Functions::get_instance();
            $api_key   = (string) $functions->get_option('fastpixel_api_key', '');
            if (empty($api_key)) {
                return null;
            }

            $domain   = (string) parse_url(get_site_url(), PHP_URL_HOST);

            $response = wp_remote_post(FASTPIXEL_DASHBOARD_HOST . '/api/get-associated-domain', [
                'headers' => [
                    'Accept'       => 'application/json',
                    'Content-Type' => 'text/plain',
                ],
                'body'    => wp_json_encode(['apikey' => $api_key, 'domain' => $domain]),
                'timeout' => 10,
            ]);

            if (is_wp_error($response)) {
                return null;
            }

            $response_code = (int) wp_remote_retrieve_response_code($response);
            if (200 !== $response_code) {
                FASTPIXEL_Debug::log('[PAGEVIEWS] No data available: unexpected response code', $response_code);
                FASTPIXEL_Debug::log('[PAGEVIEWS] Response body', substr(wp_remote_retrieve_body($response), 0, 1000));
                return null;
            }

            $response_body = wp_remote_retrieve_body($response);
            $data = json_decode($response_body, true);
            if (!is_array($data)) {
                return null;
            }

            FASTPIXEL_Debug::log('[PAGEVIEWS] Request successful', $domain);
            return $data;
        }

        protected function get_pageviews_panel_html()
        {
            $pageviews_tooltip = esc_html__('Pageviews are consumed by both visits and optimizations, each visit to an optimized page uses one pageview, and each page optimized uses one pageview too.', 'fastpixel-website-accelerator');
            $html  = '<div class="fastpixel-panel-title-row">';
            $html .= '<h3 class="fastpixel-panel-title">' . esc_html__('Pageviews Usage', 'fastpixel-website-accelerator') . '</h3>';
            $html .= '<span class="fastpixel-pageviews-help-trigger">';
            $html .= '<button type="button" class="fastpixel-pageviews-help-button" aria-label="' . esc_attr__('Pageviews usage info', 'fastpixel-website-accelerator') . '">';
            $html .= '<img src="' . esc_url(FASTPIXEL_PLUGIN_URL . 'icons/info.svg') . '" alt="" class="fastpixel-pageviews-help-icon" />';
            $html .= '</button>';
            $html .= '<span class="fastpixel-pageviews-help-tooltip" role="tooltip">' . $pageviews_tooltip . '</span>';
            $html .= '</span>';
            $html .= '</div>';

            $data = $this->get_pageviews_data();
            if (!is_array($data)) {
                $html .= '<p class="fastpixel-panel-placeholder">' . esc_html__('No data available.', 'fastpixel-website-accelerator') . '</p>';
                return $html;
            }

            $used  = isset($data['used'])      ? (int) $data['used']      : 0;
            $total = isset($data['pageviews']) ? (int) $data['pageviews'] : 0;

            $percent = $total > 0 ? min(100, max(0, ($used / $total) * 100)) : 0;
            $visual_percent = $percent > 0 ? max(2, round($percent, 2)) : 0;

            $html .= '<p class="fastpixel-pageviews-numbers">';
            $html .= '<span class="fastpixel-pageviews-used">' . number_format_i18n($used) . '</span>';
            if ($total > 0) {
                $html .= ' / ';
                $html .= '<span class="fastpixel-pageviews-total">' . number_format_i18n($total) . '</span>';
            }
            $html .= '</p>';
            if ($total > 0) {
                $html .= '<div class="fastpixel-pageviews-bar"><div class="fastpixel-pageviews-bar-fill" style="width:' . esc_attr($visual_percent) . '%"></div></div>';
            }
            return $html;
        }

        protected function get_account_panel_html()
        {
            $functions = FASTPIXEL_Functions::get_instance();
            $api_key   = (string) $functions->get_option('fastpixel_api_key', '');
            $masked    = $this->mask_api_key($api_key);

            $html  = '<h3 class="fastpixel-panel-title">' . esc_html__('FastPixel Account Area', 'fastpixel-website-accelerator') . '</h3>';
            $html .= '<div class="fastpixel-account-panel-content">';
            $html .= '<p class="fastpixel-account-key-label">' . esc_html__('API Key', 'fastpixel-website-accelerator') . '</p>';
            $html .= '<div class="fastpixel-api-key-display">';
            $html .= '<span class="fastpixel-api-key-masked">' . esc_html($masked) . '</span>';
            $html .= '<button type="button" class="button fastpixel-change-key-btn">' . esc_html__('Change', 'fastpixel-website-accelerator') . '</button>';
            $html .= '</div>';
            $html .= '<div class="fastpixel-api-key-edit" style="display:none;">';
            $html .= '<input type="text" class="fastpixel-api-key-input regular-text" placeholder="' . esc_attr__('Enter new API key…', 'fastpixel-website-accelerator') . '" autocomplete="off" />';
            $html .= '<div class="fastpixel-api-key-edit-actions">';
            $html .= '<button type="button" class="button button-primary fastpixel-save-key-btn">' . esc_html__('Save', 'fastpixel-website-accelerator') . '</button>';
            $html .= '<button type="button" class="button fastpixel-cancel-key-btn">' . esc_html__('Cancel', 'fastpixel-website-accelerator') . '</button>';
            $html .= '</div>';
            $html .= '<div class="fastpixel-api-key-message" style="display:none;" aria-live="polite"></div>';
            $html .= '</div>';
            $html .= '</div>';
            return $html;
        }

        protected function mask_api_key($key)
        {
            if (empty($key)) {
                return esc_html__('(not set)', 'fastpixel-website-accelerator');
            }

            $visible_chars = 4;
            $hidden_chars = max(0, strlen($key) - $visible_chars);

            return substr($key, 0, $visible_chars) . str_repeat("\xe2\x80\xa2", $hidden_chars);
        }

        public function get_link()
        {
            return esc_url(admin_url('admin.php?page=' . FASTPIXEL_TEXTDOMAIN . '-settings#cache-status'));
        }

        /**
         * Automatically request cache for uncached pages when dashboard is first loaded after onboarding
         */
        public function maybe_auto_request_cache() {
            // Only run on cache-status tab
            global $pagenow;
            // just checking page, no data posted
            $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : false;
            if ($pagenow != 'admin.php' || $page != FASTPIXEL_TEXTDOMAIN . '-settings') {
                return;
            }
            
            // Check if we already did this auto-request
            $transient_key = 'fastpixel_auto_cache_requested';
            
            // TEMPORARY-> just for testing: uncomment the line below to force auto-request on every dashboard load (for testing)
            // delete_transient($transient_key);
            
            if (get_transient($transient_key)) {
                return;
            }

            // Only run if we have an API key (temp or normal)
            $functions = FASTPIXEL_Functions::get_instance();
            $api_key = $functions->get_option('fastpixel_api_key', '');
            if (empty($api_key)) {
                return;
            }

            FASTPIXEL_Debug::log('[CACHE-STATUS] maybe_auto_request_cache: starting auto cache request for uncached pages');

            // Get the posts list from the table
            // We need to prepare items with default args to get first page
            $table = $this->get_table();
            
            // Temporarily filter per_page to limit auto-requests (max 50 items)
            $limit_auto_requests = function($per_page) {
                return min($per_page, 50);
            };
            add_filter('fastpixel/status_page/posts_per_page', $limit_auto_requests, 999);
            
            $table->prepare_items();
            $items = $table->items;
            
            remove_filter('fastpixel/status_page/posts_per_page', $limit_auto_requests, 999);

            if (empty($items)) {
                FASTPIXEL_Debug::log('[CACHE-STATUS] maybe_auto_request_cache: no items found');
                // check it as done even if no items, to avoid checking again
                set_transient($transient_key, true, DAY_IN_SECONDS);
                return;
            }

            $be_cache = FASTPIXEL_Backend_Cache::get_instance();
            $requested_count = 0;
            $skipped_count = 0;

            foreach ($items as $item) {
                // skip if already cached
                if (isset($item['cachestatus']) && $item['cachestatus'] === 'cached') {
                    $skipped_count++;
                    continue;
                }
                //skip if excluded or private
                if (isset($item['cachestatus']) && in_array($item['cachestatus'], ['excluded', 'error'])) {
                    $skipped_count++;
                    continue;
                }
                if (empty($item['url'])) {
                    continue;
                }
                // request cache for this URL
                $url = $item['url'];
                
                // Get selected post type from GET parameter or default to 'page'
                $selected_post_type = isset($_GET['ptype']) ? sanitize_key($_GET['ptype']) : 'page';
                if (empty($selected_post_type)) {
                    $selected_post_type = apply_filters('fastpixel/status_page/default_post_type', 'page');
                }
                
                $filter_args = [
                    'id' => isset($item['ID']) ? $item['ID'] : '',
                    'selected_of_type' => $selected_post_type,
                    'type' => 'posts',
                    'url' => $url
                ];

                FASTPIXEL_Debug::log('[CACHE-STATUS] maybe_auto_request_cache: requesting cache for', $url);
                
                $cache_requested = $be_cache->purge_cache_by_url($filter_args);
                
                if ($cache_requested) {
                    $requested_count++;
                } else {
                    FASTPIXEL_Debug::log('[CACHE-STATUS] maybe_auto_request_cache: failed to request cache for', $url);
                }
                // small delay...
                usleep(100000); // 0.1 second
            }

            // Mark as done
            set_transient($transient_key, true, DAY_IN_SECONDS);

            FASTPIXEL_Debug::log('[CACHE-STATUS] maybe_auto_request_cache: completed', [
                'requested' => $requested_count,
                'skipped' => $skipped_count,
                'total' => count($items)
            ]);

            if ($requested_count > 0) {
                $notices = FASTPIXEL_Notices::get_instance();
                $notices->add_flash_notice(
                    sprintf(
                        esc_html__('Started caching %d page(s). This may take a few minutes. Check the status below.', 'fastpixel-website-accelerator'),
                        $requested_count
                    ),
                    'success',
                    false
                );
            }
        }
    }
    new FASTPIXEL_Tab_Cache_Status();
}
