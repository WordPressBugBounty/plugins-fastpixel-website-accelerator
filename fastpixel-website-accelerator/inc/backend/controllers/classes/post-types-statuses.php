<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Post_Types_Statuses')) {
    class FASTPIXEL_Post_Types_Statuses {

        protected $type = 'posts';
        protected $be_functions;
        protected $functions;
        protected $post_types = [];
        protected $total_items = 1;
        protected $total_pages = 1;
        protected $nonce;
        protected $selected_post_type;
        protected $excluded_post_types = [];

        public function __construct() {
            $this->functions = FASTPIXEL_Functions::get_instance();
            $this->be_functions = FASTPIXEL_Backend_Functions::get_instance();
            //loading post types later, when all plugins and themes loaded
            add_action('init', function () {
                $this->post_types = get_post_types(['public' => true], 'objects');
                $this->nonce = wp_create_nonce('cache_status_nonce');
                $this->excluded_post_types = apply_filters('fastpixel/backend/posts_statuses/init/excluded_post_types', $this->functions->get_option('fastpixel_excluded_post_types', []));
            });
            //adding post type options into selector
            add_filter('fastpixel/status_page/options', [$this, 'add_option'], 10, 2);
            //setting default post type
            add_filter('fastpixel/status_page/default_post_type', [$this, 'default_post_type'], 10, 1);
            //hook that register callbacks required to display selected post type list
            add_action('fastpixel/status_page/selected_post_type', [$this, 'selected'], 10, 1);
            //handling posts statuses on status page
            add_filter('fastpixel/status_page/get_statuses', [$this, 'status_page_get_statuses'], 10, 2);
            add_filter('fastpixel/backend_functions/cache_status_display/excluded', [$this, 'check_post_is_excluded'], 10, 2);
            //handling admin bar purge button
            add_filter('fastpixel/admin_bar/purge_this_button_exclude', [$this, 'check_post_is_excluded'], 10, 2);
            //handling purge
            add_filter('fastpixel/backend/purge/single/object', [$this, 'backend_purge_single_object'], 10, 2);
            add_filter('fastpixel/backend/purge/single/reset_type', [$this, 'backend_cache_reset_type'], 10, 2);
            add_filter('fastpixel/backend/purge/single/permalink', [$this, 'backend_single_permalink'], 10, 2);
            add_filter('fastpixel/backend/purge/single/title', [$this, 'backend_ajax_purge_single_post_title'], 10, 2);
            add_filter('fastpixel/backend/purge/single/post/is_excluded', [$this, 'check_post_is_excluded'], 10, 2);
            add_filter('fastpixel/backend/bulk/type', [$this, 'backend_bulk_statuses_type'], 10, 2);
            //handling cached files deletion
            add_filter('fastpixel/backend/delete/single/permalink', [$this, 'backend_single_permalink'], 10, 2);
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
            //handling bulk cache purge
            add_filter('fastpixel/backend/bulk/reset_type', [$this, 'backend_bulk_cache_reset_type'], 10, 2);
            add_filter('fastpixel/backend/bulk/post_type_name', [$this, 'backend_post_type_name'], 10, 2);
            add_filter('fastpixel/backend/bulk/purge_single', [$this, 'backend_bulk_single_permalink'], 10, 2);
            add_action('admin_enqueue_scripts', function () {
                wp_localize_script('fastpixel-backend', 'fastpixel_backend_status', [
                    'type'               => $this->type, 
                    'selected_of_type'   => $this->selected_post_type,
                    'delete_cached_link' => sprintf('admin-post.php?action=%1$s&nonce=%2$s&selected_of_type=%3$s&id=', 'fastpixel_admin_delete_cached', $this->nonce, $this->selected_post_type),
                    'extra_params'       => apply_filters('fastpixel/status_page/extra_params', [])
                ]);
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
            $args['post_status'] = ['publish', 'private'];
            $wp_query = new \WP_Query($args);
            $posts = $wp_query->get_posts();
            $this->total_items = $wp_query->found_posts;
            $this->total_pages = $wp_query->max_num_pages;
            foreach($posts as $post) {
                $url = get_permalink($post);
                $status_data = ['id' => $post->ID, 'selected_of_type' => $this->selected_post_type, 'type' => $this->type];
                $url = apply_filters('fastpixel/status_page/permalink', $url, $status_data);
                $cache_status = $this->be_functions->cache_status_display($url, $status_data);
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
                $show_on_front  = get_option('show_on_front');
                $page_on_front  = get_option('page_on_front');
                //if static page is not set, adding homepage to list
                if (($show_on_front == 'posts' || $page_on_front == 0) && $args['current_page'] == 1 && empty($args['s'])) {
                    $url = get_home_url();
                    $status_data = ['id' => 'homepage', 'selected_of_type' => $this->selected_post_type, 'type' => $this->type];
                    $url = apply_filters('fastpixel/status_page/permalink', $url, $status_data);
                    $cache_status = $this->be_functions->cache_status_display($url, $status_data);
                    $extra_pages[] = [
                        'ID'                => 'homepage',
                        'post_title'        => esc_html__('Homepage', 'fastpixel-website-accelerator'),
                        'url'               => $url,
                        'cache_status'      => $cache_status['status_display'],
                        'cachestatus'       => $cache_status['status'],
                        'display_status'    => '<b>published</b>',
                        'html_created_time' => $cache_status['html_created_time'],
                        'post_status'       => 'publish'
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
            if (($item['ID'] == null || $item['ID'] == 'homepage') && $item['post_title'] == 'Homepage') {
                $purge_id = 'homepage';
                $link = get_home_url();
            }
            if (is_numeric($item['ID'])) {
                $edit_link = get_edit_post_link($item['ID']);
            } else {
                $edit_link = '';
            }
            $purge_link = sprintf('admin-post.php?action=%1$s&nonce=%2$s&id=%3$s&type=%4$s&selected_of_type=%5$s', 'fastpixel_admin_purge_cache', $this->nonce, $purge_id, $this->type, $this->selected_post_type);
            //actions
            if ($item['cachestatus'] != 'cached') {
                $purge_cache_link = sprintf('<a class="fastpixel-purge-single" data-id="%1$s" href="%2$s">' . esc_html__('Cache Now', 'fastpixel-website-accelerator') . '</a>', $purge_id, esc_url($purge_link));
            } else {
                $purge_cache_link = sprintf('<a class="fastpixel-purge-single" data-id="%1$s" href="%2$s">' . esc_html__('Purge Cache', 'fastpixel-website-accelerator') . '</a>', $purge_id, esc_url($purge_link));
            }
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
                $delete_link = sprintf('admin-post.php?action=%1$s&nonce=%2$s&id=%3$s&selected_of_type=%4$s&type=%5$s', 'fastpixel_admin_delete_cached', $this->nonce, $purge_id, $this->selected_post_type, $this->type);
                $actions['delete_cached'] = sprintf('<a class="fastpixel-delete-cached-files-single" data-id="%1$s" href="%2$s">' . esc_html__('Delete Cached Files', 'fastpixel-website-accelerator') . '</a>', $purge_id, esc_url($delete_link));
            }
            //removing edit button for homepage
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

        //purge action for post types
        public function backend_purge_single_object($object = false, $args = []) {
            //if object is already set, return it
            if ($object) {
                return $object;
            }
            //check if id and type is present
            if (empty($args['id']) || empty($args['type']) || $args['type'] != $this->type) {
                return false;
            }
            return get_post($args['id']);
        }

        public function backend_single_permalink($permalink_to_reset, $args)
        {
            if (!empty($args['type']) && $args['type'] == $this->type) {
                if (in_array($args['selected_of_type'], array_keys($this->post_types))) {
                    if (is_numeric($args['id'])) {
                        $permalink_to_reset = get_permalink($args['id']);
                    } else if ($args['id'] == 'homepage') {
                        $permalink_to_reset = $this->be_functions->get_home_url();
                    }
                }
            }
            return $permalink_to_reset;
        }

        public function backend_bulk_single_permalink($permalink_to_reset, $args)
        {
            if (in_array($args['selected_of_type'], array_keys($this->post_types))) {
                $args['type'] = $this->type;
                $permalink_to_reset = $this->backend_single_permalink($permalink_to_reset, $args);
            }
            return $permalink_to_reset;
        }

        public function backend_bulk_statuses_type($type, $args)
        {
            if (!empty($type)) {
                return $type;
            }
            if (!empty($args['selected_of_type']) && in_array($args['selected_of_type'], array_keys($this->post_types))) {
                $type = $this->type;
            }
            return $type;
        }

        public function backend_ajax_purge_single_post_title($post_title, $args)
        {
            if (!empty($args['type']) && $args['type'] == $this->type) {
                if (in_array($args['selected_of_type'], array_keys($this->post_types))) {
                    $post_type_label = esc_html__('Post', 'fastpixel-website-accelerator');
                    if (is_numeric($args['id'])) {
                        $post = get_post($args['id']);
                        $title = $post->post_title;
                        $post_type_object = get_post_type_object($args['selected_of_type']);
                        $labels = get_post_type_labels($post_type_object);
                        $post_type_label = !empty($labels->singular_name) ? $labels->singular_name : esc_html__('Post', 'fastpixel-website-accelerator');
                    } else if ($args['id'] == 'homepage') {
                        $title = esc_html__('Homepage', 'fastpixel-website-accelerator');
                    }
                    /* translators: first %s is used for post type name, second %s is used for post title */
                    $post_title = sprintf(esc_html__('%1$s "%2$s"', 'fastpixel-website-accelerator'), $post_type_label, $title);
                }
            }
            return $post_title;
        }

        public function backend_cache_reset_type($type, $args) {
            if (!empty($args['type']) && $args['type'] == $this->type) {
                if (!empty($args['selected_of_type']) && in_array($args['selected_of_type'], array_keys($this->post_types))) {
                    if (is_numeric($args['id'])) {
                        $type = 'id';
                    } else if ($args['id'] == 'homepage') {
                        $type = 'url';
                    }
                }
            }
            return $type;
        }

        public function backend_bulk_cache_reset_type($type, $args)
        {
            if (!empty($args['selected_of_type']) && in_array($args['selected_of_type'], array_keys($this->post_types))) {
                $args['type'] = $this->type;
                $type = $this->backend_cache_reset_type($type, $args);
            }
            return $type;
        }

        public function backend_post_type_name($name, $args) {
            if (in_array($args['selected_of_type'], array_keys($this->post_types))) {
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
            //first we need to check if it is aleady excluded
            if ($status) {
                return $status;
            }
            //first we check if this class handle current post type
            if (!empty($data['selected_of_type']) && in_array($data['selected_of_type'], array_keys($this->post_types))) {
                //checking if post type is not excluded completely
                if (in_array($data['selected_of_type'], $this->excluded_post_types)) {
                    $status = true;
                } else if (!empty($data['id'])) {
                    $is_password_protected = false;
                    $is_private = false;
                    if (is_numeric($data['id'])) {
                        $is_password_protected = post_password_required($data['id']);
                        $post = get_post($data['id']);
                        if (!empty($post->post_status)) {
                            $is_private = $post->post_status == 'private' ? true : false;
                        }
                    }
                    $status = $is_password_protected || $is_private;
                }
            }
            return $status;
        }

        public function status_page_get_statuses($items, $args) {
            if ($args['type'] == $this->type) {
                if (in_array($args['selected_of_type'], array_keys($this->post_types))) {
                    foreach ($args['ids'] as $id) {
                        if ($id == 'homepage') {
                            $permalink = $this->be_functions->get_home_url();
                        } else {
                            $permalink = get_permalink($id);
                        }
                        $status_data = ['id' => $id, 'selected_of_type' => $args['selected_of_type'], 'url' => $permalink, 'extra_params' => $args['extra_params']];
                        $permalink = apply_filters('fastpixel/status_page/ajax/permalink', $permalink, $status_data);
                        $status_data['url'] = $permalink;
                        $status = $this->be_functions->cache_status_display($permalink, $status_data);
                        if ($status) {
                            $items[$id] = $status;
                        }
                    }
                }
            }
            return $items;
        }

    }

    new FASTPIXEL_Post_Types_Statuses();
}
