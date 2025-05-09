<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit; ?>

<div class="help-center">
    <?php $help_links = $this->get_links(); ?>
    <?php foreach ($help_links as $h_link) : ?>
    <div>
        <span class="main-icon"><img src="<?php echo esc_html($h_link['icon']); ?>" class="icon"></span>
        <h4><?php echo esc_html($h_link['title']); ?></h4>
        <p><?php echo esc_html($h_link['description']); ?></p>
        <div class="fastpixel-button-container"><a href="<?php echo esc_attr($h_link['link']); ?>" target="_blank" class="button-setting"><?php echo esc_html(!empty($h_link['button_title']) ? $h_link['button_title'] : $h_link['title']); ?></a></div>
    </div>
    <?php endforeach; ?>
</div>