<?php
namespace FASTPIXEL;

use WP_Query;
defined('ABSPATH') || exit;

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

if (!class_exists('FASTPIXEL\FASTPIXEL_Posts_Table')) {
    class FASTPIXEL_Posts_Table extends \WP_List_Table
    {
        protected $selected_post_type;
        protected $posts_order = 'asc';
        protected $posts_orderby = 'ID';
        protected $functions;
        protected $serve_stale;
        protected $nonce;
        protected $be_functions;
        protected $search;

        public function __construct() {
            parent::__construct();

            $this->functions    = FASTPIXEL_Functions::get_instance();
            $this->be_functions = FASTPIXEL_Backend_Functions::get_instance();
            $this->serve_stale  = $this->functions->get_option('fastpixel_serve_stale');

            $this->selected_post_type = apply_filters('fastpixel/status_page/default_post_type', '');

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- wordpress page is accessed without any nonces.
            if (isset($_GET['ptype'])) { 
                $this->selected_post_type = sanitize_key($_GET['ptype']); //phpcs:ignore
            }
            //need to run selected post type actions
            do_action('fastpixel/status_page/selected_post_type', $this->selected_post_type);

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- wordpress page is accessed without any nonces.
            if (isset($_REQUEST['order']) ) {
                $order = sanitize_key($_REQUEST['order']); //phpcs:ignore
                if (is_string($order) && in_array(strtolower($order), array('asc', 'desc'))) {
                    $this->posts_order = $order;
                }
            }
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- wordpress page is accessed without any nonces.
            if (isset($_REQUEST['orderby'])) {
                $orderby = sanitize_key($_REQUEST['orderby']); //phpcs:ignore
                if (is_string($orderby) && in_array(strtolower($orderby), array('id'))) {
                    $this->posts_orderby = $orderby;
                }
            }
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- wordpress page is accessed without any nonces.
            if (isset($_REQUEST['s'])) {
                $search = sanitize_text_field($_REQUEST['s']); //phpcs:ignore
                if (is_string($search)) {
                    $this->search = $search;
                }
            }
            $this->nonce = wp_create_nonce('cache_status_nonce');
            $this->process_bulk_action();
        }

        public function get_columns()
        {
            return [
                'cb'             => '<input type="checkbox" />',
                'ID'             => esc_html__('ID', 'fastpixel-website-accelerator'),
                'post_title'     => esc_html__('Title', 'fastpixel-website-accelerator'),
                'url'            => esc_html__('URL', 'fastpixel-website-accelerator'),
                'display_status' => esc_html__('Post Status', 'fastpixel-website-accelerator'),
                'cache_status'   => esc_html__('Cache Status', 'fastpixel-website-accelerator')
            ];
        }

        protected function get_default_primary_column_name()
        {
            return 'post_title';
        }

        // Adding action links to column
        protected function column_url($item)
        {
            $actions = $this->row_actions(apply_filters('fastpixel/status_page/row_actions', [], $item));
            $text = apply_filters('fastpixel/status_page/column_url', $item['url'], $item);
            return sprintf('%1$s %2$s', esc_html($text), $actions);
        }

        public function prepare_items()
        {
            $columns = $this->get_columns();
            $hidden = array('ID');
            $sortable = $this->get_sortable_columns();
            $primary = $this->get_default_primary_column_name();
            $this->_column_headers = array($columns, $hidden, $sortable, $primary);

            $fastpixel_per_page = $this->get_items_per_page('fastpixel_per_page', 20);
            $per_page = apply_filters('fastpixel/status_page/posts_per_page', $fastpixel_per_page);
            $current_page = $this->get_pagenum();
            $offset = ($current_page - 1) * $per_page;
            $args = array(
                'post_type'      => $this->selected_post_type,
                'posts_per_page' => $per_page,
                'offset'         => $offset,
                'current_page'   => $current_page
            );
            if ($this->posts_order && in_array(strtolower($this->posts_order), array('asc', 'desc'))) {
                $args['order'] = $this->posts_order;
            }
            if ($this->posts_orderby && in_array(strtolower($this->posts_orderby), array('id'))) {
                $args['orderby'] = $this->posts_orderby;
            }
            if (!empty($this->search)) {
                $args['s'] = $this->search;
            }
            $this->items = $this->get_table_data($args);
            $total_items = apply_filters('fastpixel/status_page/total_items', 1);
            $total_pages = apply_filters('fastpixel/status_page/total_pages', 1);
            $this->set_pagination_args(
                array(
                    'total_items' => $total_items,
                    'per_page'    => $per_page,
                    'total_pages' => $total_pages // use ceil to round up
                )
            );
        }

        protected function get_table_data($args)
        {
            return apply_filters('fastpixel/status_page/posts_list', [], $args);
        }

        public function column_default($item, $column_name)
        {
            switch ($column_name) {
                case 'post_title':
                case 'url':
                case 'display_status':
                case 'cache_status':
                default:
                    return $item[$column_name];
            }
        }

        protected function get_sortable_columns()
        {
            $sortable_columns = array(
                'ID'   => array('ID', false)
            );
            return $sortable_columns;
        }

        protected function display_tablenav($which)
        {
            if ($which == 'top') {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- wordpress page is accessed without any nonces, no data is posted.
                $order = isset($_GET['order']) ? sanitize_key($_GET['order']) : false; //phpcs:ignore
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- wordpress page is accessed without any nonces, no data is posted.
                $orderby = isset($_GET['orderby']) ? sanitize_key($_GET['orderby']) : false; //phpcs:ignore
                echo '<form method="get" action="' . esc_url(admin_url('admin.php?page=' . FASTPIXEL_TEXTDOMAIN)) . '">'; } ?>
                <?php $this->display_search($which); ?>
                <div class="tablenav <?php echo esc_attr($which); ?>">
                    <input type="hidden" name="page" value="<?php echo esc_html(FASTPIXEL_TEXTDOMAIN); ?>" />
                    <?php echo (isset($orderby) && $orderby ? '<input type="hidden" name="orderby" value="' . esc_html($orderby) . '" />' : '')
                    . (isset($order) && $order ? '<input type="hidden" name="order" value="' . esc_html($order) . '" />' : ''); 

                    if ($this->has_items()): ?>
                        <div class="alignleft actions bulkactions">
                        <?php $this->bulk_actions($which); ?>
                        </div>
                    <?php endif;
                    $this->extra_tablenav($which);
                    $this->pagination($which);
                    ?>
                    <br class="clear" />
                </div>
            <?php
            if ($which == 'bottom') { 
                echo '</form>'; 
            } 
        }

        protected function extra_tablenav($which)
        {
            switch ($which) {
                case 'top':
                    $this->display_posts_filter('posts_filter');
                    break;
                case 'bottom':
                    break;
                default:     
                    break;
            }
        }

        protected function display_posts_filter($id = false) {
            if ($id == false) {
                return;
            }
            $options = apply_filters('fastpixel/status_page/options', '', $this->selected_post_type);
            echo wp_kses('<div class="alignleft actions">
                <select name="ptype">'.$options.'</select>
                <input type="submit" value="' .esc_html__('Filter', 'fastpixel-website-accelerator') . '" class="button action"/>
                </div>', ['div' => ['class' => []], 'select' => ['name' =>[]], 'option' => ['value' => [], 'selected' => []], 'optgroup' => ['label' => []], 'input' => ['type' => [], 'value' => [], 'class' => []]]);
        }

        public function column_cb($item)
        {
            if (!empty($item['ID'])) {
                return sprintf('<input type="checkbox" name="rid[]" value="%1$s" data-status="%2$s"/>', esc_html($item['ID']), esc_html($item['cachestatus']));
            } else {
                return '';
            }
        }

        public function get_bulk_actions()
        {
            $action = array(
                'reset' => esc_html__('Purge Cache', 'fastpixel-website-accelerator')
            );
            return $action;
        }

        public function process_bulk_action()
        {
            // If the reset bulk action is triggered
            $action = $this->current_action();
            $rids = [];
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- wordpress page is accessed without any nonces, no data is posted.
            if (isset($_GET['rid']) && is_array($_GET['rid']) && count($_GET['rid']) > 0) {
                foreach ($_GET['rid'] as $rid) { //phpcs:ignore
                    $rids[] = sanitize_key($rid);
                }
            }
            if ('reset' === $action && !empty($rids)) {
                $notices = FASTPIXEL_Notices::get_instance();
                $k = 20;
                if (count($rids) <= $k) {
                    $k = count($rids);
                } else {
                    $notices->add_flash_notice(esc_html__('Max 20 posts can be sent per one request', 'fastpixel-website-accelerator'), 'notice', false);
                }
                // loop over the array of IDs and request page cache
                $be_cache = FASTPIXEL_Backend_Cache::get_instance();
                $r_count = 0;
                for ($i = 0; $i < $k; $i++) {
                    $filter_args = ['id' => $rids[$i], 'selected_of_type' => $this->selected_post_type];
                    //handling post purge
                    $cache_reset_type = apply_filters('fastpixel/backend/bulk/reset_type', 'url', $filter_args);
                    $cache_requested = false;
                    if ($cache_reset_type == 'url') {
                        $permalink_to_reset = apply_filters('fastpixel/backend/bulk/purge_single', '', $filter_args);
                        if (!empty($permalink_to_reset)) {
                            $cache_requested = $be_cache->purge_cache_by_url($permalink_to_reset);
                        }
                    } else {
                        $cache_requested = $be_cache->purge_cache_by_id($rids[$i]);
                    }
                    if ($cache_requested) {
                        $r_count++;
                    }
                }
                $args = array(
                    'page'    => FASTPIXEL_TEXTDOMAIN, 
                    'paged'   => $this->get_pagenum(), 
                    'ptype'   => $this->selected_post_type,
                    'order'   => $this->posts_order,
                    'orderby' => $this->posts_orderby,
                    's'       => $this->search
                );
                $filter_args['count'] = $r_count;
                $post_type_name = apply_filters('fastpixel/backend/bulk/post_type_name', '', $filter_args);
                
                /* translators: %1 should be a posts count, %2 post type name */
                $notices->add_flash_notice(sprintf(esc_html__('Cache has been purged for %1$d %2$s', 'fastpixel-website-accelerator'), esc_html($r_count), esc_html($post_type_name)), 'success', false);
                wp_redirect(esc_url_raw(add_query_arg($args, admin_url('admin.php'))));
                exit;
            }
        }

        protected function display_search($which = 'top') {
            //preventing search field display for non-post types lists
            $display_search = apply_filters('fastpixel/status_page/display_search', false);
            if ($display_search) {
                $post_type_name = apply_filters('fastpixel/status_page/search/post_type_name', __('Posts', 'fastpixel-website-accelerator'), $this->selected_post_type);
                switch ($which) {
                    case 'top': 
                        ?>
                        <p class="search-box">
                        <label class="-reader-text" for="post-search-input"><?php 
                        /* translators: %s is used to display Post type */
                        printf(esc_html__('Search %s', 'fastpixel-website-accelerator'), esc_html($post_type_name)); ?>:</label>
                        <input type="search" id="post-search-input" name="s" value="">
                        <input type="submit" id="search-submit" class="button" value="<?php
                        /* translators: %s is used to display Post type */
                        printf(esc_html__('Search %s', 'fastpixel-website-accelerator'), esc_html($post_type_name)); ?>"></p>
                    <?php break;
                    case 'bottom':
                        break;
                    default:
                        break;
                }
            }
        }
    }
}
