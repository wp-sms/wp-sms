<form method="post">

    <div class="u-flex u-flex--column u-align-center c-ready__message">
        <svg width="66" height="65" viewBox="0 0 66 65" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M32.9998 64.5833C15.2807 64.5833 0.916504 50.219 0.916504 32.5C0.916504 14.7809 15.2807 0.416687 32.9998 0.416687C50.7188 0.416687 65.0831 14.7809 65.0831 32.5C65.0831 50.219 50.7188 64.5833 32.9998 64.5833ZM29.7998 45.3333L52.4863 22.647L47.949 18.1097L29.7998 36.2589L20.7254 27.1841L16.1881 31.7217L29.7998 45.3333Z" fill="#058860"/>
        </svg>
        <h1 class="u-text-center">
            <?php esc_html_e('Onboarding process successfully completed!', 'wp-sms'); ?>
        </h1>
        <p class="u-text-center">
            <?php esc_html_e('Start using the plugin to send SMS messages from your WordPress site.', 'wp-sms'); ?>
        </p>
        <a href="<?php echo esc_url(admin_url('/?page=wp-sms')); ?>" title="<?php esc_attr_e('start using WP SMS', 'wp-sms'); ?>" class="c-btn c-btn--primary c-btn--mainready">
            <?php esc_html_e('Start using WP SMS', 'wp-sms'); ?>
        </a>
    </div>
    <div class="c-ready-row">
        <h3 class="c-ready__title"><?php esc_html_e('Support', 'wp-sms'); ?></h3>
        <div class="c-ready__items u-flex u-align-stretch u-content-sp">
            <div class="c-readycard">
                <span class="c-readycard__icon c-readycard__icon--document"></span>
                <h2 class="c-readycard__title"><?php esc_html_e('Documentation', 'wp-sms'); ?></h2>
                <p class="c-readycard__desc">
                    <?php esc_html_e('Find everything you need to get WP-SMS up and running.', 'wp-sms'); ?>
                </p>
                <a class="c-btn" href="<?php echo esc_url('https://wp-sms-pro.com/documentation/?utm_source=wp-sms&utm_medium=link&utm_campaign=onboarding'); ?>" target="_blank" title="<?php esc_attr_e('View', 'wp-sms'); ?>"><?php esc_html_e('View', 'wp-sms'); ?></a>
            </div>
            <div class="c-readycard">
                <span class="c-readycard__icon c-readycard__icon--faq"></span>
                <h2 class="c-readycard__title">FAQ</h2>
                <p class="c-readycard__desc">
                    <?php esc_html_e("If you've encountered an issue or have a question.", 'wp-sms'); ?>
                </p>
                <a class="c-btn" href="<?php echo esc_url('https://wp-sms-pro.com/faq/?utm_source=wp-sms&utm_medium=link&utm_campaign=onboarding'); ?>" target="_blank" title="<?php esc_attr_e('View', 'wp-sms'); ?>"><?php esc_html_e('View', 'wp-sms'); ?></a>
            </div>
            <div class="c-readycard">
                <span class="c-readycard__icon c-readycard__icon--plugin"></span>
                <h2 class="c-readycard__title"><?php esc_html_e('Plugin customization', 'wp-sms'); ?></h2>
                <p class="c-readycard__desc"><?php esc_html_e('Needs any customization?', 'wp-sms'); ?></p>
                <a class="c-btn" href="<?php echo esc_url('https://wp-sms-pro.com/contact/?utm_source=wp-sms&utm_medium=link&utm_campaign=onboarding'); ?>" target="_blank" title="<?php esc_attr_e('View', 'wp-sms'); ?>"><?php esc_html_e('View', 'wp-sms'); ?></a>
            </div>
        </div>
    </div>
    <div class="c-ready-row">
        <h3 class="c-ready__title"><?php _e('Integrations', 'wp-sms'); ?></h3>
        <div class="c-ready__items u-flex u-align-stretch u-content-sp">
            <div class="c-readycard c-readycard--integration">
                <svg width="30" height="30" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <g clip-path="url(#clip0_11815_3666)">
                        <path d="M15 30C23.2843 30 30 23.2843 30 15C30 6.71573 23.2843 0 15 0C6.71573 0 0 6.71573 0 15C0 23.2843 6.71573 30 15 30Z" fill="#9B5C8F"/>
                        <path d="M7.36527 10.0078H22.6289C23.5957 10.0078 24.375 10.7871 24.375 11.7539V17.5781C24.375 18.5449 23.5957 19.3242 22.6289 19.3242H17.1563L17.9063 21.1641L14.6016 19.3242H7.37113C6.40433 19.3242 5.62503 18.5449 5.62503 17.5781V11.7539C5.61917 10.793 6.39847 10.0078 7.36527 10.0078Z" fill="white"/>
                        <path
                            d="M6.69151 11.6016C6.79698 11.4551 6.96104 11.3789 7.17198 11.3672C7.5587 11.3379 7.78135 11.5196 7.83409 11.918C8.06846 13.5117 8.33213 14.8594 8.60167 15.9668L10.2716 12.791C10.4239 12.5039 10.6173 12.3516 10.8458 12.334C11.1798 12.3106 11.3849 12.5274 11.4728 12.9727C11.6661 13.9864 11.9064 14.8477 12.1993 15.5801C12.3985 13.6465 12.7325 12.252 13.2071 11.3907C13.3243 11.1797 13.4884 11.0684 13.711 11.0567C13.8868 11.0391 14.045 11.0977 14.1915 11.209C14.338 11.3203 14.4142 11.4668 14.4259 11.6426C14.4317 11.7774 14.4083 11.8946 14.3497 12.0059C14.0509 12.5567 13.8106 13.4766 13.6114 14.7598C13.4181 16.002 13.3536 16.9688 13.4005 17.666C13.4181 17.8594 13.3829 18.0235 13.3067 18.17C13.213 18.3399 13.0782 18.4278 12.9024 18.4453C12.7032 18.4629 12.4981 18.3692 12.2989 18.1641C11.5899 17.4375 11.0274 16.3594 10.6173 14.918C10.1192 15.8965 9.75596 16.6231 9.52159 17.1153C9.07042 17.9766 8.68956 18.416 8.37315 18.4395C8.16807 18.4571 7.99229 18.2813 7.83995 17.9121C7.45323 16.916 7.03135 14.9824 6.58018 12.1289C6.53917 11.9121 6.58604 11.7364 6.69151 11.6016ZM23.0743 12.7969C22.7989 12.3164 22.3946 12.0293 21.8556 11.9121C21.7091 11.8828 21.5743 11.8653 21.4454 11.8653C20.713 11.8653 20.1212 12.2461 19.6524 13.0078C19.254 13.6582 19.0606 14.3731 19.0606 15.1582C19.0606 15.7442 19.1837 16.2481 19.4239 16.6699C19.6993 17.1504 20.1036 17.4375 20.6427 17.5547C20.7892 17.584 20.9239 17.6016 21.0528 17.6016C21.7911 17.6016 22.3888 17.2207 22.8458 16.459C23.2442 15.8028 23.4376 15.0879 23.4376 14.3028C23.4435 13.7051 23.3146 13.2071 23.0743 12.7969ZM22.1134 14.9063C22.0079 15.4102 21.8146 15.7852 21.5333 16.0371C21.3106 16.2364 21.1056 16.3184 20.9181 16.2832C20.7364 16.2422 20.5841 16.084 20.4669 15.7852C20.3731 15.5508 20.3321 15.3106 20.3321 15.0938C20.3321 14.9004 20.3497 14.7129 20.3849 14.5371C20.4552 14.2266 20.5841 13.9219 20.7892 13.6289C21.0411 13.2539 21.3048 13.1016 21.5802 13.1543C21.7618 13.1953 21.9142 13.3535 22.0314 13.6524C22.1251 13.8867 22.1661 14.127 22.1661 14.3438C22.1661 14.543 22.1485 14.7305 22.1134 14.9063ZM18.2989 12.7969C18.0235 12.3164 17.6134 12.0293 17.0802 11.9121C16.9337 11.8828 16.7989 11.8653 16.67 11.8653C15.9376 11.8653 15.3458 12.2461 14.8771 13.0078C14.4786 13.6582 14.2853 14.3731 14.2853 15.1582C14.2853 15.7442 14.4083 16.2481 14.6485 16.6699C14.9239 17.1504 15.3282 17.4375 15.8673 17.5547C16.0138 17.584 16.1485 17.6016 16.2774 17.6016C17.0157 17.6016 17.6134 17.2207 18.0704 16.459C18.4689 15.8028 18.6622 15.0879 18.6622 14.3028C18.6681 13.7051 18.545 13.2071 18.2989 12.7969ZM17.3321 14.9063C17.2267 15.4102 17.0333 15.7852 16.7521 16.0371C16.5294 16.2364 16.3243 16.3184 16.1368 16.2832C15.9552 16.2422 15.8028 16.084 15.6856 15.7852C15.5919 15.5508 15.5509 15.3106 15.5509 15.0938C15.5509 14.9004 15.5685 14.7129 15.6036 14.5371C15.6739 14.2266 15.8028 13.9219 16.0079 13.6289C16.2599 13.2539 16.5235 13.1016 16.7989 13.1543C16.9806 13.1953 17.1329 13.3535 17.2501 13.6524C17.3439 13.8867 17.3849 14.127 17.3849 14.3438C17.3966 14.543 17.3731 14.7305 17.3321 14.9063Z"
                            fill="#9B5C8F"/>
                    </g>
                    <defs>
                        <clipPath id="clip0_11815_3666">
                            <rect width="30" height="30" fill="white"/>
                        </clipPath>
                    </defs>
                </svg>
                <a class="c-readycard__title" target="_blank" title="<?php esc_attr_e('WooCommerce', 'wp-sms'); ?>" href="<?php echo esc_url('https://wp-sms-pro.com/woocommerce-sms-integration/?utm_source=wp-sms&utm_medium=link&utm_campaign=onboarding'); ?>">
                    <?php esc_html_e('WooCommerce', 'wp-sms'); ?>
                </a>
                <p class="c-readycard__desc">
                    <?php esc_html_e('Turn your site into a functional WordPress e-commerce website with just a few clicks.', 'wp-sms'); ?>
                </p>
                <div class="u-flex u-align-center">
                    <a title="<?php esc_attr_e('Pro Version Required', 'wp-sms'); ?>" target="_blank" href="<?php echo esc_url('https://wp-sms-pro.com/buy/?utm_source=wp-sms&utm_medium=link&utm_campaign=onboarding'); ?>" class="c-readycard__badge">
                        <?php esc_html_e('Pro Version Required', 'wp-sms'); ?>
                    </a>
                </div>
            </div>
            <div class="c-readycard c-readycard--integration">
                <svg fill="none" height="28" width="23" xmlns="http://www.w3.org/2000/svg">
                    <path clip-rule="evenodd" d="M17.042 0a1.724 1.724 0 0 0-1.697 1.75v9.273a1.726 1.726 0 0 0 2.39 1.614 1.725 1.725 0 0 0 1.058-1.614V1.749A1.725 1.725 0 0 0 17.043 0Z" fill="#6596FF" fill-rule="evenodd"/>
                    <path clip-rule="evenodd" d="M.936 9.52S0 9.52 0 10.312L0 16.968v.133h.004C.075 23.13 5.01 28 11.09 28c6.06 0 11.04-4.834 11.147-10.835h.005v-6.852c0-.793-.934-.794-.934-.794H.936Z" fill="#6596FF" fill-rule="evenodd"/>
                    <path d="M4.318 19.03a1.694 1.694 0 1 0 0-3.388 1.694 1.694 0 0 0 0 3.388Z" fill="#444"/>
                    <path clip-rule="evenodd" d="M5.066.1a1.675 1.675 0 0 0-1.649 1.697v9.274a1.675 1.675 0 1 0 3.348 0V1.797A1.675 1.675 0 0 0 5.066.1Z" fill="#6596FF" fill-rule="evenodd"/>
                    <path d="M17.615 19.034a1.694 1.694 0 1 0 0-3.388 1.694 1.694 0 0 0 0 3.388Z" fill="#444"/>
                    <path clip-rule="evenodd" d="M5.478 20.13a.376.376 0 0 0-.25.637c.176.19.695.847 1.62 1.434.925.586 2.283 1.127 4.21 1.214h.03c2.17-.066 3.588-.564 4.515-1.13.927-.565 1.37-1.222 1.54-1.452a.378.378 0 0 0-.354-.613.375.375 0 0 0-.25.165c-.203.274-.525.767-1.329 1.258-.8.489-2.076.952-4.13 1.017-1.797-.083-3.009-.576-3.828-1.096-.822-.521-1.223-1.042-1.47-1.308a.376.376 0 0 0-.304-.125Z" fill="#444" fill-rule="evenodd"/>
                </svg>
                <h2 class="c-readycard__title"><?php esc_html_e('Ultimate Member', 'wp-sms'); ?></h2>
                <p class="c-readycard__desc">
                    <?php esc_html_e('Sync Mobile number from Ultimate Members mobile number form field.', 'wp-sms'); ?>
                </p>
                <div class="u-flex u-align-center">
                    <a title="<?php esc_attr_e('Pro Version Required', 'wp-sms'); ?>" target="_blank" href="<?php echo esc_url('https://wp-sms-pro.com/buy/?utm_source=wp-sms&utm_medium=link&utm_campaign=onboarding'); ?>" class="c-readycard__badge">
                        <?php esc_html_e('Pro Version Required', 'wp-sms'); ?>
                    </a>
                </div>
            </div>
            <div class="c-readycard c-readycard--integration">
                <svg fill="none" height="29" width="29" xmlns="http://www.w3.org/2000/svg">
                    <path clip-rule="evenodd"
                          d="M12.296 28.402c-5.42-.658-9.988-4.446-11.66-9.67a16.85 16.85 0 0 1-.573-2.66c-.094-.754-.08-2.574.025-3.35.234-1.717.628-3.014 1.368-4.501a13.788 13.788 0 0 1 4.701-5.366c.696-.47.868-.57 1.614-.944A13.972 13.972 0 0 1 12.347.537c.695-.087 2.63-.087 3.325 0 4.738.596 8.785 3.451 10.892 7.684 1.012 2.035 1.453 3.924 1.455 6.24.002 1.204-.05 1.743-.274 2.865-1.167 5.846-6.105 10.377-12.072 11.078-.78.091-2.618.09-3.377-.002Zm3.19-.614c2.6-.304 4.93-1.276 6.936-2.892 2.55-2.055 4.25-4.98 4.816-8.29.139-.814.192-2.644.1-3.488-.178-1.66-.6-3.107-1.335-4.587-2.009-4.044-5.8-6.742-10.33-7.351-.86-.115-2.466-.115-3.326 0-4.564.613-8.36 3.33-10.363 7.42A13.507 13.507 0 0 0 .68 13.119c-.09.846-.037 2.678.102 3.488.333 1.951.98 3.616 2.02 5.196a13.412 13.412 0 0 0 8.566 5.806c1.264.256 2.871.326 4.119.18Zm-3.062-1.385c-2.967-.402-5.72-1.909-7.606-4.161-2.525-3.017-3.425-7.007-2.443-10.837.409-1.594 1.31-3.346 2.367-4.605a17.269 17.269 0 0 1 1.722-1.702c1.627-1.33 3.813-2.259 6.037-2.564.626-.085 2.392-.085 3.018 0 2.223.305 4.41 1.234 6.037 2.564.465.38 1.326 1.231 1.722 1.702.78.929 1.6 2.352 2.035 3.53.81 2.196.942 4.807.36 7.102a12.29 12.29 0 0 1-3.383 5.774 12.024 12.024 0 0 1-6.737 3.199c-.743.097-2.408.096-3.13-.002Zm8.558-4.971c.02-.05.036-.55.035-1.113-.002-1.036-.058-1.505-.265-2.226a5.346 5.346 0 0 0-1.134-2.055c-.726-.793-1.976-1.405-3.08-1.507l-.335-.032-1.487 1.465-1.487 1.464-1.491-1.465-1.492-1.464-.385.034a6.007 6.007 0 0 0-1.732.46c-1.48.684-2.37 2.02-2.695 4.047-.07.44-.091 2.109-.029 2.34l.038.141h7.75c7.335 0 7.754-.005 7.789-.09Zm2.657-2.152c.048-.03.05-.21.013-.78-.132-1.97-.629-3.266-1.604-4.178-.661-.62-1.617-.984-2.58-.986-.297 0-.273-.018-.788.575-.223.257-.243.298-.16.327.694.246 1.204.559 1.674 1.025.414.412.704.833 1.001 1.456.298.626.474 1.206.597 1.97.049.305.114.573.144.593.073.05 1.623.049 1.703-.002Zm-9.617-4.797a4.148 4.148 0 0 0 2.628-1.802c.455-.694.632-1.32.632-2.236 0-.537-.022-.742-.114-1.064-.42-1.454-1.497-2.515-2.954-2.906-.569-.152-1.474-.153-2.04-.002-1.446.389-2.547 1.487-2.955 2.949-.15.538-.16 1.465-.023 1.995.344 1.32 1.3 2.409 2.518 2.87.742.28 1.559.35 2.308.195Zm4.06-1.627c.518-.21.871-.45 1.328-.902.514-.51.794-.954 1.029-1.637.158-.46.167-.525.169-1.227 0-.626-.017-.806-.116-1.149a4.125 4.125 0 0 0-3.062-2.897c-.405-.094-.582-.108-1.082-.085-.645.03-1.062.133-1.636.404-.34.161-.364.216-.114.266.29.058.923.35 1.32.609a4.97 4.97 0 0 1 2.26 4.182c0 .844-.228 1.75-.604 2.4l-.13.226.166-.034c.092-.018.305-.089.472-.156Z"
                          fill="#D84D26" fill-rule="evenodd"/>
                </svg>
                <h2 class="c-readycard__title"><?php esc_html_e('BuddyPress', 'wp-sms'); ?></h2>
                <p class="c-readycard__desc">
                    <?php esc_html_e('build any kind of community website with member profiles, activity streams, and more.', 'wp-sms'); ?>
                </p>
                <div class="u-flex u-align-center">
                    <a title="<?php esc_attr_e('Pro Version Required', 'wp-sms'); ?>" target="_blank" href="<?php echo esc_url('https://wp-sms-pro.com/buy/?utm_source=wp-sms&utm_medium=link&utm_campaign=onboarding'); ?>" class="c-readycard__badge">
                        <?php esc_html_e('Pro Version Required', 'wp-sms'); ?>
                    </a>
                </div>
            </div>
            <div class="c-readycard c-readycard--integration">
                <svg fill="none" height="28" width="20" xmlns="http://www.w3.org/2000/svg">
                    <path d="M2.87 0 0 8.213 10.62 8.2 3.38 28h8.758l7.256-19.793L13.641.013 2.871 0Z" fill="#4668FF"/>
                    <path d="m2.87 0 10.771.014-3.02 8.186L0 8.213 2.87 0Z" fill="#001FA4"/>
                    <path d="m10.621 8.2 3.02-8.186 5.753 8.193L12.138 28H3.38l7.24-19.8Z" fill="#4668FF"/>
                </svg>
                <a class="c-readycard__title" target="_blank" title="<?php esc_attr_e('Contact Form 7', 'wp-sms'); ?>" href="<?php echo esc_url('https://wp-sms-pro.com/contact-form-7-sms-integration/?utm_source=wp-sms&utm_medium=link&utm_campaign=onboarding'); ?>">
                    <?php esc_html_e('Contact Form 7', 'wp-sms'); ?>
                </a>
                <p class="c-readycard__desc">
                    <?php esc_html_e('Send contact data to the Constant Contact API.', 'wp-sms'); ?>
                </p>
            </div>
            <div class="c-readycard c-readycard--integration">
                <svg fill="none" height="28" width="26" xmlns="http://www.w3.org/2000/svg">
                    <path clip-rule="evenodd" d="M25.43 19.015c0 1.279-.906 2.85-2.015 3.49l-8.687 5.015c-1.107.64-2.92.64-4.028 0l-8.686-5.016C.906 21.864 0 20.295 0 19.015V8.984c0-1.28.906-2.85 2.014-3.488L10.7.48c1.108-.64 2.92-.64 4.029 0l8.686 5.016c1.108.64 2.015 2.209 2.015 3.488v10.031Z" fill="#F15A2B" fill-rule="evenodd"/>
                    <path clip-rule="evenodd" d="M10.25 11.677h11.51V8.439H10.277c-1.642 0-3.004.563-4.046 1.671-2.506 2.668-2.574 9.39-2.574 9.39H21.67v-6.082h-3.236v2.845H7.111c.072-1.06.557-2.955 1.479-3.936.417-.443.944-.65 1.66-.65Z" fill="#fff" fill-rule="evenodd"/>
                </svg>
                <h2 class="c-readycard__title">
                    <?php esc_html_e('GravityForms', 'wp-sms'); ?>
                </h2>
                <p class="c-readycard__desc">
                    <?php esc_html_e("Integrate any form with Agile's Sales and Marketing CRM.", 'wp-sms'); ?>
                </p>
                <div class="u-flex u-align-center">
                    <a title="<?php esc_attr_e('Pro Version Required', 'wp-sms'); ?>" target="_blank" href="<?php echo esc_url('https://wp-sms-pro.com/buy/?utm_source=wp-sms&utm_medium=link&utm_campaign=onboarding'); ?>" class="c-readycard__badge">
                        <?php esc_html_e('Pro Version Required', 'wp-sms'); ?>
                    </a>
                </div>
            </div>
            <div class="c-readycard c-readycard--integration">
                <svg fill="none" height="27" width="60" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                    <path d="M0 0h60v27H0z" fill="url(#a)"/>
                    <defs>
                        <pattern height="1" id="a" patternContentUnits="objectBoundingBox" width="1">
                            <use transform="matrix(.004 0 0 .00889 0 -.593)" xlink:href="#b"/>
                        </pattern>
                        <image height="250" id="b" width="250"
                               xlink:href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAPoAAAD6CAYAAACI7Fo9AAAACXBIWXMAAAsTAAALEwEAmpwYAAAGymlUWHRYTUw6Y29tLmFkb2JlLnhtcAAAAAAAPD94cGFja2V0IGJlZ2luPSLvu78iIGlkPSJXNU0wTXBDZWhpSHpyZVN6TlRjemtjOWQiPz4gPHg6eG1wbWV0YSB4bWxuczp4PSJhZG9iZTpuczptZXRhLyIgeDp4bXB0az0iQWRvYmUgWE1QIENvcmUgNS42LWMxNDUgNzkuMTYzNDk5LCAyMDE4LzA4LzEzLTE2OjQwOjIyICAgICAgICAiPiA8cmRmOlJERiB4bWxuczpyZGY9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkvMDIvMjItcmRmLXN5bnRheC1ucyMiPiA8cmRmOkRlc2NyaXB0aW9uIHJkZjphYm91dD0iIiB4bWxuczp4bXBNTT0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL21tLyIgeG1sbnM6c3RSZWY9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9zVHlwZS9SZXNvdXJjZVJlZiMiIHhtbG5zOnN0RXZ0PSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvc1R5cGUvUmVzb3VyY2VFdmVudCMiIHhtbG5zOnhtcD0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wLyIgeG1sbnM6ZGM9Imh0dHA6Ly9wdXJsLm9yZy9kYy9lbGVtZW50cy8xLjEvIiB4bWxuczpwaG90b3Nob3A9Imh0dHA6Ly9ucy5hZG9iZS5jb20vcGhvdG9zaG9wLzEuMC8iIHhtcE1NOk9yaWdpbmFsRG9jdW1lbnRJRD0ieG1wLmRpZDpkZDA1NDA4ZS05ZjA3LTc2NDQtOWNiMC00OWQwOGNmYjVhZjciIHhtcE1NOkRvY3VtZW50SUQ9InhtcC5kaWQ6NDJGMUU1MzhEQTA2MTFFN0I4MzQ5NDE5MzIxOTc2QzEiIHhtcE1NOkluc3RhbmNlSUQ9InhtcC5paWQ6ZTM0OTc5ZjEtYTYxNi05ZjRiLWI4YmQtZTA0MDYyMWYyYWQxIiB4bXA6Q3JlYXRvclRvb2w9IkFkb2JlIFBob3Rvc2hvcCBDQyAyMDE0IChXaW5kb3dzKSIgeG1wOkNyZWF0ZURhdGU9IjIwMTktMDctMDZUMTE6NDQ6MTgrMDQ6MzAiIHhtcDpNb2RpZnlEYXRlPSIyMDE5LTA3LTA2VDExOjU0OjU5KzA0OjMwIiB4bXA6TWV0YWRhdGFEYXRlPSIyMDE5LTA3LTA2VDExOjU0OjU5KzA0OjMwIiBkYzpmb3JtYXQ9ImltYWdlL3BuZyIgcGhvdG9zaG9wOkNvbG9yTW9kZT0iMyIgcGhvdG9zaG9wOklDQ1Byb2ZpbGU9InNSR0IgSUVDNjE5NjYtMi4xIj4gPHhtcE1NOkRlcml2ZWRGcm9tIHN0UmVmOmluc3RhbmNlSUQ9InhtcC5paWQ6NTVhMTRmNzMtYTFjMS05MDQzLTg5YzItMWYyOGY5ODBhOTg4IiBzdFJlZjpkb2N1bWVudElEPSJ4bXAuZGlkOmRkMDU0MDhlLTlmMDctNzY0NC05Y2IwLTQ5ZDA4Y2ZiNWFmNyIvPiA8eG1wTU06SGlzdG9yeT4gPHJkZjpTZXE+IDxyZGY6bGkgc3RFdnQ6YWN0aW9uPSJzYXZlZCIgc3RFdnQ6aW5zdGFuY2VJRD0ieG1wLmlpZDpkN2IyNmNkNC04MzllLWUwNGQtYTY5NS01ZDJiMTRlMjljYTMiIHN0RXZ0OndoZW49IjIwMTktMDctMDZUMTE6NTQ6NDUrMDQ6MzAiIHN0RXZ0OnNvZnR3YXJlQWdlbnQ9IkFkb2JlIFBob3Rvc2hvcCBDQyAyMDE5IChXaW5kb3dzKSIgc3RFdnQ6Y2hhbmdlZD0iLyIvPiA8cmRmOmxpIHN0RXZ0OmFjdGlvbj0ic2F2ZWQiIHN0RXZ0Omluc3RhbmNlSUQ9InhtcC5paWQ6ZTM0OTc5ZjEtYTYxNi05ZjRiLWI4YmQtZTA0MDYyMWYyYWQxIiBzdEV2dDp3aGVuPSIyMDE5LTA3LTA2VDExOjU0OjU5KzA0OjMwIiBzdEV2dDpzb2Z0d2FyZUFnZW50PSJBZG9iZSBQaG90b3Nob3AgQ0MgMjAxOSAoV2luZG93cykiIHN0RXZ0OmNoYW5nZWQ9Ii8iLz4gPC9yZGY6U2VxPiA8L3htcE1NOkhpc3Rvcnk+IDwvcmRmOkRlc2NyaXB0aW9uPiA8L3JkZjpSREY+IDwveDp4bXBtZXRhPiA8P3hwYWNrZXQgZW5kPSJyIj8+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"/>
                    </defs>
                </svg>
                <h2 class="c-readycard__title"><?php esc_html_e('Quform', 'wp-sms'); ?></h2>
                <p class="c-readycard__desc">
                    <?php esc_html_e('Create WordPress forms quickly and easily, no coding required.', 'wp-sms'); ?>
                </p>
                <div class="u-flex u-align-center">
                    <a title="<?php esc_attr_e('Pro Version Required', 'wp-sms'); ?>" target="_blank" href="<?php echo esc_url('https://wp-sms-pro.com/buy/?utm_source=wp-sms&utm_medium=link&utm_campaign=onboarding'); ?>" class="c-readycard__badge">
                        <?php esc_html_e('Pro Version Required', 'wp-sms'); ?>
                    </a>
                </div>
            </div>
            <div class="c-readycard c-readycard--integration">
                <svg fill="none" height="28" width="28" xmlns="http://www.w3.org/2000/svg">
                    <path clip-rule="evenodd" d="M23.79 4.1A13.857 13.857 0 0 0 13.935 0a13.86 13.86 0 0 0-9.854 4.1A13.988 13.988 0 0 0 0 14a13.99 13.99 0 0 0 4.081 9.9 13.86 13.86 0 0 0 9.855 4.1 13.86 13.86 0 0 0 9.854-4.1A13.99 13.99 0 0 0 27.87 14a13.99 13.99 0 0 0-4.082-9.9Zm-.46 19.337a13.212 13.212 0 0 1-9.394 3.909c-3.669 0-6.99-1.494-9.395-3.909A13.335 13.335 0 0 1 .651 14c0-3.686 1.486-7.022 3.89-9.437a13.213 13.213 0 0 1 9.394-3.91c3.669 0 6.99 1.495 9.394 3.91A13.335 13.335 0 0 1 27.219 14c.001 3.685-1.486 7.022-3.89 9.437Z" fill="#35495C" fill-rule="evenodd"/>
                    <path clip-rule="evenodd"
                          d="M25.924 13.883c-.063-6.598-5.405-11.927-11.988-11.927-6.582 0-11.926 5.33-11.987 11.928L7.446 8.36l1.843 1.852-4.022 4.042h17.339l-4.023-4.042 1.844-1.852 5.497 5.522Zm-11.988-2.07L8.828 6.43h3.394V3.775c0-.69.772-1.254 1.714-1.254s1.713.564 1.713 1.254v2.654h3.395l-5.108 5.385ZM15.426 20.778a4.507 4.507 0 0 0-1.034-.477 7.116 7.116 0 0 1-.784-.314c-.203-.1-.353-.21-.449-.325a.64.64 0 0 1-.144-.423c0-.13.04-.254.123-.371a.868.868 0 0 1 .384-.288c.176-.075.4-.115.678-.116.224 0 .429.019.614.051a3.253 3.253 0 0 1 .828.255l.309-.937a3.063 3.063 0 0 0-.706-.236 4.589 4.589 0 0 0-.832-.096v-.97h-.85v1.019a3.27 3.27 0 0 0-.392.088 2.497 2.497 0 0 0-.804.39c-.22.167-.391.363-.509.588a1.598 1.598 0 0 0-.178.738c.002.308.082.579.24.813.159.235.385.438.677.613.29.174.636.323 1.037.45.3.096.546.197.733.301.187.103.324.217.41.341.086.125.13.269.128.43 0 .176-.052.33-.153.462a.965.965 0 0 1-.441.306 2.06 2.06 0 0 1-.709.112 4.038 4.038 0 0 1-1.253-.207 3.153 3.153 0 0 1-.487-.205l-.3.977c.136.074.305.142.511.204.207.062.435.111.684.15.247.036.501.055.76.057h.045v1.002h.85v-1.083c.109-.02.212-.046.31-.075.34-.103.624-.244.848-.424.226-.179.393-.386.504-.62.11-.232.166-.48.166-.742 0-.308-.066-.58-.201-.815a1.873 1.873 0 0 0-.613-.623Z"
                          fill="#35495C" fill-rule="evenodd"/>
                </svg>
                <h2 class="c-readycard__title">
                    <?php esc_html_e('Easy Digital Downloads', 'wp-sms'); ?>
                </h2>
                <p class="c-readycard__desc">
                    <?php esc_html_e('Selling digital products on the WordPress platform.', 'wp-sms'); ?>
                </p>
                <div class="u-flex u-align-center">
                    <a title="<?php esc_attr_e('Pro Version Required', 'wp-sms'); ?>" target="_blank" href="<?php echo esc_url('https://wp-sms-pro.com/buy/?utm_source=wp-sms&utm_medium=link&utm_campaign=onboarding'); ?>" class="c-readycard__badge">
                        <?php esc_html_e('Pro Version Required', 'wp-sms'); ?>
                    </a>
                </div>
            </div>
            <div class="c-readycard c-readycard--integration">
                <svg fill="none" height="28" width="20" xmlns="http://www.w3.org/2000/svg">
                    <path d="m10.45 19.81 1.26-2.115H8.282l1.26 2.114-1.764 5.794L10 28l2.213-2.397-1.763-5.794Zm6.176-2.367h-.611L10 27.779 3.984 17.443h-.61A3.352 3.352 0 0 0 .03 20.786V28h19.94v-7.214a3.352 3.352 0 0 0-3.344-3.343ZM12.8 8.397a1.43 1.43 0 0 0-1.427 1.427h2.862c0-.786-.641-1.427-1.435-1.427Zm-5.61 0a1.43 1.43 0 0 0-1.428 1.427h2.862A1.442 1.442 0 0 0 7.19 8.397Zm7.671-3.428H13.58l-.603-1.511h-2.512V2.45c.474-.183.802-.64.802-1.175C11.267.572 10.694 0 9.992 0S8.717.573 8.717 1.275c0 .534.336.992.802 1.175v1.008H7.007l-.603 1.511H5.13C2.694 4.97.832 7.1 1.282 9.481l1.328 6.801h14.764l1.328-6.801c.458-2.382-1.397-4.512-3.84-4.512Zm2.046 4.344c0 5.145-13.817 5.145-13.817 0 0-1.221.94-2.237 2.153-2.344h9.504a2.358 2.358 0 0 1 2.16 2.344Z" fill="#D75676"/>
                </svg>
                <h2 class="c-readycard__title">
                    <?php esc_html_e('WP Job Manager', 'wp-sms'); ?>
                </h2>
                <p class="c-readycard__desc">
                    <?php esc_html_e('Adding job-board like functionality to your WordPress site.', 'wp-sms'); ?>
                </p>
                <div class="u-flex u-align-center"><a title="<?php esc_attr_e('Pro Version Required', 'wp-sms'); ?>" target="_blank" href="https://wp-sms-pro.com/buy/?utm_source=wp-sms&utm_medium=link&utm_campaign=onboarding" class="c-readycard__badge"><?php _e('Pro Version Required', 'wp-sms'); ?></a></div>
            </div>
            <div class="c-readycard c-readycard--integration">
                <svg fill="none" height="29" width="35" xmlns="http://www.w3.org/2000/svg">
                    <path clip-rule="evenodd" d="M22.715 11.371 16.225.967.726 27.363 0 28.967h5.05L34.485 7.809H22.078l1.965 2.656-1.306.962-.022-.056Zm-3.83 2.893-8.327 6.132s4.316-8.296 5.689-10.697l2.604 4.521.033.044Z" fill="#3A99D9" fill-rule="evenodd"/>
                    <path clip-rule="evenodd" d="M8.435 28.967h12.19l-1.603-3.019 4.589-3.638.158.263 3.728 6.394h4.892l-7.006-12.718L8.435 28.967Z" fill="#B8E179" fill-rule="evenodd"/>
                </svg>
                <a class="c-readycard__title" target="_blank" title="<?php esc_attr_e('Awesome Support', 'wp-sms'); ?>" href="<?php echo esc_url('https://wp-sms-pro.com/awesome-support-sms-integration/?utm_source=wp-sms&utm_medium=link&utm_campaign=onboarding'); ?>">
                    <?php esc_html_e('Awesome Support', 'wp-sms'); ?>
                </a>
                <p class="c-readycard__desc">
                    <?php esc_html_e('The most versatile and feature-rich support plugin for WordPress.', 'wp-sms'); ?>
                </p>
                <div class="u-flex u-align-center">
                    <a title="<?php esc_attr_e('Pro Version Required', 'wp-sms'); ?>" target="_blank" href="<?php echo esc_url('https://wp-sms-pro.com/buy/?utm_source=wp-sms&utm_medium=link&utm_campaign=onboarding'); ?>" class="c-readycard__badge">
                        <?php esc_html_e('Pro Version Required', 'wp-sms'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <div class="c-ready-row">
        <h3 class="c-ready__title"><?php esc_html_e('Our products', 'wp-sms'); ?></h3>
        <div class="c-ready__items u-flex u-align-stretch u-content-sp">
            <div class="c-readycard">
                <svg fill="none" height="28" width="28" xmlns="http://www.w3.org/2000/svg">
                    <path d="M26.552 17.898a12.687 12.687 0 0 1-5.501 6.864 13.114 13.114 0 0 1-8.728 1.734 12.97 12.97 0 0 1-7.795-4.223 12.5 12.5 0 0 1-3.144-8.167 12.492 12.492 0 0 1 3.003-8.218 12.96 12.96 0 0 1 7.72-4.352 13.119 13.119 0 0 1 8.757 1.59 12.7 12.7 0 0 1 5.62 6.772h1.454a14.039 14.039 0 0 0-6.063-7.756 14.519 14.519 0 0 0-9.762-1.98c-3.39.511-6.48 2.201-8.705 4.762A13.835 13.835 0 0 0 0 14.107a13.843 13.843 0 0 0 3.55 9.13 14.369 14.369 0 0 0 8.778 4.631c3.398.46 6.852-.294 9.73-2.125A14.027 14.027 0 0 0 28 17.898h-1.448Z" fill="#000"/>
                    <path
                        d="m26.857 12.593-.736-.374-2.89 5.468a1.546 1.546 0 0 0-1.139-.434 1.565 1.565 0 0 0-1.103.516l-2.733-3.581c-.018-.027-.042-.047-.061-.072a1.486 1.486 0 0 0-.056-1.102 1.528 1.528 0 0 0-.4-.529 1.568 1.568 0 0 0-1.261-.355 1.575 1.575 0 0 0-.626.24 1.54 1.54 0 0 0-.465.475 1.506 1.506 0 0 0-.144 1.278c-.016.022-.037.038-.053.06l-1.919 3.244a1.557 1.557 0 0 0-2.097.051l-2.542-4.152a1.48 1.48 0 0 0 .097-.511c-.008-.4-.175-.781-.466-1.061a1.573 1.573 0 0 0-1.09-.437c-.409 0-.8.157-1.091.437-.291.28-.459.66-.466 1.06v.014c-.053.045-.102.08-.153.13l-4.54 4.544.592.568 4.382-4.385a1.562 1.562 0 0 0 2.199.348l2.629 4.297a1.493 1.493 0 0 0 .31 1.185c.132.166.298.303.487.403a1.577 1.577 0 0 0 1.791-.223 1.5 1.5 0 0 0 .5-1.121 1.468 1.468 0 0 0-.05-.361l1.993-3.366a1.542 1.542 0 0 0 1.95-.02l2.9 3.8a1.46 1.46 0 0 0-.017.155c.008.4.175.78.466 1.06.291.28.683.437 1.09.437.409 0 .8-.157 1.091-.437.291-.28.459-.66.466-1.06a1.433 1.433 0 0 0-.019-.18l3.174-6.009ZM7.17 14c-.24 0-.473-.07-.673-.2a1.191 1.191 0 0 1-.445-.533 1.165 1.165 0 0 1 .262-1.293 1.233 1.233 0 0 1 1.319-.257c.221.09.41.242.543.437a1.17 1.17 0 0 1-.15 1.498 1.212 1.212 0 0 1-.856.348Zm5.087 5.768c-.24 0-.473-.07-.672-.2a1.193 1.193 0 0 1-.446-.532 1.165 1.165 0 0 1 .262-1.293 1.233 1.233 0 0 1 1.319-.257c.222.09.41.242.543.437a1.17 1.17 0 0 1-.15 1.498 1.223 1.223 0 0 1-.856.347Zm4.464-4.943c-.24 0-.474-.07-.673-.2a1.193 1.193 0 0 1-.446-.532 1.164 1.164 0 0 1 .263-1.293 1.233 1.233 0 0 1 1.32-.257c.22.09.41.242.542.437a1.17 1.17 0 0 1-.15 1.498 1.223 1.223 0 0 1-.856.348Zm5.426 5.14c-.239 0-.473-.069-.672-.2a1.192 1.192 0 0 1-.446-.532 1.165 1.165 0 0 1 .263-1.293 1.234 1.234 0 0 1 1.319-.257c.22.09.41.242.543.437a1.17 1.17 0 0 1-.15 1.498 1.223 1.223 0 0 1-.857.348Z"
                        fill="#000"/>
                </svg>
                <h2 class="c-readycard__title"><?php esc_html_e('WP Statistics', 'wp-sms'); ?></h2>
                <p class="c-readycard__desc">
                    <?php esc_html_e('Find everything you need to get WP-SMS up and running.', 'wp-sms'); ?>
                </p>
                <a class="c-btn" href="<?php echo esc_url('https://wp-statistics.com/?utm_source=wp-sms&utm_medium=link&utm_campaign=onboarding'); ?>" target="_blank" title="<?php esc_attr_e('Learn more', 'wp-sms'); ?>">
                    <?php esc_html_e('Learn more', 'wp-sms'); ?>
                </a>
            </div>
            <div class="c-readycard">
                <svg fill="none" height="28" width="28" xmlns="http://www.w3.org/2000/svg">
                    <path clip-rule="evenodd" d="M0 14C0 6.268 6.268 0 14 0c4.925 0 9.257 2.544 11.752 6.389L18.1 14.163l-.006.005-.006.006a.707.707 0 0 1-1.078-.075l-2.135-2.925c-1.83-2.51-5.495-2.732-7.615-.46L.55 17.898A14.007 14.007 0 0 1 0 14Zm2.516 8.01A13.984 13.984 0 0 0 14 28c7.732 0 14-6.268 14-14 0-1.18-.146-2.326-.42-3.42l-6.454 6.554a4.949 4.949 0 0 1-7.544-.535l-2.134-2.925a.707.707 0 0 0-1.088-.066L2.516 22.01Z" fill="#F22F46" fill-rule="evenodd"/>
                </svg>
                <h2 class="c-readycard__title"><?php esc_html_e('WP SlimStat', 'wp-sms'); ?></h2>
                <p class="c-readycard__desc">
                    <?php esc_html_e('Find everything you need to get WP-SMS up and running.', 'wp-sms'); ?>
                </p>
                <a class="c-btn" href="<?php echo esc_url('https://wp-slimstat.com/?utm_source=wp-sms&utm_medium=link&utm_campaign=onboarding'); ?>" target="_blank" title="<?php esc_attr_e('Learn more', 'wp-sms'); ?>">
                    <?php esc_html_e('Learn more', 'wp-sms'); ?>
                </a>
            </div>
            <div class="c-readycard">
                <svg width="28" height="28" viewBox="0 0 34 38" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M11.5061 22.3443L7.99382 34.4264C7.80111 35.0893 7.29336 35.6142 6.63721 35.8289C4.474 36.5365 2.64146 34.1035 3.9187 32.2197L12.1754 20.0416L11.5061 22.3443Z" fill="#F4DD45"/>
                    <path d="M12.1756 20.0416L25.8599 26.9017L22.2682 27.8715L11.4931 22.3403L12.1756 20.0416Z" fill="#EFAC08"/>
                    <path d="M24.3876 23.3819L28.2666 14.1512L23.8129 14.1872L21.7297 16.9528L24.3876 23.3819Z" fill="#FCC101"/>
                    <path d="M17.8504 7.36292L25.8599 26.9017L22.2682 25.1777L16.3419 10.7391L17.8504 7.36292Z" fill="#FCC101"/>
                    <path d="M31.1039 14.1512L31.8932 16.7864C32.3594 18.3428 30.5291 19.5625 29.2721 18.5331L31.1039 14.1512Z" fill="#FFE97B"/>
                    <path d="M12.1759 20.0416L16.3423 10.7391L5.24399 3.73535L12.1759 20.0416Z" fill="#FFE97B"/>
                    <path d="M16.3418 10.7391L22.2681 25.1777L12.1754 20.0416L16.3418 10.7391Z" fill="#F4DD45"/>
                    <path d="M28.2307 14.1512H31.104L25.8601 26.9017L24.3876 23.3459L28.2307 14.1512Z" fill="#EFAC08"/>
                    <path d="M16.3421 10.775L17.8506 7.36295L7.32917 1.86068C6.10135 1.21857 4.73561 2.44631 5.24378 3.73535L16.3421 10.775Z" fill="#F4DD45"/>
                    <path d="M11.493 22.3402L22.2681 27.8714L8.90701 31.3194L11.493 22.3402Z" fill="#FCC101"/>
                </svg>
                <h2 class="c-readycard__title"><?php esc_html_e('FeedbackBird', 'wp-sms'); ?></h2>
                <p class="c-readycard__desc">
                    <?php esc_html_e('Find everything you need to get WP-SMS up and running.', 'wp-sms'); ?>
                </p>
                <a class="c-btn" href="<?php echo esc_url('https://feedbackbird.io/?utm_source=wp-sms&utm_medium=link&utm_campaign=onboarding'); ?>" title="<?php esc_attr_e('Learn more', 'wp-sms'); ?>" target="_blank">
                    <?php esc_html_e('Learn more', 'wp-sms'); ?>
                </a>
            </div>
        </div>
    </div>

</form>