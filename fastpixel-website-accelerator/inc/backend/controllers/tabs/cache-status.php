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
            // hook to auto-request cache for uncached pages on first dashboard load
            add_action('fastpixel/tabs/loaded', [$this, 'maybe_auto_request_cache'], 5);
        }

        public function settings() {}

        public function get_table() {
            return $this->table;
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
                // skip if post_status is private
                if (isset($item['post_status']) && $item['post_status'] === 'private') {
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
