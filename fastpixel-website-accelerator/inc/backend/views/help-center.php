<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit; ?>

<div class="help-center-wrap">
    <?php
    $help_rows = [
        'top'    => [],
        'bottom' => [],
    ];
    foreach ($this->get_links() as $h_link) {
        $row = !empty($h_link['row']) ? $h_link['row'] : 'bottom';
        if (!isset($help_rows[$row])) {
            $help_rows[$row] = [];
        }
        $help_rows[$row][] = $h_link;
    }
    ?>
    <div class="help-center-stack">
        <?php foreach ($help_rows as $row => $help_links) : ?>
            <?php if (empty($help_links)) : ?>
                <?php continue; ?>
            <?php endif; ?>
            <div class="help-center help-center-row-<?php echo esc_attr($row); ?>">
                <?php foreach ($help_links as $h_link) : ?>
                    <?php
                    $is_chat_trigger = !empty($h_link['action']) && 'open_chat' === $h_link['action'];
                    $button_classes  = 'button-setting' . ($is_chat_trigger ? ' fastpixel-chat-trigger' : '');
                    $button_title    = !empty($h_link['button_title']) ? $h_link['button_title'] : $h_link['title'];
                    ?>
                    <div class="help-center-card">
                        <span class="main-icon"><img src="<?php echo esc_url($h_link['icon']); ?>" class="icon"></span>
                        <h4><?php echo esc_html($h_link['title']); ?></h4>
                        <p><?php echo esc_html($h_link['description']); ?></p>
                        <div class="fastpixel-button-container">
                            <?php if ($is_chat_trigger) : ?>
                                <button type="button" class="<?php echo esc_attr($button_classes); ?>" data-fastpixel-chat-trigger="1"><?php echo esc_html($button_title); ?></button>
                            <?php else : ?>
                                <a href="<?php echo esc_url($h_link['link']); ?>" target="_blank" class="<?php echo esc_attr($button_classes); ?>"><?php echo esc_html($button_title); ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
