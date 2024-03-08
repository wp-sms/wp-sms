<span class="wpsms-indicator__status <?php echo esc_attr($type); ?>">
    <svg viewBox="0 0 6 6" xmlns="http://www.w3.org/2000/svg">
        <circle cx="3" cy="2" r="1" stroke-width="2"></circle>
    </svg>
    <span><?php echo wp_kses_post($label); ?></span>
</span>