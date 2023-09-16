<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?php echo esc_html($email_title); ?></title>
    <style type="text/css">
        .mail-body {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            text-decoration: none;
            font-family: Inter, Arial, Helvetica, sans-serif;
            background-color: #f7f7f7;
            width: 100%;
            padding: 100px 0 50px 0
        }

        .mail-body * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            text-decoration: none;
            font-family: Inter, Arial, Helvetica, sans-serif
        }

        .mail-body .main-section {
            max-width: 580px;
            margin: auto;
            border-radius: 15px;
            background-color: white;
            -webkit-box-shadow: 0px 4px 15px 0px rgba(0, 0, 0, 0.05);
            -moz-box-shadow: 0px 4px 15px 0px rgba(0, 0, 0, 0.05);
            box-shadow: 0px 4px 15px 0px rgba(0, 0, 0, 0.05)
        }

        .mail-body .main-section .header {
            background: url(<?php echo WP_SMS_URL . '/assets/images/email-background.jpg'; ?>) no-repeat center center/cover;
            width: 100%;
            padding: 15px;
            position: relative;
            border-radius: 15px 15px 0 0;
            min-height: 100px
        }

        .mail-body .main-section .header .wp-sms-logo {
            position: absolute;
            right: 0px;
            bottom: 0px;
            height: auto;
            width: 200px
        }

        .mail-body .main-section .header .wp-sms-logo img {
            position: absolute;
            right: 20px;
            bottom: 20px;
            width: 100%
        }

        .mail-body .main-section .content {
            padding: 50px 50px
        }

        .mail-body .main-section .content h1, .mail-body .main-section .content h2, .mail-body .main-section .content h3, .mail-body .main-section .content h4, .mail-body .main-section .content h5, .mail-body .main-section .content h6 {
            color: #222222;
            text-align: left;
            margin: 20px 0 20px 0
        }

        .mail-body .main-section .content p {
            line-height: 22px;
            margin-bottom: 0;
            color: #222222;
            font-size: 15px;
            margin-top: 13px;
            margin-bottom: 10px;
        }

        .mail-body .main-section .content .button {
            display: inline-block;
            background-color: black;
            border-radius: 10px;
            padding: 14px 18px;
            text-align: center;
            color: white;
            margin: 30px auto;
            transition: 0.3s all ease-out
        }

        .mail-body .main-section .content .button:hover {
            background-color: #f1692c
        }

        .mail-body .footer-links {
            max-width: 50%;
            text-align: center;
            margin: 30px auto;
            display: block
        }

        .mail-body .footer-links p {
            color: #808080;
            font-size: small
        }

        .mail-body .footer-links p a {
            text-decoration: underline;
            color: #535353;
            font-size: small;
            transition: 0.3s all ease-out
        }

        .mail-body .footer-links p a:hover {
            color: #222222
        }

        .mail-body .main-section code, .mail-body .main-section pre {
            background-color: #f0f0f0;
            padding: 11px 12px !important;
            border-radius: 3px;
            font-family: Monaco, monospace;
            font-size: 14px;
            color: #333;
            white-space: pre-wrap;
            display: block;
        }

        @media screen and (min-width: 450px) and (max-width: 580px) {
            .mail-body {
                padding: 100px 10px
            }
        }

        @media screen and (max-width: 450px) {
            .mail-body {
                padding: 50px 10px
            }

            .mail-body .main-section .content {
                padding: 20px
            }

            .mail-body .main-section .content h1 {
                font-size: 18px
            }

            .mail-body .main-section .content h2, .mail-body .main-section .content h3, .mail-body .main-section .content h4, .mail-body .main-section .content h5, .mail-body .main-section .content h6 {
                font-size: 16px
            }

            .mail-body .main-section .content p {
                font-size: 14px
            }

            .mail-body .main-section .content .button {
                font-size: 15px
            }

            .mail-body .footer-links {
                max-width: 90%
            }
        }
    </style>
</head>

<body style="margin:0; padding:0;">
<div class="mail-body">

    <div class="main-section">
        <div class="header">
            <a href="<?php echo esc_url(WP_SMS_SITE); ?>" target="_blank" class="wp-sms-logo"><img src="<?php echo WP_SMS_URL . '/assets/images/email-logo.png'; ?>" alt=""></a>
        </div>
        <div class="content">
            <h2><?php echo esc_html($email_title); ?></h2>
            <p><?php _e('Hello,', 'wp-sms'); ?></p>
            <p><?php echo wp_kses_post($content); ?></p>
            <p style="text-align:center;"><a href="<?php echo esc_url($cta_link); ?>" class="button"><?php echo esc_html($cta_title); ?></a></p>

            <p><?php _e('Best regards,', 'wp-sms'); ?></p>
        </div>
    </div>

    <div class="footer-links">
        <p><?php _e('This email automatically has been sent from ', 'wp-sms'); ?><a href="<?php echo esc_url($site_url); ?>"><?php echo esc_html($site_name); ?></a></p>
    </div>

</div>
</body>

