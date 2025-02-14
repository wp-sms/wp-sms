<div id="main" class="wpsms-onboarding" role="content">

    <section class="c-section--logo u-text-center">
        <img alt="logo" src="<?= WP_SMS_URL . 'assets/images/logo.svg' ?>"/>
    </section>
    <header class="o-section c-section--header">
        <div class="o-section__wrapper">
            <div class="c-header">
                <nav class="c-header_navigation">
                    <ul class="s-nav s-nav--steps">
                        <li class="<?= $current == 'getting-started' ? 'is-active' : '' ?>"><a href="/" title="Getting Started">Getting Started</a></li>
                        <li class="<?= $current == 'sms-gateway' ? 'is-active' : '' ?>"><a href="/step2-sms-gateway.html" title="SMS gateway">SMS gateway</a></li>
                        <li class="<?= $current == 'configuration' ? 'is-active' : '' ?>"><a href="/step3-configuration-result.html" title="Configuration">Configuration</a></li>
                        <li class="<?= $current == 'test-setup' ? 'is-active' : '' ?>"><a href="/step4-industry.html" title="Test Your Setup">Test Your Setup</a></li>
                        <li class="<?= $current == 'update-all-in-one' ? 'is-active' : '' ?>"><a href="/step5-wp-sms-pro.html" title="WP-SMS Pro">WP-SMS Pro</a></li>
                        <li class="<?= $current == 'addons' ? 'is-active' : '' ?>"><a href="/step6-addons.html" title="Add-ons">Add-ons</a></li>
                        <li class="<?= $current == 'ready' ? 'is-active' : '' ?>"><a href="/step7-ready.html" title="Ready">Ready</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>
    <section class="o-section c-section--maincontent">
        <div class="o-section__wrapper o-section__wrapper--maincontent">