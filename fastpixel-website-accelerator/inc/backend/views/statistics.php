<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

$stats_instance = FASTPIXEL_Stats::get_instance();
$stats = $stats_instance->get_stats();
?>

    <div class="wrap fastpixel-stats-wrap fastpixel-website-accelerator-statistics">
        
        <div class="fastpixel-stats-overview">
            <div class="fastpixel-stats-card">
                <h2><?php echo esc_html__('Cache Hit Ratio', 'fastpixel-website-accelerator'); ?></h2>
                <div class="fastpixel-stats-value"><?php echo esc_html($stats['hit_ratio']); ?>%</div>
            </div>
            
            <div class="fastpixel-stats-card">
                <h2><?php echo esc_html__('Cache Hits', 'fastpixel-website-accelerator'); ?></h2>
                <div class="fastpixel-stats-value"><?php echo esc_html(number_format($stats['hits'])); ?></div>
            </div>
                        
            <div class="fastpixel-stats-card">
                <h2><?php echo esc_html__('Cache Misses', 'fastpixel-website-accelerator'); ?></h2>
                <div class="fastpixel-stats-value"><?php echo esc_html(number_format($stats['misses'])); ?></div>
            </div>
            
            <div class="fastpixel-stats-card">
                <h2><?php echo esc_html__('Total Requests', 'fastpixel-website-accelerator'); ?></h2>
                <div class="fastpixel-stats-value"><?php echo esc_html(number_format($stats['total'])); ?></div>
            </div>
        </div>
        
        <div class="fastpixel-stats-charts">
            <div class="fastpixel-stats-chart-container">
                <h2><?php echo esc_html__('Daily Statistics', 'fastpixel-website-accelerator'); ?></h2>
                <canvas id="fastpixel-daily-chart" height="100"></canvas>
            </div>
            
            <div class="fastpixel-stats-chart-container">
                <h2><?php echo esc_html__('Hourly Statistics', 'fastpixel-website-accelerator'); ?></h2>
                <canvas id="fastpixel-hourly-chart" height="100"></canvas>
            </div>
        </div>
        
        <div class="fastpixel-stats-actions">
            <button id="fastpixel-reset-stats" class="button button-secondary">
                <?php echo esc_html__('Reset Statistics', 'fastpixel-website-accelerator'); ?>
            </button>
        </div>
    </div>
<?php 
