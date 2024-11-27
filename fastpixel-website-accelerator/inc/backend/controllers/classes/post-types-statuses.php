<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Post_Types_Statuses')) {
    class FASTPIXEL_Post_Types_Statuses {
        protected $be_functions;
        protected $excludes;
        protected $post_types = [];
        protected $total_items = 1;
        protected $total_pages = 1;
        protected $nonce;
        protected $selected_post_type;

        public function __construct() {
            $this->be_functions = FASTPIXEL_Backend_Functions::get_instance();
            $this->excludes = FASTPIXEL_Excludes::get_instance();
            //loading post types later, when all plugins and themes loaded
            add_action('admin_init', function () {
                $this->post_types = get_post_types(['public' => true], 'objects');
                $this->nonce = wp_create_nonce('cache_status_nonce');
            });
            //adding post type options into selector
            add_filter('fastpixel/status_page/options', [$this, 'add_option'], 10, 2);
            //setting default post type
            add_filter('fastpixel/status_page/default_post_type', [$this, 'default_post_type'], 10, 1);
            //hook that register callbacks required to display selected post type list
            add_action('fastpixel/status_page/selected_post_type', [$this, 'selected'], 10, 1);
            //handling posts statuses on status page
            add_filter('fastpixel/admin_ajax/cache_statuses/permalink', [$this, 'admin_ajax_cache_statuses'], 10, 2);
            add_action('fastpixel/backend_functions/cache_status_display/excluded', [$this, 'check_post_is_excluded'], 10, 2);
            //handling ajax cache purge
            add_filter('fastpixel/backend/ajax/purge_single', [$this, 'backend_purge_single'], 10, 2);
            add_filter('fastpixel/backend/ajax/purge_single_post_title', [$this, 'backend_ajax_purge_single_post_title'], 10, 2);
            add_filter('fastpixel/backend/ajax/cache_reset_type', [$this, 'backend_cache_reset_type'], 10, 2);
            //handling default cache purge
            add_filter('fastpixel/backend/cache_action/purge_single', [$this, 'backend_purge_single'], 10, 2);
            add_filter('fastpixel/backend/cache_action/cache_reset_type', [$this, 'backend_cache_reset_type'], 10, 2);
            add_filter('fastpixel/backend/cache_action/post_type_name', [$this, 'backend_post_type_name'], 10, 2);
            //handling default delete cached files action
            add_filter('fastpixel/backend/delete_action/purge_single', [$this, 'backend_purge_single'], 10, 2);
            //handling bulk cache purge
            add_filter('fastpixel/backend/bulk/cache_reset_type', [$this, 'backend_cache_reset_type'], 10, 2);
            add_filter('fastpixel/backend/bulk/post_type_name', [$this, 'backend_post_type_name'], 10, 2);
        }
        public function default_post_type($post_type) { 
            if (empty($post_type)) {
                return 'page';
            }
            return $post_type;
        }

        public function selected($post_type) {
            //no need to fire actions if selected post type is not handled by this class
            if (!in_array($post_type, array_keys($this->post_types))) {
                return false;
            }
            $this->selected_post_type = $post_type;
            add_filter('fastpixel/status_page/posts_per_page', [$this, 'posts_per_page'], 10, 1);
            add_filter('fastpixel/status_page/posts_list', [$this, 'get_posts_list'], 10, 2);
            add_filter('fastpixel/status_page/display_search', [$this, 'display_search'], 10, 1);
            add_filter('fastpixel/status_page/total_items', [$this, 'total_items'], 10, 1);
            add_filter('fastpixel/status_page/total_pages', [$this, 'total_pages'], 10, 1);
            add_filter('fastpixel/status_page/column_url', [$this, 'column_url'], 10, 2);
            add_filter('fastpixel/status_page/row_actions', [$this, 'row_actions'], 10, 2);
            add_filter('fastpixel/status_page/search/post_type_name', [$this, 'search_post_type_name'], 10, 2);
            add_action('admin_enqueue_scripts', function () {
                wp_localize_script('fastpixel-backend', 'fastpixel_backend_status', ['post_type' => $this->selected_post_type]);
            });
        }

        public function posts_per_page($posts_per_page) {
            return $posts_per_page;
        }

        public function add_option($options, $selected) {
            $opts = '<optgroup label="Post Types">';
            foreach ($this->post_types as $ptype) {
                if ($ptype->name == 'attachment') {
                    continue;
                }
                if ($ptype->public == true) {
                    $opts .= '<option value="' . esc_attr($ptype->name) . '" ' . ($selected == $ptype->name ? 'selected="selected"' : '') . '>' . esc_html($ptype->label) . '</option>';
                }
            }
            $opts .= '</optgroup>';
            return $options . $opts;
        }

        public function get_posts_list($posts_list, $args) {
            $args['post_status'] = $args['post_type'] == 'attachment' ? 'any' : ['publish', 'private'];
            $wp_query = new \WP_Query($args);
            $posts = $wp_query->get_posts();
            $this->total_items = $wp_query->found_posts;
            $this->total_pages = $wp_query->max_num_pages;
            foreach($posts as $post) {
                $url = get_permalink($post);
                $cache_status = $this->be_functions->cache_status_display($url, ['id' => $post->ID, 'post_type' => $this->selected_post_type]);
                $posts_list[] = [
                    'ID'                => $post->ID,
                    'post_title'        => $post->post_title,
                    'url'               => urldecode($url),
                    'cache_status'      => $cache_status['status_display'],
                    'cachestatus'       => $cache_status['status'],
                    'display_status'    => $post->post_status == 'publish' ? '<b>' . __('published', 'fastpixel-website-accelerator') . '</b>' : '<b>' . $post->post_status .'</b>',
                    'html_created_time' => $cache_status['html_created_time'],
                    'post_status'       => $post->post_status
                ];
            }
            $extra_pages = [];
            if ($args['post_type'] == 'page') {
                $page_on_front  = get_option('page_on_front');
                //if static page is not set, adding homepage to list
                if ($page_on_front == 0 && $args['current_page'] == 1) {
                    $cache_status = $this->be_functions->cache_status_display(get_home_url());
                    $extra_pages[] = [
                        'ID'             => 'homepage',
                        'post_title'     => esc_html__('Homepage', 'fastpixel-website-accelerator'),
                        'url'            => get_home_url(),
                        'cache_status'   => $cache_status['status_display'],
                        'cachestatus'    => $cache_status['status'],
                        'display_status' => '<b>published</b>',
                        'post_status'    => 'publish'
                    ];
                }
            }
            $posts_list = array_merge($extra_pages, $posts_list);
            return $posts_list;
        }

        public function display_search($display) {
            return true;
        }

        public function total_items($total_items)
        {
            return $this->total_items;
        }

        public function total_pages($total_pages) {
            return $this->total_pages;
        }

        public function column_url($text, $item) {
            return $text;
        }

        public function row_actions($actions, $item) {
            //setting links
            $link = get_permalink($item['ID']);
            $purge_id = $item['ID'];
            //setting links for homepage
            if ($item['ID'] == null && $item['post_title'] == 'Homepage') {
                $purge_id = 'homepage';
                $link = get_home_url();
            }
            $edit_link = sprintf('post.php?post=%1$d&action=%2$s', $item['ID'], 'edit');
            $purge_link = sprintf('admin-post.php?action=%1$s&nonce=%2$s&post_id=%3$s&post_type=%4$s', ($purge_id == 'homepage' ? 'fastpixel_admin_purge_homepage_cache' : 'fastpixel_admin_purge_post_cache'), $this->nonce, $purge_id, $this->selected_post_type);
            //actions
            if ($item['cachestatus'] != 'cached') {
                $purge_cache_link = sprintf('<a class="fastpixel-purge-single-post" data-post-id="%1$s" data-post-type="%2$s" href="%3$s">' . esc_html__('Cache Now', 'fastpixel-website-accelerator') . '</a>', $purge_id, $this->selected_post_type, esc_url($purge_link));
            } else {
                $purge_cache_link = sprintf('<a class="fastpixel-purge-single-post" data-post-id="%1$s" data-post-type="%2$s" href="%3$s">' . esc_html__('Purge Cache', 'fastpixel-website-accelerator') . '</a>', $purge_id, $this->selected_post_type, esc_url($purge_link));
            }
            $actions = apply_filters('fastpixel/status_page/url_actions', [], ['post_type' => 'page']);
            $actions = array(
                'view'        => sprintf('<a href="%s" target="_blank">' . esc_html__('Preview', 'fastpixel-website-accelerator') . '</a>', esc_url($link)),
                'edit'        => sprintf('<a href="%s">' . esc_html__('Edit', 'fastpixel-website-accelerator') . '</a>', esc_url($edit_link)),
                'purge_cache' => $purge_cache_link
            );
            //adding delete cached files action for stale items
            if (
                $item['cachestatus'] == 'stale' ||
                (in_array($item['cachestatus'], array('excluded', 'error')) &&
                    (isset($item['html_created_time']) && $item['html_created_time'] > 0))
            ) {
                $delete_link = sprintf('admin-post.php?action=%1$s&nonce=%2$s&post_id=%3$s&post_type=%4$s', 'fastpixel_admin_delete_cached', $this->nonce, $purge_id, $this->selected_post_type);
                $actions['delete_cached'] = sprintf('<a class="fastpixel-delete-cached-files-single-post" data-post-id="%1$s" data-post-type="%2$s" href="%3$s">' . esc_html__('Delete Cached Files', 'fastpixel-website-accelerator') . '</a>', $purge_id, $this->selected_post_type, esc_url($delete_link));
            }
            //removing edit button for homepage, when post_id is not present
            if ($purge_id == 'homepage') {
                unset($actions['edit']);
            }
            if ($item['post_status'] == 'private' || $item['cachestatus'] == 'excluded') {
                unset($actions['purge_cache']);
            }
            return $actions;
        }

        public function search_post_type_name($default, $post_type) {
            if (in_array($post_type, array_keys($this->post_types))) {
                return $this->post_types[$post_type]->labels->name;
            }
            return $default;
        }

        public function admin_ajax_cache_statuses($permalink, $args)
        {
            if (in_array($args['post_type'], array_keys($this->post_types))) {
                $permalink = get_permalink($args['id']);
            }
            return $permalink;
        }

        public function backend_purge_single($permalink_to_reset, $args)
        {
            if (in_array($args['post_type'], array_keys($this->post_types))) {
                if (is_numeric($args['post_id'])) {
                    $permalink_to_reset = get_permalink($args['post_id']);
                } else if ($args['post_id'] == 'homepage') {
                    $permalink_to_reset = $this->be_functions->get_home_url();
                }
                
            }
            return $permalink_to_reset;
        }

        public function backend_ajax_purge_single_post_title($title, $args)
        {
            if (in_array($args['post_type'], array_keys($this->post_types))) {
                if (is_numeric($args['post_id'])) {
                    $post = get_post($args['post_id']);
                    $title = $post->post_title;
                } else if ($args['post_id'] == 'homepage') {
                    $title = __('Homepage', 'fastpixel-website-accelerator');
                }

            }
            return $title;
        }

        public function backend_cache_reset_type($type, $args) {
            if (in_array($args['post_type'], array_keys($this->post_types))) {
                if (is_numeric($args['post_id'])) {
                    $type = 'id';
                } else if ($args['post_id'] == 'homepage') {
                    $type = 'url';
                }
            }
            return $type;
        }

        public function backend_post_type_name($name, $args) {
            if (in_array($args['post_type'], array_keys($this->post_types))) {
                foreach ($this->post_types as $post_type) {
                    if ($post_type->name == $this->selected_post_type) {
                        if (is_object($post_type->labels)) {
                            if ($args['count'] == 1) {
                                $name = strtolower($post_type->labels->singular_name);
                            } else {
                                $name = strtolower($post_type->labels->name);
                            }
                        }
                        break;
                    }
                }
            }
            return $name;
        }

        public function check_post_is_excluded($status, $data) {
            //need to check for exclusion
            if (isset($data['id']) && !empty($data['id'])) {
                $url = get_permalink($data['id']);
                $status = $this->excludes->check_is_exclusion($url) || post_password_required($data['id']);
            }
            return $status;
        }
    }

    if (is_admin()) {
        new FASTPIXEL_Post_Types_Statuses();
    }
}
