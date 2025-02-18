<div id="main" class="wpsms-onboarding" role="content">

    <section class="c-section--logo u-text-center">
        <img alt="logo" src="<?php echo WP_SMS_URL . 'assets/images/logo.svg'; ?>"/>
    </section>
    <header class="o-section c-section--header">
        <div class="o-section__wrapper">
            <div class="c-header">
                <nav class="c-header_navigation">
                    <ul class="s-nav s-nav--steps">
                        <?php

                        foreach ($steps as $key => $step) {
                            $isActive = ($key < $index) ? 'is-active' : '';
                            echo '<li class="' . $isActive . '"><span></span><a href="' . $step['url'] . '" title="' . $step['title'] . '">' . $step['title'] . '</a></li>';
                        }
                        ?>
                    </ul>
                </nav>
            </div>
        </div>
    </header>
    <section class="o-section c-section--maincontent">
        <div class="o-section__wrapper o-section__wrapper--maincontent">
