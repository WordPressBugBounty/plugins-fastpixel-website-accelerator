<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;
use FASTPIXEL\FASTPIXEL_Config_Model;

if (!class_exists('FASTPIXEL\FASTPIXEL_Backend_Cache')) {
    class FASTPIXEL_Backend_Cache extends FASTPIXEL_Backend_Controller
    {
        protected $debug = false;
        protected static $instance;
        protected $cache_dir;
        protected $config;
        protected $time_to_wait = 5; //need this option to avoid multiple page cache requests
        protected $serve_stale;
        protected $be_functions;

        public function __construct()
        {
            parent::__construct();

            self::$instance = $this;
            $this->config = FASTPIXEL_Config_Model::get_instance();
            $this->cache_dir = $this->functions->get_cache_dir();
            $this->be_functions = FASTPIXEL_Backend_Functions::get_instance();


            $this->serve_stale = function_exists('get_option') ? $this->functions->get_option('fastpixel_serve_stale') : $this->config->get_option('serve_stale');

            //cache functions only for backend
            add_action('save_post', function ($post_id, $post, $update) {
                if ($this->debug) {
                    FASTPIXEL_DEBUG::log('Class FASTPIXEL_Backend_Cache: ACTION save_post');
                }
                if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                    return;
                }
                if (isset($_REQUEST['fastpixel-nonce']) && !empty($_REQUEST['fastpixel-nonce']) ) {
                    if (!wp_verify_nonce(sanitize_key($_REQUEST['fastpixel-nonce']), 'fastpixel_edit_post')) {
                        return;
                    }
                }
                //need this check for admin posts/pages listing page to avoid cache request
                $action = isset($_GET['action']) && !empty($_GET['action']) ? sanitize_text_field($_GET['action']) : false;
                if ($action && $action == 'untrash') {
                    return;
                }
                if ($post->post_status == 'publish') {
                    if (!defined('FASTPIXEL_SAVE_POST')) {
                        define('FASTPIXEL_SAVE_POST', true);
                    }
                    $cache = FASTPIXEL_Backend_Cache::get_instance();
                    $cache->purge_cache_by_id($post_id);
                    do_action('fastpixel/post/published', $post_id); //own hook
                }
            }, 10, 3);
            add_action('wp_insert_post', function ($post_id, $post, $update) {
                if ($this->debug) {
                    FASTPIXEL_DEBUG::log('Class FASTPIXEL_Backend_Cache: ACTION wp_insert_post');
                }
                if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                    return;
                }
                if (isset($_REQUEST['fastpixel-nonce']) && !empty($_REQUEST['fastpixel-nonce'])) {
                    if (!wp_verify_nonce(sanitize_key($_REQUEST['fastpixel-nonce']), 'fastpixel_edit_post')) {
                        return;
                    }
                }
                //need this check for admin posts/pages listing page to avoid cache request
                $action = isset($_GET['action']) && !empty($_GET['action']) ? sanitize_text_field($_GET['action']) : false;
                if ($action && $action == 'untrash') {
                    return;
                }
                if ($post->post_status == 'publish') {
                    $cache = FASTPIXEL_Backend_Cache::get_instance();
                    $cache->purge_cache_by_id($post_id);
                    do_action('fastpixel/post/inserted', $post_id); //own hook
                }
            }, 10, 3);
            add_action('draft_to_publish', function ($post) {
                if ($this->debug) {
                    FASTPIXEL_DEBUG::log('Class FASTPIXEL_Backend_Cache: ACTION draft_to_publish');
                }
                if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                    return;
                }
                if (!empty($post) && $post->post_status == 'publish') {
                    $cache = FASTPIXEL_Backend_Cache::get_instance();
                    $cache->purge_cache_by_id($post->ID);
                    do_action('fastpixel/post/draft_to_publish', $post->ID); //own hook
                }
            }, 10, 1);
            add_action('pending_to_publish', function ($post) {
                if ($this->debug) {
                    FASTPIXEL_DEBUG::log('Class FASTPIXEL_Backend_Cache: ACTION pending_to_publish');
                }
                if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                    return;
                }
                if (!empty($post)) {
                    $cache = FASTPIXEL_Backend_Cache::get_instance();
                    $cache->purge_cache_by_id($post->ID);
                    do_action('fastpixel/post/pending_to_publish', $post->ID); //own hook
                }
            }, 10, 1);

            add_action('wp_trash_post', function ($post_id, $old_status) {
                if ($this->debug) {
                    FASTPIXEL_DEBUG::log('Class FASTPIXEL_Backend_Cache: ACTION wp_trash_post');
                }
                if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                    return;
                }
                if (!empty($post_id)) {
                    $post = get_post($post_id);
                    if (preg_match('/__trashed/i', $post->post_name)) {
                        $post->post_name = preg_replace('/__trashed/i', '', $post->post_name);
                        $url = new FASTPIXEL_Url(get_the_permalink($post));
                        $this->delete_url_cache($url);
                    } else {
                        $this->delete_url_cache(new FASTPIXEL_Url($post_id));
                    }
                    do_action('fastpixel/post/trashed', $post_id); //own hook
                }
            }, 10, 2);

            add_action('transition_comment_status', function ($new_status, $old_status, $comment) {
                $cache = FASTPIXEL_Backend_Cache::get_instance();
                $cache->purge_cache_by_id($comment->comment_post_ID);
                do_action('fastpixel/comment/transition_status', $new_status, $old_status, $comment); //own hook
            }, 10, 3);
            add_action('comment_post', function ($comment_id, $comment_approved, $commentdata) {
                if ($comment_approved == 1) {
                    $comment = get_comment($comment_id);
                    $cache = FASTPIXEL_Backend_Cache::get_instance();
                    $cache->purge_cache_by_id($comment->comment_post_ID);
                    do_action('fastpixel/comment/approved', $comment->comment_post_ID, $comment_approved, $commentdata); //own hook
                }
            }, 10, 3);
            add_action('admin_post_fastpixel_admin_purge_cache', [$this, 'admin_purge_cache']);
            add_action('admin_post_fastpixel_admin_delete_cached', [$this, 'admin_delete_cached_files']);
            //taxonomy functions
            add_action('edit_terms', function ($term_id, $taxonomy, $args) {
                if ($this->debug) {
                    FASTPIXEL_DEBUG::log('Class FASTPIXEL_Backend_Cache: ACTION edit_terms');
                }
                $current_term = get_term($term_id);
                if ($current_term->slug != $args['slug']) {
                    $url = new FASTPIXEL_Url(get_term_link($current_term));
                    $this->delete_url_cache($url);
                    do_action('fastpixel/term/edit', $term_id, $taxonomy, $args); //own hook
                }}, 10, 3);
            add_action('delete_term', function ($term_id, $taxonomy, $args, $deleted_term) {
                if ($this->debug) {
                    FASTPIXEL_DEBUG::log('Class FASTPIXEL_Backend_Cache: ACTION delete_term');
                }
                if (!empty($deleted_term)) {
                    $url = new FASTPIXEL_Url(get_term_link($deleted_term));
                    $this->delete_url_cache($url);
                    do_action('fastpixel/term/deleted', $term_id, $taxonomy, $args, $deleted_term); //own hook
                }
            }, 11, 4);
            add_action('edited_terms', function ($term_id, $taxonomy, $args) {
                if ($this->debug) {
                    FASTPIXEL_DEBUG::log('Class FASTPIXEL_Backend_Cache: ACTION edited_terms');
                }
                $term = get_term($term_id);
                //according docs there should be already updated term, but it is not, in this case we update slug manually
                if ($args['slug'] != $term->slug) {
                    $term->slug = $args['slug'];
                }
                $url = new FASTPIXEL_Url(get_term_link($term));
                $this->functions->update_post_cache($url->get_url_path(), true);
                do_action('fastpixel/terms/edited', $term_id, $taxonomy, $args); //own hook
            }, 10, 3);

            add_action('permalink_structure_changed', [$this, 'permalinks_change'], 10, 2);

            //moved this action outside is_admin validation to get old terms that needs to be reset, because is_admin fires after 'pre_post_update'
            //this is required in case gutenberg editor is used
            add_action('pre_post_update', function ($post_id, $data) {
                if ($this->debug) {
                    FASTPIXEL_DEBUG::log('Class FASTPIXEL_Backend_Cache: ACTION pre_post_update');
                }
                //need this to avoid duplicate execution for post type "post" which use gutenberg
                //TODO: more accurate validation
                if ($data['post_type'] == 'post' && function_exists('use_block_editor_for_post') && !use_block_editor_for_post($post_id)) {
                    return;
                }
                $post = get_post($post_id);
                //do actions when post status changed
                if ($post->post_status != $data['post_status']) {
                    if ($post->post_status == 'publish' && $data['post_status'] != 'trash') {
                        //resetting/removing cache when post is "unpublished"
                        $url = new FASTPIXEL_Url( (int)$post_id);
                        $this->delete_url_cache($url);
                        do_action('fastpixel/pre_post_update/unpublished', $post_id); //own hook
                    }
                    //if post was published and still have publish status then we need to check slug, it can be changed too
                    if ($post->post_status == 'publish' && $post->post_status == $data['post_status'] && $post->post_name != $data['post_name']) {
                        $url = new FASTPIXEL_Url( (int)$post_id);
                        $this->delete_url_cache($url);
                        do_action('fastpixel/pre_post_update/published', $post_id); //own hook
                    }
                    //trashed posts served separately because their name contains word "__trashed" already
                    if ($data['post_status'] == 'trash' && $post->post_status == 'publish') {
                        $post->post_name = preg_replace('/__trashed/i', '', $post->post_name);
                        $url = new FASTPIXEL_Url(get_the_permalink($post));
                        $this->delete_url_cache($url);
                        do_action('fastpixel/pre_post_update/trashed', $post_id); //own hook for trashed post
                    }
                    //resetting archives and taxonomies pages
                    $this->admin_purge_archives($post_id);
                    $this->admin_purge_taxonomies($post_id);
                }
                //need to check if post slug changed
                if ($post->post_name != $data['post_name']) {
                    //need to delete old cached slug
                    if ($this->debug) {
                        FASTPIXEL_Debug::log('PRE_POST_UPDATE: Slug changed. Old post slug that should be removed', get_the_permalink($post));
                    }
                    $url = new FASTPIXEL_Url(get_the_permalink($post));
                    $this->delete_url_cache($url);
                    do_action('fastpixel/pre_post_update/slug_changed', $post_id); //own hook
                }
                //need to check if post parent changed
                if ($post->post_parent != $data['post_parent']) {
                    //need to delete old cached slug
                    if ($this->debug) {
                        FASTPIXEL_Debug::log('PRE_POST_UPDATE: Parent changed. Old post slug that should be removed', get_the_permalink($post));
                    }
                    $url = new FASTPIXEL_Url(get_the_permalink($post));
                    $this->delete_url_cache($url);
                    do_action('fastpixel/pre_post_update/parent_changed', $post_id); //own hook
                }
            }, 10, 2);

            add_action('wp_ajax_fastpixel_purge_cache', [$this, 'admin_ajax_purge_cache']);
            add_action('wp_ajax_fastpixel_cache_statuses', [$this, 'admin_ajax_cache_statuses']);
            add_action('wp_ajax_fastpixel_delete_cached_files', [$this, 'admin_ajax_delete_cached_files']);

            /*
             * added nonce to the edit form, not works with gutenberg editor 
             */
            add_action('edit_page_form', [$this, 'post_nonce_field']);
            add_action('edit_form_advanced', [$this, 'post_nonce_field']);
            /*
             * reset cache status for homepage when post is created, updated, edited, deleted
             */
            add_action('fastpixel/post/published', [$this, 'purge_homepage_cache'], 10, 1);
            add_action('fastpixel/post/inserted', [$this, 'purge_homepage_cache'], 10, 1);
            add_action('fastpixel/post/draft_to_publish', [$this, 'purge_homepage_cache'], 10, 1);
            add_action('fastpixel/post/pending_to_publish', [$this, 'purge_homepage_cache'], 10, 1);
            add_action('fastpixel/post/trashed', [$this, 'purge_homepage_cache'], 10, 1);
        }

        public static function get_instance()
        {
            if (empty(self::$instance)) {
                self::$instance = new FASTPIXEL_Backend_Cache();
            }
            return self::$instance;
        }

        public function post_nonce_field() {
            wp_nonce_field('fastpixel_edit_post', 'fastpixel-nonce', false);
        }

        public function purge_cache_by_id($post_id)
        {
            if (empty($post_id)) {
                return false;
            }
            $post = get_post(esc_sql($post_id));

            // Do not purge the cache if it's an autosave or it is updating a revision.
            if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || 'revision' === $post->post_type) {
                return false;
            } elseif (in_array($post->post_status, ['draft', 'auto-draft', 'pending'])) { // Do not purge the cache if the user is editing an unpublished post.
                return false;
            } elseif (!current_user_can('edit_post', $post_id) && (!defined('DOING_CRON') || !DOING_CRON)) { // Do not purge the cache if the user cannot edit the post.
                return false;
            }

            //Do not purge post if post type is excluded
            $excluded_post_types = apply_filters('fastpixel/purge_by_id/excluded_post_types', []);
            $post_type = get_post_type($post_id);
            if (in_array($post_type, $excluded_post_types)) {
                return false;
            }

            $url = new FASTPIXEL_Url( (int)$post_id);
            $path = untrailingslashit($url->get_url_path());
            //need to check if request already was sent, because on post save much hooks are fired and each can trigger cache request
            $status = $this->functions->check_post_cache_status($url->get_url());
            if (time() <= ($this->time_to_wait + $status['last_cache_request_time'])) {
                return false;
            }
            //delete cached files if serve stale is disabled(adding this to keep more free space and avoid serving cached pages)
            if (!$this->functions->get_option('fastpixel_serve_stale')) {
                $this->functions->delete_cached_files($path);
            } else {
                do_action('fastpixel/admin/purge_cache_by_id', $url->get_url());
            }
            $this->functions->update_post_cache($path, true);
            //request page cache after reset
            if (class_exists('FASTPIXEL\FASTPIXEL_Request') && $request = FASTPIXEL_Request::get_instance()) {
                $requested = $request->cache_request($url->get_url());
                if ($requested) {
                    $this->functions->update_post_cache($url->get_url_path(), false, true);
                    if ($this->debug) {
                        FASTPIXEL_DEBUG::log('Class FASTPIXEL_Backend_Cache: request_cache, Ended Successfully');
                    }
                    //purging categories/tags/taxonomies pages
                    $this->admin_purge_taxonomies($post_id);
                    return true;
                } else {
                    if ($this->debug) {
                        FASTPIXEL_DEBUG::log('Class FASTPIXEL_Backend_Cache: request_cache, Ended with error');
                    }
                    return false;
                }
            } else {
                if ($this->debug) {
                    FASTPIXEL_DEBUG::log('Class FASTPIXEL_Backend_Cache: ACTION purge_post, Error: Class FASTPIXEL_CACHE is not available during post purge');
                }
            }
            return true;
        }

        public function purge_cache_by_url($purge_url) {
            if (empty($purge_url)) {
                return false;
            }
            if ($this->debug) {
                FASTPIXEL_Debug::log('Class FASTPIXEL_Backend_Cache: purging cache by url: ', $purge_url);
            }
            $url = new FASTPIXEL_Url($purge_url);
            $path = untrailingslashit($url->get_url_path());
            $status = $this->functions->check_post_cache_status($url->get_url());
            //delete cached files if serve stale is disabled(adding this to keep more free space and avoid serving cached pages)
            if (!$this->functions->get_option('fastpixel_serve_stale')) {
                $this->functions->delete_cached_files($path);
            } else {
                do_action('fastpixel/admin/purge_cache_by_url', $purge_url);
            }
            //setting invalidation time
            $this->functions->update_post_cache($path, true);
            //request page cache after invalidation
            if (class_exists('FASTPIXEL\FASTPIXEL_Request') && $request = FASTPIXEL_Request::get_instance()) {
                $requested = $request->cache_request($url->get_url());
                if ($requested) {
                    $this->functions->update_post_cache($url->get_url_path(), false, true);
                    if ($this->debug) {
                        FASTPIXEL_DEBUG::log('Class FASTPIXEL_Backend_Cache: request_cache_by_url, Ended Successfully');
                    }
                    return true;
                } else {
                    if ($this->debug) {
                        FASTPIXEL_DEBUG::log('Class FASTPIXEL_Backend_Cache: request_cache_by_url, Ended with error');
                    }
                    return false;
                }
            } else {
                if ($this->debug) {
                    FASTPIXEL_DEBUG::log('Class FASTPIXEL_Backend_Cache: ACTION purge_cache_by_url, Error: Class FASTPIXEL_Request is not available on purge');
                }
            }
            return true;
        }

        public function purge_all()
        {
            //initializing filesystem
            global $wp_filesystem;
            if (empty($wp_filesystem)) {
                require_once ABSPATH . '/wp-admin/includes/file.php';
                WP_Filesystem();
            }
            if (file_exists($this->functions->get_cache_dir())) {
                $wp_filesystem->put_contents($this->functions->get_cache_dir() . DIRECTORY_SEPARATOR . 'invalidated', '');
            } else {
                if ($this->debug) {
                    FASTPIXEL_DEBUG::log('Class FASTPIXEL_Backend_Cache: ACTION purge_all, Error: Can\'t purge cache, cache directory not exists');
                }
                return false;
            }
            $do_request = apply_filters('fastpixel_request_purge_all', true);
            if ($this->debug) {
                FASTPIXEL_DEBUG::log('Class FASTPIXEL_Backend_Cache: ACTION purge_all, $do_request:', $do_request);
            }
            //request page cache after reset
            if ($do_request) {
                if (class_exists('FASTPIXEL\FASTPIXEL_Request')) {
                    if ($this->debug) {
                        FASTPIXEL_DEBUG::log('Class FASTPIXEL_Backend_Cache: ACTION purge_all, Requesting Purge All API');
                    }

                    $request = FASTPIXEL_Request::get_instance();
                    $request->purge_all_request();
                } else {
                    if ($this->debug) {
                        FASTPIXEL_DEBUG::log('Class FASTPIXEL_Backend_Cache: ACTION purge_all, Error: Class FASTPIXEL_CACHE is not available in "purge all" action');
                    }
                }
            }
            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }
            do_action('fastpixel/purge/all');
            //remove cache folder if serve_stale is disabled
            $serve_stale = $this->functions->get_option('fastpixel_serve_stale');
            $home_url = new FASTPIXEL_Url(get_home_url());
            if (!$serve_stale && file_exists($this->functions->get_cache_dir() . DIRECTORY_SEPARATOR . $home_url->get_url_path() )) {
                $wp_filesystem->rmdir($this->functions->get_cache_dir() . DIRECTORY_SEPARATOR . $home_url->get_url_path(), true);
            } else {
                if ($this->debug) {
                    FASTPIXEL_DEBUG::log('Class FASTPIXEL_Backend_Cache: ACTION purge_all, Error: Can\'t clear cache directory');
                }
                return false;
            }
            return true;
        }

        public function admin_purge_cache($ajax = false) 
        {
            if (!isset($_REQUEST['nonce']) || !wp_verify_nonce(sanitize_text_field($_REQUEST['nonce']), 'cache_status_nonce')) {
                if ($ajax) {
                    echo wp_json_encode(['status' => 'error', 'statusText' => esc_html__('Can\'t run action, wrong nonce provided', 'fastpixel-website-accelerator')]);
                    wp_die();
                } else {
                    $this->notices->add_flash_notice(esc_html__('Can\'t run action, wrong nonce provided', 'fastpixel-website-accelerator'), 'error', false);
                    wp_redirect(wp_get_referer());
                }
            }
            $id = sanitize_text_field($_REQUEST['id']);
            $type = sanitize_text_field($_REQUEST['type']);
            $selected_of_type = sanitize_text_field($_REQUEST['selected_of_type']);
            if (empty($id) || !is_numeric($id) || empty($type) || empty($selected_of_type)) {
                if ($ajax) {
                } else {
                    $this->notices->add_flash_notice(esc_html__('ID or Type is wrong or empty', 'fastpixel-website-accelerator'), 'error', false);
                    wp_redirect(wp_get_referer());
                }
            }
            $args = ['id' => $id, 'type' => $type, 'selected_of_type' => $selected_of_type];
            //handling purge without ajax           
            $cache_reset_type = apply_filters('fastpixel/backend/purge/single/reset_type', 'url', $args);
            $permalink_to_reset = apply_filters('fastpixel/backend/purge/single/permalink', '', $args);
            if (empty($permalink_to_reset)) {
                if ($ajax) {
                    echo wp_json_encode(['status' => 'error', 'statusText' => esc_html__('Error occured, can\'t get cache url', 'fastpixel-website-accelerator')]);
                    wp_die();
                } else {
                    $this->notices->add_flash_notice(esc_html__('ID or Type is wrong or empty', 'fastpixel-website-accelerator'), 'error', false);
                    wp_redirect(wp_get_referer());
                }
            }
            $status = $this->functions->check_post_cache_status($permalink_to_reset);
            $cache_requested = false;
            if ($cache_reset_type == 'url') {
                $cache_requested = $this->purge_cache_by_url($permalink_to_reset);
            } else {
                $cache_requested = $this->purge_cache_by_id($id);
            }
            $status = $this->functions->check_post_cache_status($permalink_to_reset);
            if ($cache_requested) {
                $post_title = apply_filters('fastpixel/backend/purge/single/title', '', $args);
                $status_text = $status['have_cache'] && !$status['need_cache'] ?
                /* translators: status purged */
                esc_html__('purged.', 'fastpixel-website-accelerator') :
                /* translators: status requested */
                esc_html__('requested.', 'fastpixel-website-accelerator');
                if ($ajax) {
                    $post_status = $this->be_functions->cache_status_display($permalink_to_reset, $args);
                    echo wp_json_encode([
                        'id'         => $id,
                        'status'     => 'success',
                        /* translators: %1 used to display post name, %2 should display text "purged" or "requested"(texts are translated separately) */
                        'statusText' => sprintf(esc_html__('Cache for %1$s has been %2$s', 'fastpixel-website-accelerator'), esc_html($post_title), esc_html($status_text)),
                        'item'       => $post_status
                    ]);
                    wp_die();
                } else {
                    /* translators: %1 post name, %2 action name (purged or requested) */
                    $this->notices->add_flash_notice(sprintf(esc_html__('Cache for %1$s has been %2$s', 'fastpixel-website-accelerator'), esc_html($post_title), esc_html($status_text)), 'success');
                    wp_redirect(wp_get_referer());
                }
            } 
            if ($ajax) {
                echo wp_json_encode(['status' => 'error', 'statusText' => esc_html__('Error occured while requesting cache', 'fastpixel-website-accelerator')]);
                wp_die();
            }
            $this->notices->add_flash_notice(esc_html__('Error occured while requesting cache', 'fastpixel-website-accelerator'), 'success');
            wp_redirect(wp_get_referer());
        }

        public function admin_ajax_purge_cache()
        {
            $this->admin_purge_cache(true);
        }

        public function admin_ajax_cache_statuses() {
            if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'cache_status_nonce')) {
                echo wp_json_encode(['status' => 'error', 'statusText' => esc_html__('Can\'t run action, wrong nonce provided', 'fastpixel-website-accelerator')]);
                wp_die();
            }
            if (!isset($_POST['ids']) || empty($_POST['ids'])) {
                echo wp_json_encode(['status' => 'error', 'statusText' => esc_html__('Post IDs is wrong or empty', 'fastpixel-website-accelerator')]);
                wp_die();
            }
            if (!isset($_POST['type']) || empty($_POST['type'])) {
                echo wp_json_encode(['status' => 'error', 'statusText' => esc_html__('Object Type is wrong or empty', 'fastpixel-website-accelerator')]);
                wp_die();
            }
            if (!isset($_POST['selected_of_type']) || empty($_POST['selected_of_type'])) {
                echo wp_json_encode(['status' => 'error', 'statusText' => esc_html__('Post Type is wrong or empty', 'fastpixel-website-accelerator')]);
                wp_die();
            }
            $type = sanitize_text_field($_POST['type']);
            $selected_of_type = sanitize_text_field($_POST['selected_of_type']);
            $post_ids = [];
            foreach ($_POST['ids'] as $id) {
                $post_ids[] = sanitize_key($id);
            }
            $statuses = ['status' => 'success', 'items' => []];
            $items = apply_filters('fastpixel/status_page/get_statuses', [], ['type' => $type, 'selected_of_type' => $selected_of_type, 'ids' => $post_ids]);
            if (!empty($items)) {
                $statuses['items'] = $items;
            }
            echo wp_json_encode($statuses);
            wp_die();
        }

        public function admin_ajax_delete_cached_files()
        {
            $this->admin_delete_cached_files(true);
        }

        public function admin_purge_archives($post_id = null) {
            if (empty($post_id) || !is_numeric($post_id)) {
                return;
            }
            $post_type = get_post_type($post_id);

            // test case - default wordpress installation
            if ($post_type === 'post') {
                if (get_option('show_on_front') === 'posts') {
                    $path = untrailingslashit(preg_replace('/https?:\/\//i', '', home_url()));
                    $this->functions->update_post_cache($path, true);
                } else {
                    // if blog page is set then it needs to be reset
                    $blog_page_id = get_option('page_for_posts');
                    $blog_url = new FASTPIXEL_Url( (int)$blog_page_id);
                    $this->functions->update_post_cache($blog_url->get_url_path(), true);
                }
            } else {
                $post_types = array_keys(get_post_types(['public' => true, 'has_archive' => true], 'object'));
                if ($post_type && in_array($post_type, $post_types)) {
                    $archive_url = get_post_type_archive_link($post_type);
                    if ($archive_url) {
                        $url = new FASTPIXEL_Url($archive_url);
                        $path = untrailingslashit($url->get_url_path());
                        $this->functions->update_post_cache($path, true);
                    }
                }
            }
        }

        public function admin_purge_taxonomies($post_id = null)
        {
            if (empty($post_id) || !is_numeric($post_id)) {
                return;
            }
            $taxonomies = get_post_taxonomies($post_id);
            if (!empty($taxonomies)) {
                foreach ($taxonomies as $taxonomy_name) {
                    $taxonomy = get_taxonomy($taxonomy_name);
                    if ($taxonomy->public || $taxonomy->publicly_queryable) {
                        $terms = get_the_terms($post_id, $taxonomy_name);
                        if (!empty($terms)) {
                            foreach ($terms as $term) {
                                $term_link = get_term_link($term);
                                if (!empty($term_link)) {
                                    $url = new FASTPIXEL_Url($term_link);
                                    $path = untrailingslashit($url->get_url_path());
                                    //TODO: check if we need to delete cached files for taxonomies
                                    $this->delete_url_cache($url);
                                    $this->functions->update_post_cache($path, true);
                                }
                            }
                        }
                    }
                }
            }
        }

        //function that delete cached files and folders when page is unpublished, deleted, renamed etc... 
        public function delete_url_cache($url = null) {
            if (empty($url) || !is_object($url)) {
                return false;
            }
            return $this->functions->delete_cached_files($url->get_url_path());
        }

        //update advanced-cache.php on rewrite rules change
        public function permalinks_change($old, $new)
        {
            $diag = FASTPIXEL_Diag::get_instance();
            //checking same activation test like on plugin activation
            if ($diag->run_activation_tests()) {
                $this->functions->update_ac_file();
            }
            return $new;
        }

        public function admin_delete_cached_files($ajax = false) {
            if (!isset($_REQUEST['nonce']) || !wp_verify_nonce(sanitize_text_field($_REQUEST['nonce']), 'cache_status_nonce')) {
                if ($ajax) {
                    echo wp_json_encode(['status' => 'error', 'statusText' => esc_html__('Can\'t run action, wrong nonce provided', 'fastpixel-website-accelerator')]);
                    wp_die();
                } else {
                    $this->notices->add_flash_notice(esc_html__('Can\'t run action, wrong nonce provided', 'fastpixel-website-accelerator'), 'error', false);
                    wp_redirect(wp_get_referer());
                }
            }
            $id = isset($_REQUEST['id']) ? sanitize_text_field($_REQUEST['id']) : false;
            $type = isset($_REQUEST['type']) ? sanitize_text_field($_REQUEST['type']) : false;
            $selected_of_type = isset($_REQUEST['selected_of_type']) ? sanitize_text_field($_REQUEST['selected_of_type']) : false;
            if (empty($id) || empty($type) || empty($selected_of_type)) {
                if ($ajax) {
                    echo wp_json_encode(['status' => 'error', 'statusText' => esc_html__('Cache files cannot be deleted, wrong ID or type', 'fastpixel-website-accelerator')]);
                    wp_die();
                } else {
                    $this->notices->add_flash_notice(esc_html__('Cache files cannot be deleted, wrong ID or type', 'fastpixel-website-accelerator'), 'error', false);
                    wp_redirect(wp_get_referer());
                }
            }
            $args = ['id' => $id, 'type' => $type, 'selected_of_type' => $selected_of_type];
            $permalink_to_reset = apply_filters('fastpixel/backend/delete/single/permalink', '', $args);
            if (empty($permalink_to_reset)) {
                if ($ajax) {
                    echo wp_json_encode(['status' => 'error', 'statusText' => esc_html__('Error occured, can\'t get url for deletion', 'fastpixel-website-accelerator')]);
                    wp_die();
                } else {
                    $this->notices->add_flash_notice(esc_html__('Error occured, can\'t get url for deletion', 'fastpixel-website-accelerator'), 'error', false);
                    wp_redirect(wp_get_referer());
                }
            }
            $url = new FASTPIXEL_Url($permalink_to_reset);
            if ($this->delete_url_cache($url)) {
                if ($ajax) {
                    $post_status = $this->be_functions->cache_status_display($url->get_url(), $args);
                    echo wp_json_encode([
                        'id'         => $id,
                        'status'     => 'success',
                        /* translators: %s used to display post name */
                        'statusText' => sprintf(esc_html__('Cached files deleted for %s.', 'fastpixel-website-accelerator'), esc_html($url->get_url())),
                        'item'       => $post_status
                    ]);
                    wp_die();
                } else {
                    /* translators: %1 should be a url*/
                    $this->notices->add_flash_notice(sprintf(esc_html__('Cached files deleted for %1$s', 'fastpixel-website-accelerator'), esc_url($url->get_url())), 'success', false);
                    wp_redirect(wp_get_referer());
                }
            }
            if ($ajax) {
                echo wp_json_encode(['status' => 'error', 'statusText' => esc_html__('Error occured while deleting', 'fastpixel-website-accelerator')]);
                wp_die();
            } else {
                $this->notices->add_flash_notice(sprintf(esc_html__('Error occured while deleting', 'fastpixel-website-accelerator'), esc_url($url->get_url())), 'success', false);
                wp_redirect(wp_get_referer());
            }
        }

        public function purge_homepage_cache($post_id) {
            if (function_exists('get_home_url')) {
                $homepage_url = new FASTPIXEL_Url(get_home_url());
                $status = $this->functions->check_post_cache_status($homepage_url->get_url());
                if ($status['need_cache'] == false) {
                    $this->functions->update_post_cache($homepage_url->get_url_path(), true);
                }
            }
        }
    }
    new FASTPIXEL_Backend_Cache();
}
