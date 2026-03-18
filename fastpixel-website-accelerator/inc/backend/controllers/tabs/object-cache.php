<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

// Defensive guard: if this admin tab file is accidentally copied as wp-content/object-cache.php,
// bail out early to avoid a fatal error during WordPress object-cache bootstrap.
if (defined('WP_CONTENT_DIR') && realpath(__FILE__) === realpath(WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'object-cache.php')) {
    return;
}

if (!class_exists('FASTPIXEL\FASTPIXEL_Tab_Object_Cache')) {
    class FASTPIXEL_Tab_Object_Cache extends FASTPIXEL_UI_Tab
    {
        protected $name = 'Object Cache';
        protected $slug = 'object-cache';
        protected $order = 4.25;

        public function __construct() {
            parent::__construct();
            $this->name = esc_html__('Object Cache', 'fastpixel-website-accelerator');
            add_action('fastpixel/tabs/loaded', [$this, 'save_options'], 98);
            add_action('admin_footer', [$this, 'print_object_cache_toggle_script']);
        }

        public function settings() {
            if (!$this->check_capabilities()) {
                return;
            }

            register_setting(FASTPIXEL_TEXTDOMAIN, 'fastpixel_object_cache_enabled', ['type' => 'boolean']);
            register_setting(FASTPIXEL_TEXTDOMAIN, 'fastpixel_object_cache_method', ['type' => 'string']);
            register_setting(FASTPIXEL_TEXTDOMAIN, 'fastpixel_object_cache_host', ['type' => 'string']);
            register_setting(FASTPIXEL_TEXTDOMAIN, 'fastpixel_object_cache_port', ['type' => 'string']);
            register_setting(FASTPIXEL_TEXTDOMAIN, 'fastpixel_object_cache_username', ['type' => 'string']);
            register_setting(FASTPIXEL_TEXTDOMAIN, 'fastpixel_object_cache_password', ['type' => 'string']);
            register_setting(FASTPIXEL_TEXTDOMAIN, 'fastpixel_object_cache_dbid', ['type' => 'integer']);
            register_setting(FASTPIXEL_TEXTDOMAIN, 'fastpixel_object_cache_default_lifetime', ['type' => 'integer']);
            register_setting(FASTPIXEL_TEXTDOMAIN, 'fastpixel_object_cache_global_groups', ['type' => 'string']);
            register_setting(FASTPIXEL_TEXTDOMAIN, 'fastpixel_object_cache_do_not_cache', ['type' => 'string']);
            register_setting(FASTPIXEL_TEXTDOMAIN, 'fastpixel_object_cache_persistent', ['type' => 'boolean']);
            register_setting(FASTPIXEL_TEXTDOMAIN, 'fastpixel_object_cache_cache_wp_admin', ['type' => 'boolean']);
            register_setting(FASTPIXEL_TEXTDOMAIN, 'fastpixel_object_cache_store_transients', ['type' => 'boolean']);
            register_setting(FASTPIXEL_TEXTDOMAIN, 'fastpixel_object_cache_connection_verified', ['type' => 'boolean']);
            register_setting(FASTPIXEL_TEXTDOMAIN, 'fastpixel_object_cache_connection_verified_method', ['type' => 'string']);
            register_setting(FASTPIXEL_TEXTDOMAIN, 'fastpixel_object_cache_connection_verified_message', ['type' => 'string']);

            add_settings_section(
                'fastpixel_object_cache_section',
                '',
                '__return_false',
                FASTPIXEL_TEXTDOMAIN . '-object-cache'
            );

            add_settings_field(
                'fastpixel_object_cache_block',
                '',
                [$this, 'field_object_cache_cb'],
                FASTPIXEL_TEXTDOMAIN . '-object-cache',
                'fastpixel_object_cache_section',
                ['class' => 'fastpixel-settings-form-row']
            );
        }

        public function field_object_cache_cb($args) {
            $this->initialize_object_cache_defaults();
            $method  = $this->normalize_object_cache_method($this->functions->get_option('fastpixel_object_cache_method', 'disabled'));
            $redis_extension_enabled = class_exists('Redis');
            $memcached_extension_enabled = class_exists('Memcached');
            if (($method === 'redis' && !$redis_extension_enabled) || ($method === 'memcached' && !$memcached_extension_enabled)) {
                $method = 'disabled';
            }
            $enabled = $method !== 'disabled';
            $show_advanced_settings = $enabled;
            $host    = $this->functions->get_option('fastpixel_object_cache_host', '127.0.0.1');
            $port    = $this->functions->get_option('fastpixel_object_cache_port', '');
            $user    = $this->functions->get_option('fastpixel_object_cache_username', '');
            $pass    = $this->functions->get_option('fastpixel_object_cache_password', '');
            $dbid    = $this->functions->get_option('fastpixel_object_cache_dbid', 0);
            $ttl     = $this->functions->get_option('fastpixel_object_cache_default_lifetime', 360);
            $global_groups = (array) $this->functions->get_option('fastpixel_object_cache_global_groups', $this->get_default_global_groups());
            $do_not_cache  = (array) $this->functions->get_option('fastpixel_object_cache_do_not_cache', $this->get_default_do_not_cache_groups());
            $persistent = $this->functions->get_option('fastpixel_object_cache_persistent', 1);
            $cache_wp_admin = $this->functions->get_option('fastpixel_object_cache_cache_wp_admin', 1);
            $store_transients = $this->functions->get_option('fastpixel_object_cache_store_transients', 1);
            $connection_method_label = $method === 'redis'
                ? esc_html__('Redis', 'fastpixel-website-accelerator')
                : ($method === 'memcached'
                    ? esc_html__('Memcached', 'fastpixel-website-accelerator')
                    : esc_html__('Redis/Memcached', 'fastpixel-website-accelerator'));
            $show_required_mark = $show_advanced_settings && (
                $this->normalize_string($host) === '' ||
                $this->normalize_string($port) === ''
            );
            $required_mark_html = $show_required_mark
                ? ' <span class="fastpixel-required-mark" title="' . esc_attr__('Required', 'fastpixel-website-accelerator') . '">!</span>'
                : '';
            $object_cache_docs_url = 'https://fastpixel.io/docs/fastpixel-object-cache/';
            ?>

            <div class="fastpixel-object-cache-layout">
                <div class="fastpixel-object-cache-top-help">
                    <span class="fastpixel-object-cache-top-help-trigger">
                        <button
                            type="button"
                            class="fastpixel-object-cache-top-help-button"
                            aria-label="<?php esc_attr_e('Object cache info', 'fastpixel-website-accelerator'); ?>"
                        >
                            <img
                                src="<?php echo esc_url(FASTPIXEL_PLUGIN_URL . 'icons/info.svg'); ?>"
                                alt=""
                                class="fastpixel-object-cache-top-help-icon"
                            />
                        </button>
                        <span class="fastpixel-object-cache-top-help-tooltip" role="tooltip">
                            <?php esc_html_e('Object Cache keeps frequently used data ready in memory, so your site serves repeat requests faster, reduces database and network overhead, and feels snappier for visitors.', 'fastpixel-website-accelerator'); ?>
                        </span>
                    </span>
                </div>

                <div class="fastpixel-object-cache-method-block">
                <setting id="fastpixel_object_cache_method-container" class="fastpixel-horizontal-selector">
                    <content>
                        <name><?php esc_html_e('Method', 'fastpixel-website-accelerator'); ?></name>
                        <div class="fastpixel-horizontal-options">
                            <label class="fastpixel-horizontal-selector-label">
                                <input type="radio" class="fastpixel-horizontal-selector-radio" name="fastpixel_object_cache_method" value="disabled" <?php checked($method, 'disabled'); ?> />
                                <span data-base-label="<?php echo esc_attr__('Disabled', 'fastpixel-website-accelerator'); ?>"><?php esc_html_e('Disabled', 'fastpixel-website-accelerator'); ?></span>
                            </label>

                            <label class="fastpixel-horizontal-selector-label<?php echo !$redis_extension_enabled ? ' fastpixel-object-cache-method-option-disabled' : ''; ?>"<?php echo !$redis_extension_enabled ? ' tabindex="0"' : ''; ?>>
                                <input type="radio" class="fastpixel-horizontal-selector-radio" name="fastpixel_object_cache_method" value="redis" <?php checked($method, 'redis'); ?> <?php disabled(!$redis_extension_enabled); ?> />
                                <span data-base-label="<?php echo esc_attr__('Redis', 'fastpixel-website-accelerator'); ?>"><?php esc_html_e('Redis', 'fastpixel-website-accelerator'); ?></span>
                                <?php if (!$redis_extension_enabled) : ?>
                                <span class="fastpixel-object-cache-top-help-tooltip fastpixel-object-cache-method-disabled-tooltip" role="tooltip">
                                    <?php esc_html_e('Redis PHP extension is not enabled. Enable the Redis PHP extension on your server to use this cache method.', 'fastpixel-website-accelerator'); ?>
                                </span>
                                <?php endif; ?>
                            </label>

                            <label class="fastpixel-horizontal-selector-label<?php echo !$memcached_extension_enabled ? ' fastpixel-object-cache-method-option-disabled' : ''; ?>"<?php echo !$memcached_extension_enabled ? ' tabindex="0"' : ''; ?>>
                                <input type="radio" class="fastpixel-horizontal-selector-radio" name="fastpixel_object_cache_method" value="memcached" <?php checked($method, 'memcached'); ?> <?php disabled(!$memcached_extension_enabled); ?> />
                                <span data-base-label="<?php echo esc_attr__('Memcached', 'fastpixel-website-accelerator'); ?>"><?php esc_html_e('Memcached', 'fastpixel-website-accelerator'); ?></span>
                                <?php if (!$memcached_extension_enabled) : ?>
                                <span class="fastpixel-object-cache-top-help-tooltip fastpixel-object-cache-method-disabled-tooltip" role="tooltip">
                                    <?php esc_html_e('Memcached PHP extension is not enabled. Enable the Memcached PHP extension on your server to use this cache method.', 'fastpixel-website-accelerator'); ?>
                                </span>
                                <?php endif; ?>
                            </label>
                        </div>
                        <info>
                            <p class="fastpixel-object-cache-method-description fastpixel-setting-description"><?php esc_html_e('Select a caching method supported by your hosting, or leave it disabled.', 'fastpixel-website-accelerator'); ?></p>
                        </info>
                    </content>
                </setting>
            </div>

            <div id="fastpixel_object_cache_settings" class="fastpixel-fadein-options" <?php if (!$show_advanced_settings) echo 'style="display:none"'; ?>>
                <div class="fastpixel-object-cache-group">
                    <h5
                        class="fastpixel-object-cache-group-title fastpixel-setting-subsection-title fastpixel-object-cache-connection-title"
                        id="fastpixel_object_cache_connection_title"
                        data-default-template="<?php echo esc_attr__('%s Connection Details', 'fastpixel-website-accelerator'); ?>"
                        data-connected-template="<?php echo esc_attr__('%s Connected', 'fastpixel-website-accelerator'); ?>"
                    >
                        <span class="fastpixel-object-cache-connection-title-text">
                            <?php
                            printf(
                                esc_html__('%s Connection Details', 'fastpixel-website-accelerator'),
                                esc_html($connection_method_label)
                            );
                            ?>
                        </span>
                        <span class="fastpixel-object-cache-connection-check" aria-hidden="true">&#10003;</span>
                    </h5>
                    <p
                        class="fastpixel-object-cache-group-hint fastpixel-setting-description"
                        id="fastpixel_object_cache_connection_hint"
                        data-template="<?php echo esc_attr__('Authentication credentials for %s may be necessary based on your hosting setup.', 'fastpixel-website-accelerator'); ?>"
                    >
                        <?php
                        printf(
                            esc_html__('Authentication credentials for %s may be necessary based on your hosting setup.', 'fastpixel-website-accelerator'),
                            esc_html($connection_method_label)
                        );
                        ?>
                    </p>
                    <div class="fastpixel-object-cache-grid-two">
                        <p class="fastpixel-object-cache-field">
                            <label for="fastpixel_object_cache_host"><?php echo wp_kses_post(esc_html__('Host', 'fastpixel-website-accelerator') . $required_mark_html); ?></label><br/>
                            <input id="fastpixel_object_cache_host" type="text" name="fastpixel_object_cache_host" value="<?php echo esc_attr($host); ?>" class="regular-text" placeholder="hostname, IP, or /path/to/socket" />
                            <?php if ($show_required_mark) : ?>
                            <span class="fastpixel-object-cache-inline-help fastpixel-setting-description"><?php echo wp_kses_post('Host: ' . esc_html__('Your Memcached/Redis hostname or IP address. If you are using a UNIX socket, host should be set to /path/to/memcached.sock (or /path/to/redis.sock).', 'fastpixel-website-accelerator')); ?></span>
                            <?php endif; ?>
                        </p>

                        <p class="fastpixel-object-cache-field">
                            <label for="fastpixel_object_cache_port"><?php echo wp_kses_post(esc_html__('Port', 'fastpixel-website-accelerator') . $required_mark_html); ?></label><br/>
                            <input id="fastpixel_object_cache_port" type="text" name="fastpixel_object_cache_port" value="<?php echo esc_attr($port); ?>" class="regular-text" placeholder="11211, 6379, 0, or custom" />
                            <?php if ($show_required_mark) : ?>
                            <span class="fastpixel-object-cache-inline-help fastpixel-setting-description"><?php echo wp_kses_post('Port: ' . esc_html__('Default port for Memcached is 11211 and default port for Redis is 6379. If you are using a UNIX socket, port should be set to 0.', 'fastpixel-website-accelerator')); ?></span>
                            <?php endif; ?>
                        </p>
                        <p class="fastpixel-object-cache-field">
                            <label for="fastpixel_object_cache_username"><?php esc_html_e('Username', 'fastpixel-website-accelerator'); ?></label><br/>
                            <input id="fastpixel_object_cache_username" type="text" name="fastpixel_object_cache_username" value="<?php echo esc_attr($user); ?>" class="regular-text" />
                        </p>

                        <p class="fastpixel-object-cache-field">
                            <label for="fastpixel_object_cache_password"><?php esc_html_e('Password', 'fastpixel-website-accelerator'); ?></label><br/>
                            <input id="fastpixel_object_cache_password" type="password" name="fastpixel_object_cache_password" value="<?php echo esc_attr($pass); ?>" class="regular-text" />
                        </p>
                    </div>
                </div>

                <details class="fastpixel-object-cache-advanced-settings">
                    <summary class="fastpixel-object-cache-advanced-summary"><?php esc_html_e('Advanced Settings', 'fastpixel-website-accelerator'); ?></summary>
                    <div class="fastpixel-object-cache-group fastpixel-object-cache-group-advanced">
                        <h5 class="fastpixel-object-cache-group-title fastpixel-setting-subsection-title"><?php esc_html_e('Behavior (Optional)', 'fastpixel-website-accelerator'); ?></h5>
                        <p class="fastpixel-object-cache-group-hint fastpixel-setting-description"><?php esc_html_e('Optional settings. Most sites can keep defaults.', 'fastpixel-website-accelerator'); ?></p>

                        <p class="fastpixel-object-cache-field">
                            <label for="fastpixel_object_cache_dbid">
                                <span class="fastpixel-object-cache-label-with-docs">
                                    <?php esc_html_e('Redis DB ID (Redis only)', 'fastpixel-website-accelerator'); ?>
                                    <?php $this->render_object_cache_docs_tooltip_link($object_cache_docs_url); ?>
                                </span>
                            </label><br/>
                            <input id="fastpixel_object_cache_dbid" type="number" name="fastpixel_object_cache_dbid" value="<?php echo esc_attr($dbid); ?>" class="small-text" min="0" />
                        </p>

                        <p class="fastpixel-object-cache-field">
                            <label for="fastpixel_object_cache_default_lifetime">
                                <span class="fastpixel-object-cache-label-with-docs">
                                    <?php esc_html_e('Default Object Lifetime (seconds)', 'fastpixel-website-accelerator'); ?>
                                    <?php $this->render_object_cache_docs_tooltip_link($object_cache_docs_url); ?>
                                </span>
                            </label><br/>
                            <input id="fastpixel_object_cache_default_lifetime" type="number" name="fastpixel_object_cache_default_lifetime" value="<?php echo esc_attr($ttl); ?>" class="small-text" min="0" />
                        </p>

                        <p class="fastpixel-object-cache-field">
                            <label for="fastpixel_object_cache_global_groups">
                                <span class="fastpixel-object-cache-label-with-docs">
                                    <?php esc_html_e('Global Groups (one per line)', 'fastpixel-website-accelerator'); ?>
                                    <?php $this->render_object_cache_docs_tooltip_link($object_cache_docs_url); ?>
                                </span>
                            </label><br/>
                            <textarea id="fastpixel_object_cache_global_groups" name="fastpixel_object_cache_global_groups" rows="4" class="large-text"><?php echo esc_textarea(implode("\n", $global_groups)); ?></textarea>
                        </p>

                        <p class="fastpixel-object-cache-field">
                            <label for="fastpixel_object_cache_do_not_cache">
                                <span class="fastpixel-object-cache-label-with-docs">
                                    <?php esc_html_e('Do Not Cache Groups (one per line)', 'fastpixel-website-accelerator'); ?>
                                    <?php $this->render_object_cache_docs_tooltip_link($object_cache_docs_url); ?>
                                </span>
                            </label><br/>
                            <textarea id="fastpixel_object_cache_do_not_cache" name="fastpixel_object_cache_do_not_cache" rows="3" class="large-text"><?php echo esc_textarea(implode("\n", $do_not_cache)); ?></textarea>
                        </p>

                        <p class="fastpixel-object-cache-field">
                            <span class="fastpixel-object-cache-label-with-docs">
                                <label>
                                    <input type="checkbox" name="fastpixel_object_cache_persistent" value="1" <?php checked(1, (int)$persistent); ?> />
                                    <?php esc_html_e('Use persistent connections', 'fastpixel-website-accelerator'); ?>
                                </label>
                                <?php $this->render_object_cache_docs_tooltip_link($object_cache_docs_url); ?>
                            </span>
                        </p>

                        <p class="fastpixel-object-cache-field">
                            <span class="fastpixel-object-cache-label-with-docs">
                                <label>
                                    <input type="checkbox" name="fastpixel_object_cache_cache_wp_admin" value="1" <?php checked(1, (int)$cache_wp_admin); ?> />
                                    <?php esc_html_e('Cache WP-Admin operations', 'fastpixel-website-accelerator'); ?>
                                </label>
                                <?php $this->render_object_cache_docs_tooltip_link($object_cache_docs_url); ?>
                            </span>
                        </p>

                        <p class="fastpixel-object-cache-field">
                            <span class="fastpixel-object-cache-label-with-docs">
                                <label>
                                    <input type="checkbox" name="fastpixel_object_cache_store_transients" value="1" <?php checked(1, (int)$store_transients); ?> />
                                    <?php esc_html_e('Store transients in object cache', 'fastpixel-website-accelerator'); ?>
                                </label>
                                <?php $this->render_object_cache_docs_tooltip_link($object_cache_docs_url); ?>
                            </span>
                        </p>
                    </div>
                </details>

                <div class="fastpixel-object-cache-actions">
                    <span id="fastpixel_object_cache_test_result" class="fastpixel-object-cache-test-result" aria-live="polite"></span>
                    <button type="button" id="fastpixel_object_cache_test_connection" class="fastpixel-object-cache-test-button">
                        <?php esc_html_e('Test Connection', 'fastpixel-website-accelerator'); ?>
                    </button>
                </div>

            </div>
            </div>

            <?php
        }

        public function save_options() {
            if (sanitize_text_field($_SERVER['REQUEST_METHOD']) !== 'POST' || (defined('DOING_AJAX') && DOING_AJAX) ||
                check_admin_referer('fastpixel-settings', 'fastpixel-nonce') == false ||
                empty($_POST['fastpixel-action']) || sanitize_key($_POST['fastpixel-action']) != 'save_settings') {
                return;
            }

            $method = $this->normalize_object_cache_method(sanitize_text_field($_POST['fastpixel_object_cache_method'] ?? 'disabled'));
            if ($method === 'redis' && !class_exists('Redis')) {
                $method = 'disabled';
            }
            if ($method === 'memcached' && !class_exists('Memcached')) {
                $method = 'disabled';
            }
            $object_cache_enabled = $method !== 'disabled' ? 1 : 0;
            $this->functions->update_option('fastpixel_object_cache_enabled', $object_cache_enabled);
            $this->functions->update_option('fastpixel_object_cache_method', $method);
            $this->functions->update_option('fastpixel_object_cache_host', sanitize_text_field($_POST['fastpixel_object_cache_host'] ?? ''));
            $this->functions->update_option('fastpixel_object_cache_port', sanitize_text_field($_POST['fastpixel_object_cache_port'] ?? ''));
            $this->functions->update_option('fastpixel_object_cache_username', sanitize_text_field($_POST['fastpixel_object_cache_username'] ?? ''));
            $this->functions->update_option('fastpixel_object_cache_password', sanitize_text_field($_POST['fastpixel_object_cache_password'] ?? ''));
            $this->functions->update_option('fastpixel_object_cache_dbid', intval($_POST['fastpixel_object_cache_dbid'] ?? 0));
            $this->functions->update_option('fastpixel_object_cache_default_lifetime', intval($_POST['fastpixel_object_cache_default_lifetime'] ?? 360));

            $global_groups_raw = isset($_POST['fastpixel_object_cache_global_groups']) ? sanitize_textarea_field($_POST['fastpixel_object_cache_global_groups']) : '';
            $do_not_cache_raw  = isset($_POST['fastpixel_object_cache_do_not_cache']) ? sanitize_textarea_field($_POST['fastpixel_object_cache_do_not_cache']) : '';

            $global_groups = array_values(array_filter(array_map('trim', preg_split("/\r\n|\n|\r/", $global_groups_raw))));
            $do_not_cache  = array_values(array_filter(array_map('trim', preg_split("/\r\n|\n|\r/", $do_not_cache_raw))));

            $this->functions->update_option('fastpixel_object_cache_global_groups', $global_groups);
            $this->functions->update_option('fastpixel_object_cache_do_not_cache', $do_not_cache);

            $persistent = isset($_POST['fastpixel_object_cache_persistent']) ? 1 : 0;
            $this->functions->update_option('fastpixel_object_cache_persistent', $persistent);

            $cache_wp_admin = isset($_POST['fastpixel_object_cache_cache_wp_admin']) ? 1 : 0;
            $this->functions->update_option('fastpixel_object_cache_cache_wp_admin', $cache_wp_admin);

            $store_transients = isset($_POST['fastpixel_object_cache_store_transients']) ? 1 : 0;
            $this->functions->update_option('fastpixel_object_cache_store_transients', $store_transients);
            $this->functions->update_option('fastpixel_object_cache_safe_mode', 0);

            $object_cache_data = [
                'enabled' => (bool) $object_cache_enabled,
                'method' => $this->functions->get_option('fastpixel_object_cache_method'),
                'host' => $this->functions->get_option('fastpixel_object_cache_host'),
                'port' => $this->functions->get_option('fastpixel_object_cache_port'),
                'username' => $this->functions->get_option('fastpixel_object_cache_username'),
                'password' => $this->functions->get_option('fastpixel_object_cache_password'),
                'dbid' => $this->functions->get_option('fastpixel_object_cache_dbid'),
                'default_lifetime' => $this->functions->get_option('fastpixel_object_cache_default_lifetime'),
                'global_groups' => $this->functions->get_option('fastpixel_object_cache_global_groups'),
                'do_not_cache' => $this->functions->get_option('fastpixel_object_cache_do_not_cache'),
                'persistent' => (bool)$persistent,
                'cache_wp_admin' => (bool)$cache_wp_admin,
                'store_transients' => (bool)$store_transients,
                'safe_mode' => false
            ];

            $connection_status = $this->persist_object_cache_connection_status($object_cache_data);
            $object_cache_data['connection_verified'] = !empty($connection_status['verified']);
            $object_cache_data['connection_message'] = $connection_status['message'] ?? '';

            if ($object_cache_enabled && empty($connection_status['verified']) && !empty($connection_status['message'])) {
                $method_label = $method === 'memcached'
                    ? esc_html__('Memcached', 'fastpixel-website-accelerator')
                    : esc_html__('Redis', 'fastpixel-website-accelerator');
                FASTPIXEL_Notices::get_instance()->add_flash_notice(
                    sprintf(
                        esc_html__('Object Cache settings were saved, but the %1$s connection test failed: %2$s', 'fastpixel-website-accelerator'),
                        $method_label,
                        esc_html($connection_status['message'])
                    ),
                    'warning',
                    false
                );
            }

            do_action('fastpixel/objectcache/after_save', $object_cache_data);
        }

        protected function persist_object_cache_connection_status(array $settings) {
            $method = $this->normalize_object_cache_method($settings['method'] ?? 'disabled');
            $verified = false;
            $verified_method = '';
            $message = '';

            if ($method === 'redis' || $method === 'memcached') {
                $connection = $method === 'redis'
                    ? $this->test_redis_connection($settings)
                    : $this->test_memcached_connection($settings);

                $verified = !empty($connection['ok']);
                $verified_method = $verified ? $method : '';
                $message = !empty($connection['message']) ? $this->normalize_string($connection['message']) : '';
            }

            $this->functions->update_option('fastpixel_object_cache_connection_verified', $verified ? 1 : 0);
            $this->functions->update_option('fastpixel_object_cache_connection_verified_method', $verified_method);
            $this->functions->update_option('fastpixel_object_cache_connection_verified_message', $message);

            return [
                'verified' => $verified,
                'method' => $verified_method,
                'message' => $message,
            ];
        }

        protected function print_object_cache_status_row($label, $ok, $value, $state = 'failed') {
            $status_class = 'is-negative';
            if ($state === 'skipped') {
                $status_class = 'is-neutral';
            } elseif ($ok) {
                $status_class = 'is-positive';
            }
            ?>
            <div class="fastpixel-object-cache-status-row">
                <span class="fastpixel-object-cache-status-label"><?php echo esc_html($label); ?></span>
                <span class="fastpixel-object-cache-status-badge <?php echo esc_attr($status_class); ?>"><?php echo esc_html($value); ?></span>
            </div>
            <?php
        }

        protected function get_object_cache_status(array $settings) {
            $redis_enabled = class_exists('Redis');
            $memcached_enabled = class_exists('Memcached');

            $status = [
                'redis_extension' => [
                    'ok' => $redis_enabled,
                    'state' => $redis_enabled ? 'passed' : 'failed',
                    'value' => $redis_enabled
                        ? esc_html__('Enabled', 'fastpixel-website-accelerator')
                        : esc_html__('Disabled', 'fastpixel-website-accelerator'),
                ],
                'memcached_extension' => [
                    'ok' => $memcached_enabled,
                    'state' => $memcached_enabled ? 'passed' : 'failed',
                    'value' => $memcached_enabled
                        ? esc_html__('Enabled', 'fastpixel-website-accelerator')
                        : esc_html__('Disabled', 'fastpixel-website-accelerator'),
                ],
                'connection_test' => [
                    'ok' => false,
                    'state' => 'failed',
                    'value' => esc_html__('Failed', 'fastpixel-website-accelerator'),
                ],
            ];

            $method = $this->normalize_object_cache_method($settings['method'] ?? 'disabled');
            if ($method === 'disabled') {
                $status['connection_test']['state'] = 'skipped';
                $status['connection_test']['value'] = esc_html__('Skipped (method disabled)', 'fastpixel-website-accelerator');
                return $status;
            }

            if ($method === 'memcached') {
                if (!$memcached_enabled) {
                    $status['connection_test']['value'] = esc_html__('Failed (Memcached extension disabled)', 'fastpixel-website-accelerator');
                    return $status;
                }
                $connection = $this->test_memcached_connection($settings);
            } else {
                if (!$redis_enabled) {
                    $status['connection_test']['value'] = esc_html__('Failed (Redis extension disabled)', 'fastpixel-website-accelerator');
                    return $status;
                }
                $connection = $this->test_redis_connection($settings);
            }

            $status['connection_test']['ok'] = !empty($connection['ok']);
            $status['connection_test']['state'] = $status['connection_test']['ok'] ? 'passed' : 'failed';
            $status['connection_test']['value'] = !empty($connection['ok'])
                ? esc_html__('Passed', 'fastpixel-website-accelerator')
                : (!empty($connection['message']) ? (string) $connection['message'] : esc_html__('Failed', 'fastpixel-website-accelerator'));

            return $status;
        }

        protected function test_redis_connection(array $settings) {
            if (!class_exists('Redis')) {
                return [
                    'ok' => false,
                    'message' => esc_html__('Failed (Redis extension disabled)', 'fastpixel-website-accelerator'),
                ];
            }

            $host = $this->normalize_string($settings['host'] ?? '127.0.0.1');
            if ($host === '') {
                return [
                    'ok' => false,
                    'message' => esc_html__('Failed (Redis host is empty)', 'fastpixel-website-accelerator'),
                ];
            }

            $is_socket = $this->is_unix_socket($host);
            if ($is_socket && strpos($host, 'unix://') === 0) {
                $host = substr($host, 7);
            }
            $port = $is_socket ? 0 : $this->normalize_int($settings['port'] ?? 6379, 6379);
            $persistent = !empty($settings['persistent']);
            $username = $this->normalize_string($settings['username'] ?? '');
            $password = $this->normalize_string($settings['password'] ?? '');
            $dbid = $this->normalize_int($settings['dbid'] ?? 0, 0);
            if ($dbid < 0) {
                $dbid = 0;
            }

            $client = null;
            try {
                $client = new \Redis();
                $timeout = 1;
                $connected = $persistent
                    ? @$client->pconnect($host, $port, $timeout, 'fastpixel_oc_status_' . md5($host . '|' . $port . '|' . $dbid))
                    : @$client->connect($host, $port, $timeout);
                if (!$connected) {
                    return [
                        'ok' => false,
                        'message' => esc_html__('Failed to connect to the Redis server.', 'fastpixel-website-accelerator'),
                    ];
                }

                if ($password !== '') {
                    $auth_ok = ($username !== '')
                        ? @$client->auth([$username, $password])
                        : @$client->auth($password);
                    if (!$auth_ok) {
                        return [
                            'ok' => false,
                            'message' => esc_html__('Failed (Redis authentication failed)', 'fastpixel-website-accelerator'),
                        ];
                    }
                }

                if ($dbid >= 0 && !@$client->select($dbid)) {
                    return [
                        'ok' => false,
                        'message' => esc_html__('Failed (Redis DB selection failed)', 'fastpixel-website-accelerator'),
                    ];
                }

                $pong = @$client->ping();
                if ($pong === false) {
                    return [
                        'ok' => false,
                        'message' => esc_html__('Failed (Redis ping failed)', 'fastpixel-website-accelerator'),
                    ];
                }

                return [
                    'ok' => true,
                    'message' => esc_html__('Passed', 'fastpixel-website-accelerator'),
                ];
            } catch (\Throwable $e) {
                $exception_message = (string) $e->getMessage();
                if (
                    $exception_message !== '' &&
                    (stripos($exception_message, 'protocol error') !== false || stripos($exception_message, 'reply type byte') !== false)
                ) {
                    return [
                        'ok' => false,
                        'message' => esc_html__('Failed to connect to the Redis server.', 'fastpixel-website-accelerator'),
                    ];
                }

                return [
                    'ok' => false,
                    'message' => sprintf(esc_html__('Failed (%s)', 'fastpixel-website-accelerator'), $exception_message),
                ];
            } finally {
                if (is_object($client) && method_exists($client, 'close')) {
                    try {
                        @$client->close();
                    } catch (\Throwable $e) {
                        // ignore close errors
                    }
                }
            }
        }

        protected function test_memcached_connection(array $settings) {
            if (!class_exists('Memcached')) {
                return [
                    'ok' => false,
                    'message' => esc_html__('Failed (Memcached extension disabled)', 'fastpixel-website-accelerator'),
                ];
            }

            $host = $this->normalize_string($settings['host'] ?? '127.0.0.1');
            if ($host === '') {
                return [
                    'ok' => false,
                    'message' => esc_html__('Failed (Memcached host is empty)', 'fastpixel-website-accelerator'),
                ];
            }

            $is_socket = $this->is_unix_socket($host);
            if ($is_socket && strpos($host, 'unix://') === 0) {
                $host = substr($host, 7);
            }
            $port = $is_socket ? 0 : $this->normalize_int($settings['port'] ?? 11211, 11211);
            $username = $this->normalize_string($settings['username'] ?? '');
            $password = $this->normalize_string($settings['password'] ?? '');

            $client = null;
            try {
                $client = new \Memcached();
                if (defined('Memcached::OPT_CONNECT_TIMEOUT')) {
                    $client->setOption(\Memcached::OPT_CONNECT_TIMEOUT, 800);
                }
                if (defined('Memcached::OPT_RETRY_TIMEOUT')) {
                    $client->setOption(\Memcached::OPT_RETRY_TIMEOUT, 1);
                }

                if (($username !== '' || $password !== '')) {
                    if (method_exists($client, 'setSaslAuthData') && defined('Memcached::OPT_BINARY_PROTOCOL')) {
                        $client->setOption(\Memcached::OPT_BINARY_PROTOCOL, true);
                        $client->setSaslAuthData($username, $password);
                    } else {
                        return [
                            'ok' => false,
                            'message' => esc_html__('Failed (Memcached SASL not supported)', 'fastpixel-website-accelerator'),
                        ];
                    }
                }

                if (!$client->addServer($host, $port)) {
                    return [
                        'ok' => false,
                        'message' => esc_html__('Failed (Memcached addServer failed)', 'fastpixel-website-accelerator'),
                    ];
                }

                $versions = $client->getVersion();
                if (empty($versions) || !is_array($versions)) {
                    return [
                        'ok' => false,
                        'message' => esc_html__('Failed to connect to the Memcached server.', 'fastpixel-website-accelerator'),
                    ];
                }

                $version_ok = false;
                foreach ($versions as $ver) {
                    if (is_string($ver) && $ver !== '' && $ver !== '255.255.255') {
                        $version_ok = true;
                        break;
                    }
                }
                if (!$version_ok) {
                    return [
                        'ok' => false,
                        'message' => esc_html__('Failed (Memcached server not responding)', 'fastpixel-website-accelerator'),
                    ];
                }

                return [
                    'ok' => true,
                    'message' => esc_html__('Passed', 'fastpixel-website-accelerator'),
                ];
            } catch (\Throwable $e) {
                return [
                    'ok' => false,
                    'message' => sprintf(esc_html__('Failed (%s)', 'fastpixel-website-accelerator'), $e->getMessage()),
                ];
            } finally {
                if (is_object($client) && method_exists($client, 'quit')) {
                    try {
                        @$client->quit();
                    } catch (\Throwable $e) {
                        // ignore quit errors
                    }
                }
            }
        }

        protected function initialize_object_cache_defaults() {
            $defaults_initialized = (int) $this->functions->get_option('fastpixel_object_cache_defaults_initialized', 0);
            if ($defaults_initialized === 1) {
                return;
            }

            $global_groups = $this->functions->get_option('fastpixel_object_cache_global_groups', false);
            if ($this->is_empty_object_cache_group_option($global_groups)) {
                $this->functions->update_option('fastpixel_object_cache_global_groups', $this->get_default_global_groups());
            }

            $do_not_cache = $this->functions->get_option('fastpixel_object_cache_do_not_cache', false);
            if ($this->is_empty_object_cache_group_option($do_not_cache)) {
                $this->functions->update_option('fastpixel_object_cache_do_not_cache', $this->get_default_do_not_cache_groups());
            }

            $ttl = $this->functions->get_option('fastpixel_object_cache_default_lifetime', false);
            if ($ttl === false || $ttl === null || $ttl === '' || !is_numeric($ttl)) {
                $this->functions->update_option('fastpixel_object_cache_default_lifetime', 360);
            }

            $this->functions->update_option('fastpixel_object_cache_defaults_initialized', 1);
        }

        protected function is_empty_object_cache_group_option($value) {
            if ($value === false || $value === null) {
                return true;
            }
            if (is_string($value)) {
                return trim($value) === '';
            }
            if (!is_array($value)) {
                return true;
            }
            foreach ($value as $item) {
                if (trim((string) $item) !== '') {
                    return false;
                }
            }
            return true;
        }

        protected function get_default_global_groups() {
            return [
                'users',
                'userlogins',
                'useremail',
                'userslugs',
                'usermeta',
                'user_meta',
                'site-transient',
                'site-options',
                'site-lookup',
                'site-details',
                'blog-lookup',
                'blog-details',
                'blog-id-cache',
                'rss',
                'global-posts',
                'global-cache-test',
            ];
        }

        protected function get_default_do_not_cache_groups() {
            return [
                'comment',
                'counts',
                'plugins',
                'wc_session_id',
            ];
        }

        protected function normalize_object_cache_method($value) {
            $method = strtolower($this->normalize_string($value));
            if ($method === 'memcache') {
                $method = 'memcached';
            }
            if (in_array($method, ['disabled', 'none', 'off', '0', 'false'], true)) {
                $method = 'disabled';
            }
            if (!in_array($method, ['disabled', 'redis', 'memcached'], true)) {
                $method = 'disabled';
            }
            return $method;
        }

        protected function normalize_string($value) {
            if (!is_scalar($value)) {
                return '';
            }
            return trim((string) $value);
        }

        protected function normalize_int($value, $default = 0) {
            if ($value === '' || $value === null) {
                return (int) $default;
            }
            if (is_numeric($value)) {
                return (int) $value;
            }
            return (int) $default;
        }

        protected function render_object_cache_docs_tooltip_link($url) {
            $docs_label = esc_attr__('Open Object Cache documentation', 'fastpixel-website-accelerator');
            ?>
            <a
                class="fastpixel-object-cache-docs-link"
                href="<?php echo esc_url($url); ?>"
                target="_blank"
                rel="noopener noreferrer"
                data-tooltip="<?php echo $docs_label; ?>"
                aria-label="<?php echo $docs_label; ?>"
            >
                <img src="<?php echo esc_url(FASTPIXEL_PLUGIN_URL . 'icons/info.svg'); ?>" alt="" aria-hidden="true" />
            </a>
            <?php
        }

        protected function is_unix_socket($host) {
            return is_string($host) && ($host !== '') && (strpos($host, '/') === 0 || strpos($host, 'unix://') === 0);
        }

        public function get_persisted_connected_object_cache_method() {
            $persisted_configured_method = $this->normalize_object_cache_method($this->functions->get_option('fastpixel_object_cache_method', 'disabled'));
            $persisted_object_cache_enabled = (int) $this->functions->get_option('fastpixel_object_cache_enabled', 0) === 1;

            if (
                !$persisted_object_cache_enabled ||
                ($persisted_configured_method === 'redis' && !class_exists('Redis')) ||
                ($persisted_configured_method === 'memcached' && !class_exists('Memcached'))
            ) {
                $persisted_configured_method = 'disabled';
            }

            if (!in_array($persisted_configured_method, ['redis', 'memcached'], true)) {
                return '';
            }

            $persisted_connection_verified = (int) $this->functions->get_option('fastpixel_object_cache_connection_verified', 0) === 1;
            $persisted_verified_method = $this->normalize_object_cache_method($this->functions->get_option('fastpixel_object_cache_connection_verified_method', 'disabled'));

            if (
                $persisted_connection_verified &&
                $persisted_verified_method === $persisted_configured_method &&
                in_array($persisted_verified_method, ['redis', 'memcached'], true)
            ) {
                return $persisted_verified_method;
            }

            return '';
        }

        public function print_object_cache_toggle_script() {
            if (empty($_GET['page']) || strpos((string)$_GET['page'], FASTPIXEL_TEXTDOMAIN) === false) {
                return;
            }
            $ajax_url = admin_url('admin-ajax.php');
            $test_nonce = wp_create_nonce('fastpixel-object-cache-test');
            $testing_text = esc_html__('Testing...', 'fastpixel-website-accelerator');
            $test_button_text = esc_html__('Test Connection', 'fastpixel-website-accelerator');
            $select_method_text = esc_html__('Please select Redis or Memcached before testing.', 'fastpixel-website-accelerator');
            $unexpected_response_text = esc_html__('Unexpected server response.', 'fastpixel-website-accelerator');
            $request_failed_text = esc_html__('Could not run the connection test.', 'fastpixel-website-accelerator');
            $handler_unavailable_text = esc_html__('AJAX handler is unavailable (response: 0).', 'fastpixel-website-accelerator');
            $method_active_text = esc_html__('Active', 'fastpixel-website-accelerator');
            $persisted_configured_method = $this->normalize_object_cache_method($this->functions->get_option('fastpixel_object_cache_method', 'disabled'));
            $persisted_object_cache_enabled = (int) $this->functions->get_option('fastpixel_object_cache_enabled', 0) === 1;
            if (
                !$persisted_object_cache_enabled ||
                ($persisted_configured_method === 'redis' && !class_exists('Redis')) ||
                ($persisted_configured_method === 'memcached' && !class_exists('Memcached'))
            ) {
                $persisted_configured_method = 'disabled';
            }
            if (!in_array($persisted_configured_method, ['redis', 'memcached'], true)) {
                $persisted_configured_method = '';
            }

            $persisted_connected_method = $this->get_persisted_connected_object_cache_method();
            ?>
            <script>
            (function(){
                document.addEventListener('DOMContentLoaded', function(){
                    var settings = document.getElementById('fastpixel_object_cache_settings');
                    var methodInputs = document.querySelectorAll('input[name="fastpixel_object_cache_method"]');
                    var testButton = document.getElementById('fastpixel_object_cache_test_connection');
                    var testResult = document.getElementById('fastpixel_object_cache_test_result');
                    var connectionTitle = document.getElementById('fastpixel_object_cache_connection_title');
                    var connectionTitleText = connectionTitle ? connectionTitle.querySelector('.fastpixel-object-cache-connection-title-text') : null;
                    var connectionHint = document.getElementById('fastpixel_object_cache_connection_hint');
                    var purgeButton = document.getElementById('fastpixel_object_cache_purge_button');
                    var ajaxUrl = <?php echo wp_json_encode($ajax_url); ?>;
                    var nonce = <?php echo wp_json_encode($test_nonce); ?>;
                    var testingLabel = <?php echo wp_json_encode($testing_text); ?>;
                    var defaultTestLabel = <?php echo wp_json_encode($test_button_text); ?>;
                    var selectMethodLabel = <?php echo wp_json_encode($select_method_text); ?>;
                    var unexpectedResponseLabel = <?php echo wp_json_encode($unexpected_response_text); ?>;
                    var requestFailedLabel = <?php echo wp_json_encode($request_failed_text); ?>;
                    var handlerUnavailableLabel = <?php echo wp_json_encode($handler_unavailable_text); ?>;
                    var methodActiveLabel = <?php echo wp_json_encode($method_active_text); ?>;
                    var persistedConfiguredMethod = <?php echo wp_json_encode($persisted_configured_method); ?>;
                    var persistedConnectedMethod = <?php echo wp_json_encode($persisted_connected_method); ?>;

                    if (!settings) {
                        return;
                    }

                    function getSelectedMethod() {
                        if (!methodInputs || !methodInputs.length) {
                            return 'disabled';
                        }
                        for (var i = 0; i < methodInputs.length; i++) {
                            if (methodInputs[i].checked) {
                                return methodInputs[i].value;
                            }
                        }
                        return 'disabled';
                    }

                    function getConnectionMethodLabel(method) {
                        if (method === 'redis') {
                            return 'Redis';
                        }
                        if (method === 'memcached') {
                            return 'Memcached';
                        }
                        return 'Redis/Memcached';
                    }

                    function formatMethodTemplate(template, methodLabel) {
                        if (typeof template !== 'string' || template === '') {
                            return methodLabel;
                        }
                        return template.replace('%s', methodLabel);
                    }

                    function updateConnectionCopy(isConnected) {
                        var methodLabel = getConnectionMethodLabel(getSelectedMethod());
                        if (connectionTitle && connectionTitleText) {
                            var defaultTemplate = connectionTitle.getAttribute('data-default-template') || '%s Connection Details';
                            var connectedTemplate = connectionTitle.getAttribute('data-connected-template') || '%s Connected';
                            connectionTitleText.textContent = formatMethodTemplate(
                                isConnected ? connectedTemplate : defaultTemplate,
                                methodLabel
                            );
                        }
                        if (connectionHint) {
                            var hintTemplate = connectionHint.getAttribute('data-template') || 'Authentication credentials for %s may be necessary based on your hosting setup.';
                            connectionHint.textContent = formatMethodTemplate(hintTemplate, methodLabel);
                        }
                    }

                    function getMethodLabelElement(method) {
                        if (method !== 'redis' && method !== 'memcached') {
                            return null;
                        }
                        return document.querySelector('input[name="fastpixel_object_cache_method"][value="' + method + '"] + span');
                    }

                    function resetMethodLabel(span) {
                        if (!span) {
                            return;
                        }
                        var baseLabel = span.getAttribute('data-base-label');
                        if (typeof baseLabel === 'string' && baseLabel !== '') {
                            span.textContent = baseLabel;
                        }
                    }

                    function clearActiveMethodBadge() {
                        resetMethodLabel(getMethodLabelElement('redis'));
                        resetMethodLabel(getMethodLabelElement('memcached'));
                    }

                    function setActiveMethodBadge(method) {
                        clearActiveMethodBadge();
                        var methodLabelElement = getMethodLabelElement(method);
                        if (!methodLabelElement) {
                            return;
                        }
                        var baseLabel = methodLabelElement.getAttribute('data-base-label') || methodLabelElement.textContent || '';
                        var activeLabel = compactText(methodActiveLabel);
                        methodLabelElement.textContent = activeLabel === '' ? baseLabel : (baseLabel + ' (' + activeLabel + ')');
                    }

                    function syncMethodBadgeWithSelection() {
                        clearActiveMethodBadge();
                        if (persistedConnectedMethod !== 'redis' && persistedConnectedMethod !== 'memcached') {
                            return;
                        }
                        setActiveMethodBadge(persistedConnectedMethod);
                    }

                    function setConnectionState(isConnected) {
                        if (connectionTitle) {
                            connectionTitle.classList.toggle('is-connected', !!isConnected);
                        }
                        if (purgeButton) {
                            purgeButton.hidden = !isConnected;
                        }
                        updateConnectionCopy(!!isConnected);
                    }

                    function setResult(state, message) {
                        if (!testResult) {
                            return;
                        }
                        testResult.classList.remove('is-success', 'is-error');
                        testResult.textContent = message || '';
                        if (!message) {
                            return;
                        }
                        if (state === 'success') {
                            testResult.classList.add('is-success');
                        }
                        if (state === 'error') {
                            testResult.classList.add('is-error');
                        }
                    }

                    function getFieldValue(fieldId) {
                        var field = document.getElementById(fieldId);
                        return field ? field.value : '';
                    }

                    function compactText(value) {
                        return String(value || '').replace(/\s+/g, ' ').trim();
                    }

                    function extractResponseMessage(responseData, fallbackMessage) {
                        if (!responseData || typeof responseData !== 'object') {
                            return fallbackMessage;
                        }
                        if (typeof responseData.message === 'string' && responseData.message !== '') {
                            return responseData.message;
                        }
                        if (typeof responseData.data === 'string' && responseData.data !== '') {
                            return responseData.data;
                        }
                        if (responseData.data && typeof responseData.data.message === 'string' && responseData.data.message !== '') {
                            return responseData.data.message;
                        }
                        if (responseData.data && Array.isArray(responseData.data) && responseData.data.length > 0 && typeof responseData.data[0] === 'string') {
                            return responseData.data[0];
                        }
                        return fallbackMessage;
                    }

                    function showNonJsonError(responseText, fallbackMessage) {
                        var compact = compactText(responseText);
                        if (compact === '0') {
                            setResult('error', handlerUnavailableLabel);
                            return;
                        }
                        if (compact !== '') {
                            setResult('error', compact.substring(0, 180));
                            return;
                        }
                        setResult('error', fallbackMessage);
                    }

                    function handleConnectionResponse(responseData) {
                        if (responseData && responseData.success) {
                            setConnectionState(true);
                            setResult('success', extractResponseMessage(responseData, ''));
                            return;
                        }
                        setConnectionState(false);
                        setResult('error', extractResponseMessage(responseData, unexpectedResponseLabel));
                    }

                    function updateVisibility() {
                        var method = getSelectedMethod();
                        var showAdvanced = method !== 'disabled';
                        settings.style.display = showAdvanced ? '' : 'none';
                        syncMethodBadgeWithSelection();
                        if (!showAdvanced) {
                            setConnectionState(false);
                            setResult('', '');
                            return;
                        }

                        var shouldShowConnectedTitle =
                            (method === 'redis' || method === 'memcached') &&
                            method === persistedConnectedMethod;
                        setConnectionState(shouldShowConnectedTitle);
                        if (!shouldShowConnectedTitle) {
                            setResult('', '');
                        }
                    }

                    function setButtonLoading(isLoading) {
                        if (!testButton) {
                            return;
                        }
                        testButton.disabled = !!isLoading;
                        testButton.classList.toggle('is-loading', !!isLoading);
                        testButton.textContent = isLoading ? testingLabel : defaultTestLabel;
                    }

                    updateVisibility();
                    setResult('', '');
                    setButtonLoading(false);

                    if (methodInputs && methodInputs.length) {
                        for (var j = 0; j < methodInputs.length; j++) {
                            methodInputs[j].addEventListener('change', function() {
                                setConnectionState(false);
                                setResult('', '');
                                setButtonLoading(false);
                                updateVisibility();
                            });
                        }
                    }

                    var connectionFieldIds = [
                        'fastpixel_object_cache_host',
                        'fastpixel_object_cache_port',
                        'fastpixel_object_cache_username',
                        'fastpixel_object_cache_password',
                        'fastpixel_object_cache_dbid'
                    ];
                    for (var k = 0; k < connectionFieldIds.length; k++) {
                        var connectionField = document.getElementById(connectionFieldIds[k]);
                        if (!connectionField) {
                            continue;
                        }
                        connectionField.addEventListener('input', function() {
                            setConnectionState(false);
                            setResult('', '');
                            syncMethodBadgeWithSelection();
                        });
                        connectionField.addEventListener('change', function() {
                            setConnectionState(false);
                            setResult('', '');
                            syncMethodBadgeWithSelection();
                        });
                    }
                    var persistentField = document.querySelector('input[name="fastpixel_object_cache_persistent"]');
                    if (persistentField) {
                        persistentField.addEventListener('change', function() {
                            setConnectionState(false);
                            setResult('', '');
                            syncMethodBadgeWithSelection();
                        });
                    }

                    if (testButton) {
                        testButton.addEventListener('click', function() {
                            var method = getSelectedMethod();
                            if (method !== 'redis' && method !== 'memcached') {
                                setConnectionState(false);
                                setResult('error', selectMethodLabel);
                                return;
                            }

                            var persistentInput = document.querySelector('input[name="fastpixel_object_cache_persistent"]');
                            var requestData = {
                                action: 'fastpixel_object_cache_test_connection',
                                nonce: nonce,
                                method: method,
                                host: getFieldValue('fastpixel_object_cache_host'),
                                port: getFieldValue('fastpixel_object_cache_port'),
                                username: getFieldValue('fastpixel_object_cache_username'),
                                password: getFieldValue('fastpixel_object_cache_password'),
                                dbid: getFieldValue('fastpixel_object_cache_dbid'),
                                persistent: persistentInput && persistentInput.checked ? '1' : '0'
                            };

                            setConnectionState(false);
                            syncMethodBadgeWithSelection();
                            setResult('', '');
                            setButtonLoading(true);

                            function runFetchRequest() {
                                var payload = new URLSearchParams(requestData);
                                fetch(ajaxUrl, {
                                    method: 'POST',
                                    credentials: 'same-origin',
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                                    },
                                    body: payload.toString()
                                })
                                    .then(function(response) {
                                        return response.text();
                                    })
                                    .then(function(responseText) {
                                        var responseData = null;
                                        try {
                                            responseData = JSON.parse(responseText);
                                        } catch (e) {
                                            responseData = null;
                                        }
                                        if (!responseData || typeof responseData !== 'object') {
                                            setConnectionState(false);
                                            showNonJsonError(responseText, requestFailedLabel);
                                            return;
                                        }
                                        handleConnectionResponse(responseData);
                                    })
                                    .catch(function() {
                                        setConnectionState(false);
                                        setResult('error', requestFailedLabel);
                                    })
                                    .then(function() {
                                        setButtonLoading(false);
                                    });
                            }

                            if (window.jQuery && window.jQuery.ajax) {
                                window.jQuery.ajax({
                                    url: ajaxUrl,
                                    type: 'POST',
                                    dataType: 'json',
                                    data: requestData
                                })
                                    .done(function(responseData) {
                                        handleConnectionResponse(responseData);
                                    })
                                    .fail(function(jqXHR) {
                                        var responseText = jqXHR && typeof jqXHR.responseText === 'string' ? jqXHR.responseText : '';
                                        var responseData = null;
                                        try {
                                            responseData = responseText ? JSON.parse(responseText) : null;
                                        } catch (e) {
                                            responseData = null;
                                        }
                                        setConnectionState(false);
                                        if (responseData && typeof responseData === 'object') {
                                            setResult('error', extractResponseMessage(responseData, requestFailedLabel));
                                        } else {
                                            showNonJsonError(responseText, requestFailedLabel);
                                        }
                                    })
                                    .always(function() {
                                        setButtonLoading(false);
                                    });
                                return;
                            }

                            runFetchRequest();
                        });
                    }
                });
            })();
            </script>
            <?php
        }
    }
    new FASTPIXEL_Tab_Object_Cache();
}
