<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Taxonomies_Statuses')) {
    class FASTPIXEL_Taxonomies_Statuses {
        protected $be_functions;
        protected $type = 'taxonomies';
        protected $taxonomies = [];
        protected $total_items = 1;
        protected $total_pages = 1;
        protected $nonce;
        protected $selected_taxonomy;

        public function __construct() {
            $this->be_functions = FASTPIXEL_Backend_Functions::get_instance();
            add_action('init', function() {
                $this->nonce = \wp_create_nonce('cache_status_nonce');
                $this->taxonomies = get_taxonomies(['public' => true], 'objects');
            });
            //adding post type option into selector
            add_filter('fastpixel/status_page/options', [$this, 'add_option'], 11, 2);
            //hook that register callbacks required to display selected post type list
            add_action('fastpixel/status_page/selected_post_type', [$this, 'selected'], 11, 1);
            //handling posts statuses on status page
            add_filter('fastpixel/status_page/get_statuses', [$this, 'status_page_get_statuses'], 11, 2);
            //handling admin bar purge button
            add_filter('fastpixel/admin_bar/purge_this_button_exclude', [$this, 'check_taxonomy_is_excluded'], 11, 2);
            //handling purge
            add_filter('fastpixel/backend/purge/single/object', [$this, 'backend_purge_single_object'], 10, 2);
            add_filter('fastpixel/backend/purge/single/permalink', [$this, 'backend_single_permalink'], 11, 2);
            add_filter('fastpixel/backend/purge/single/title', [$this, 'backend_purge_single_taxonomy_title'], 11, 2);
            add_filter('fastpixel/backend/purge/single/reset_type', [$this, 'backend_cache_reset_type'], 11, 2);
            add_filter('fastpixel/backend/purge/post_type_name', [$this, 'backend_post_type_name'], 11, 2);
            //nadling bulk actions
            add_filter('fastpixel/backend/bulk/type', [$this, 'backend_bulk_statuses_type'], 11, 2);
            //handling cached files deletion
            add_filter('fastpixel/backend/delete/single/permalink', [$this, 'backend_single_permalink'], 11, 2);
        }

        public function selected($post_type) {
            if (!in_array($post_type, array_keys($this->taxonomies))) {
                return false;
            }
            $this->selected_taxonomy = $post_type;
            add_filter('fastpixel/status_page/posts_per_page', [$this, 'posts_per_page'], 10, 1);
            add_filter('fastpixel/status_page/posts_list', [$this, 'get_list'], 10, 2);
            add_filter('fastpixel/status_page/total_items', [$this, 'total_items'], 10, 1);
            add_filter('fastpixel/status_page/total_pages', [$this, 'total_pages'], 10, 1);
            add_filter('fastpixel/status_page/column_url', [$this, 'column_url'], 10, 2);
            add_filter('fastpixel/status_page/row_actions', [$this, 'row_actions'], 10, 2);
            //handling bulk cache purge
            add_filter('fastpixel/backend/bulk/reset_type', [$this, 'backend_bulk_cache_reset_type'], 10, 2);
            add_filter('fastpixel/backend/bulk/post_type_name', [$this, 'backend_post_type_name'], 10, 2);
            add_filter('fastpixel/backend/bulk/purge_single', [$this, 'backend_bulk_single_permalink'], 10, 2);
            add_action('admin_enqueue_scripts', function () {
                wp_localize_script('fastpixel-backend', 'fastpixel_backend_status', [
                    'type'               => $this->type, 
                    'selected_of_type'   => $this->selected_taxonomy,
                    'delete_cached_link' => sprintf('admin-post.php?action=%1$s&nonce=%2$s&selected_of_type=%3$s&type=%4$s&id=', 'fastpixel_admin_delete_cached', $this->nonce, $this->selected_taxonomy, $this->type),
                    'extra_params'       => apply_filters('fastpixel/status_page/extra_params', [])
                ]);
            });
        }

        public function posts_per_page($posts_per_page) {
            return $posts_per_page;
        }
        public function add_option($options, $selected) {
            $opts = '<optgroup label="' . esc_html__('Taxonomies', 'fastpixel-website-accelerator') . '">';
            foreach($this->taxonomies as $taxonomy) {
                $opts .= '<option value="' . $taxonomy->name . '" ' . ($selected == $taxonomy->name ? 'selected="selected"' : '') . '>'.$taxonomy->label.'</option>';
            }
            $opts .= '</optgroup>';
            return $options . $opts;
        }

        public function get_list($list, $args) {
            if ($args['post_type'] != $this->selected_taxonomy) {
                return [];
            }
            $count_args = array(
                'taxonomy'   => $this->selected_taxonomy, 
                'hide_empty' => false,
                'fields'     => 'count'
            );
            $count_query = new \WP_Term_Query($count_args);
            $this->total_items = $count_query->get_terms();
            $this->total_pages = ceil($this->total_items / $args['posts_per_page']);

            $terms = get_terms([
                'taxonomy'   => $this->selected_taxonomy,
                'hide_empty' => false,
                'orderby'    => !empty($args['orderby']) ? $args['orderby'] : 'id',
                'order'      => !empty($args['order']) ? $args['order'] : 'asc',
                'offset'     => $args['offset'],
                'number'     => $args['posts_per_page']
            ]);
            foreach($terms as $term) {
                $url = get_term_link($term);
                $status_data = ['id' => $term->term_id, 'taxonomy' => $this->selected_taxonomy];
                $url = apply_filters('fastpixel/status_page/permalink', $url, $status_data);
                $cache_status = $this->be_functions->cache_status_display($url, $status_data);
                $list[] = [
                    'ID'                => $term->term_id,
                    'post_title'        => $term->name,
                    'url'               => urldecode($url),
                    'cache_status'      => $cache_status['status_display'],
                    'cachestatus'       => $cache_status['status'],
                    'display_status'    => '<b>' . __('published', 'fastpixel-website-accelerator') . '</b>',
                    'html_created_time' => isset($cache_status['html_created_time']) ? $cache_status['html_created_time'] : '',
                ];
            }
            return $list;
        }

        public function total_items($total_items)
        {
            return $this->total_items;
        }

        public function total_pages($total_pages)
        {
            return $this->total_pages;
        }

        public function column_url($text, $item) {
            return $text;
        }

        public function row_actions($actions, $item) {
            //setting links
            if (empty($item['ID'])) {
                return [];
            }
            $term = get_term($item['ID']);
            $link = get_term_link($term);
            $purge_id = $item['ID'];

            $edit_link = get_edit_term_link($term);
            $purge_link = sprintf('admin-post.php?action=%1$s&nonce=%2$s&id=%3$s&type=%4$s&selected_of_type=%5$s', 'fastpixel_admin_purge_cache', $this->nonce, $purge_id, $this->type, $this->selected_taxonomy);
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
                $delete_link = sprintf('admin-post.php?action=%1$s&nonce=%2$s&id=%3$s&selected_of_type=%4$s&type=%5$s', 'fastpixel_admin_delete_cached', $this->nonce, $purge_id, $this->selected_taxonomy, $this->type);
                $actions['delete_cached'] = sprintf('<a class="fastpixel-delete-cached-files-single" data-id="%1$s" href="%2$s">' . esc_html__('Delete Cached Files', 'fastpixel-website-accelerator') . '</a>', $purge_id, esc_url($delete_link));
            }
            if ($item['cachestatus'] == 'excluded') {
                unset($actions['purge_cache']);
            }
            return $actions;
        }

        //purge action for terms
        public function backend_purge_single_object($object = false, $args = [])
        {
            //if object is already set, return it
            if ($object) {
                return $object;
            }
            //check if id and type is present
            if (empty($args['id']) || empty($args['type']) || $args['type'] != $this->type) {
                return false;
            }
            return get_term($args['id']);
        }

        public function backend_single_permalink($permalink, $args) {
            if (!empty($args['type']) && $args['type'] == $this->type) {
                if (in_array($args['selected_of_type'], array_keys($this->taxonomies))) {
                    $term = get_term($args['id']);
                    $permalink = get_term_link($term);
                }
            }
            return $permalink;
        }

        public function backend_bulk_single_permalink($permalink_to_reset, $args)
        {
            if (in_array($args['selected_of_type'], array_keys($this->taxonomies))) {
                $args['type'] = $this->type;
                $permalink_to_reset = $this->backend_single_permalink($permalink_to_reset, $args);
            }
            return $permalink_to_reset;
        }

        public function backend_cache_reset_type($type, $args)
        {
            if (!empty($args['type']) && $args['type'] == $this->type) {
                if (in_array($args['selected_of_type'], array_keys($this->taxonomies))) {
                    $type = 'id';
                }
            }
            return $type;
        }

        public function backend_bulk_cache_reset_type($type, $args)
        {
            if (!empty($args['selected_of_type']) && in_array($args['selected_of_type'], array_keys($this->taxonomies))) {
                $args['type'] = $this->type;
                $type = $this->backend_cache_reset_type($type, $args);
            }
            return $type;
        }

        public function backend_bulk_statuses_type($type, $args)
        {
            if (!empty($type)) {
                return $type;
            }
            if (!empty($args['selected_of_type']) && in_array($args['selected_of_type'], array_keys($this->taxonomies))) {
                $type = $this->type;
            }
            return $type;
        }

        public function status_page_get_statuses($items, $args)
        {
            if (!empty($args['type']) && $args['type'] == $this->type) {
                if (in_array($args['selected_of_type'], array_keys($this->taxonomies))) {
                    foreach ($args['ids'] as $id) {
                        $term = get_term($id, $args['selected_of_type']);
                        $permalink = get_term_link($term);
                        $status_data = ['id' => $id, 'taxonomy' => $args['selected_of_type'], 'url' => $permalink, 'extra_params' => $args['extra_params']];
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

        public function backend_purge_single_taxonomy_title($title, $args)
        {
            if (!empty($args['type']) && $args['type'] == $this->type) {
                if (in_array($args['selected_of_type'], array_keys($this->taxonomies))) {
                    if (is_numeric($args['id'])) {
                        $term = get_term($args['id'], $args['selected_of_type']);
                        $taxonomy_object = get_taxonomy($args['selected_of_type']);
                        $labels = get_taxonomy_labels($taxonomy_object);
                        $label = !empty($labels->singular_name) ? $labels->singular_name : esc_html__('Taxonomy', 'fastpixel-website-accelerator');
                        /* translators: first %s is used for taxonomy type name, second %s is used for taxonomy term title */
                        $title = sprintf(esc_html__('%1$s "%2$s"', 'fastpixel-website-accelerator'), $label, $term->name);
                    }
                }
            }
            return $title;
        }

        public function backend_post_type_name($name, $args)
        {
            if (in_array($args['selected_of_type'], array_keys($this->taxonomies))) {
                foreach ($this->taxonomies as $taxonomy) {
                    if ($taxonomy->name == $this->selected_taxonomy) {
                        $labels = get_taxonomy_labels($taxonomy);
                        if ($args['count'] == 1) {
                            $name = strtolower($labels->singular_name);
                        } else {
                            $name = strtolower($labels->name);
                        }
                        break;
                    }
                }
            }
            return $name;
        }
    }

    if (is_admin()) {
        new FASTPIXEL_Taxonomies_Statuses();
    }
}
