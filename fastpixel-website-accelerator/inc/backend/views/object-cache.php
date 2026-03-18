<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;
// show error/update messages
settings_errors('fastpixel_messages');
settings_fields(FASTPIXEL_TEXTDOMAIN);
do_settings_sections(FASTPIXEL_TEXTDOMAIN . '-object-cache');
$purge_object_cache_url = wp_nonce_url(
    admin_url('admin.php?page=' . FASTPIXEL_TEXTDOMAIN . '-settings&fastpixel-action=fastpixel_purge_object_cache'),
    'fastpixel_purge_object_cache',
    'fastpixel_cache_nonce'
);
$persisted_connected_method = method_exists($this, 'get_persisted_connected_object_cache_method')
    ? $this->get_persisted_connected_object_cache_method()
    : '';
$show_purge_object_cache_button = in_array($persisted_connected_method, ['redis', 'memcached'], true);
?>
<div class="fastpixel-object-cache-footer-actions">
    <?php $this->be_functions->print_save_button(); ?>
    <a
        id="fastpixel_object_cache_purge_button"
        class="fastpixel-object-cache-purge-button"
        href="<?php echo esc_url($purge_object_cache_url . '#object-cache'); ?>"
        <?php if (!$show_purge_object_cache_button) : ?>hidden<?php endif; ?>
    >
        <i class="fastpixel-icon refresh" aria-hidden="true"></i>
        <?php esc_html_e('Purge Object Cache', 'fastpixel-website-accelerator'); ?>
    </a>
</div>
