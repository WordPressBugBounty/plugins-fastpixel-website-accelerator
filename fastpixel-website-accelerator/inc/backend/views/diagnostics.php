<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;
$allowed_tags = [
    'a' => [
        'href' => [], 
        'class' => [], 
        'data-plugin-file' => [], 
        'data-plugin-id' => []
    ],
    'b' => [], 
    'br' => [], 
    'strong' => [
        'class' => []
    ], 
    'span' => [
        'class' => []
    ], 
    'div' => [
        'id' => []
    ]
];
$tests_results = $this->diag->get_tests_results();
if (!empty($tests_results) && is_array($tests_results)) : ?>
<div class="fastpixel-diagnostics-container">
    <table class="fastpixel-table wp-list-table widefat fixed striped table-view-list">
        <thead>
            <tr>
                <th><?php esc_html_e('Verified', 'fastpixel-website-accelerator'); ?></th>
                <th><?php esc_html_e('Status', 'fastpixel-website-accelerator'); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($tests_results as $test_result) :
            if ($test_result['array_result'] && is_array($test_result['display'])) : ?>
                <tr><td>
                    <?php echo wp_kses($test_result['name'], $allowed_tags); ?>
                </td><td>
                    <?php if (empty($test_result['display'])) : ?> 
                    <strong class="passed"><?php esc_html_e('None', 'fastpixel-website-accelerator'); ?></strong>
                    <?php else: 
                        echo '&nbsp;'; 
                    endif; ?>
                </td></tr>
                <?php if (!empty($test_result['display']) && is_array($test_result['display'])) : ?>
                    <tr>
                        <td colspan="2">
                            <p>
                            <?php /* translators: %s is for new line */
                            printf(esc_html__('FastPixel Website Accelerator might not work properly if certain plugins are active. %s Below is a list of conflicting plugins - please disable them to ensure maximum performance.', 'fastpixel-website-accelerator'), '<br/>'); ?>
                            </p>
                            <table class="wp-list-table widefat fixed striped table-view-list">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Plugin:', 'fastpixel-website-accelerator'); ?></th>
                                        <th><?php esc_html_e('Status', 'fastpixel-website-accelerator'); ?></th>
                                    </tr>
                                </thead>
                                <?php foreach ($test_result['display'] as $key => $pass) : ?>
                                    <tr class="plugin-status-row <?php echo esc_attr($pass['status'] == true ? 'passed' : 'failed'); ?>">
                                        <td><?php echo wp_kses($key, $allowed_tags); ?></td>
                                        <td><?php echo wp_kses($pass['display_status'], $allowed_tags); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        </td>
                    </tr>
                <?php endif;
            else : ?>
                <tr class="<?php echo esc_attr($test_result['status'] == true ? 'passed' : 'failed'); ?>">
                    <td><?php echo wp_kses($test_result['name'], $allowed_tags); ?></td>
                    <td>
                    <?php if (is_bool($test_result['display'])) : ?>
                        <strong class="<?php echo esc_attr($test_result['status'] == true ? 'passed' : 'failed'); ?>"><?php echo $test_result['status'] == true ? esc_html__('OK', 'fastpixel-website-accelerator') : esc_html__('FAILED', 'fastpixel-website-accelerator'); ?></strong>
                    <?php else :
                        echo wp_kses($test_result['display'], $allowed_tags);
                    endif; ?>
                    </td>
                </tr>
            <?php endif;
        endforeach; ?>
        </tbody>
        <tfoot></tfoot>
    </table>
</div>
<?php endif;
