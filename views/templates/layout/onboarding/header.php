<!DOCTYPE html>
<html lang="EN">
<head>
    <meta charset="utf-8"/>
    <meta content="IE=edge" http-equiv="X-UA-Compatible"/>
    <meta content="" name="description"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Step 7</title>

    <!-- Disable tap highlight on IE -->
    <meta content="no" name="msapplication-tap-highlight"/>

    <!-- Add to homescreen for Chrome on Android -->
    <meta content="yes" name="mobile-web-app-capable"/>
    <meta content="Veronalabs" name="application-name"/>

    <!-- Add to homescreen for Safari on iOS -->
    <meta content="yes" name="apple-mobile-web-app-capable"/>
    <meta content="black" name="apple-mobile-web-app-status-bar-style"/>
    <meta content="Veronalabs" name="apple-mobile-web-app-title"/>

    <!-- Favicon -->
    <link href="favicon-32x32.png?v=1.0.0" rel="icon" sizes="32x32" type="image/png"/>
    <link href="site.webmanifest?v=1.0.0" rel="manifest"/>

    <!-- Tile color for Windows -->
    <meta content="mstile-150x150.png" name="msapplication-TileImage"/>
    <meta content="#3c3ce5" name="msapplication-TileColor"/>

    <!-- Theme color -->
    <meta content="#ffffff" name="theme-color"/>

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>

    <!-- Styles -->
    <link href="<?= WP_SMS_URL . 'assets/css/main.min.css' ?>" rel="stylesheet"/>
</head>

<body class="wpsms-onboarding">
<div id="main" role="content">

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