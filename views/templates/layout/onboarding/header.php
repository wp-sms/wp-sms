<div id="main" class="wpsms-onboarding" role="content">

    <section class="c-section--logo u-text-center">
        <img alt="logo" src="<?php echo esc_url(WP_SMS_URL . 'assets/images/logo.svg'); ?>"/>
    </section>

    <header class="o-section c-section--header">
        <div class="o-section__wrapper">
            <div class="c-header">
                <nav class="c-header_navigation">
                    <ul class="s-nav s-nav--steps">
                        <?php
                        if (!empty($steps) && is_array($steps)) {
                            foreach ($steps as $key => $step) {
                                $isActive = ($key < $index) ? 'is-active' : '';
                                $url = isset($step['url']) ? esc_url($step['url']) : '#';
                                $title = isset($step['title']) ? esc_html($step['title']) : '';

                                echo '<li class="' . esc_attr($isActive) . '">';
                                echo '<span></span><a href="' . $url . '" title="' . $title . '">' . $title . '</a>';
                                echo '</li>';
                            }
                        }
                        ?>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <section class="o-section c-section--maincontent">
        <div class="o-section__wrapper o-section__wrapper--maincontent">
