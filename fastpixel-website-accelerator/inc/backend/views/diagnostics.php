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
            if ($test_result['array_result'] && is_array($test_result['display'])) : 
                //need to get column names
                $column_names = $test_result['display']['column_names'];
                $tests = $test_result['display']['rows'];
                $description = $test_result['display']['description'];
                ?>
                <tr><td>
                    <?php echo wp_kses($test_result['name'], $allowed_tags); ?>
                </td><td>
                    <?php if (empty($tests)) : ?> 
                    <strong class="passed"><?php esc_html_e('None', 'fastpixel-website-accelerator'); ?></strong>
                    <?php else: 
                        echo '&nbsp;'; 
                    endif; ?>
                </td></tr>
                <?php if (!empty($tests) && is_array($tests)) : ?>
                    <tr>
                        <td colspan="2">
                            <p>
                            <?php echo !empty($description) ? wp_kses($description, ['p' => [], 'br' => []]) : ''; ?>
                            </p>
                            <table class="wp-list-table widefat fixed striped table-view-list">
                                <thead>
                                    <tr>
                                        <th><?php echo esc_attr($column_names[0]); ?></th>
                                        <th><?php echo esc_attr($column_names[1]); ?></th>
                                    </tr>
                                </thead>
                                <?php foreach ($tests as $key => $pass) : ?>
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
