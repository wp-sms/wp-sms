jQuery(document).ready(function () {
    const wpsmsNotificationButtons = document.querySelectorAll('.js-wp-sms-open-notification');
    const wpsmsSidebar = document.querySelector('.wp-sms-notification-sidebar');
    const wpsmsOverlay = document.querySelector('.wp-sms-notification-sidebar__overlay');
    const body = document.body;
    const tabs = document.querySelectorAll('.wp-sms-notification-sidebar__tab');
    const wpsmsCloseNotificationMenu = document.querySelector('.wp-sms-notification-sidebar__close');
    const tabPanes = document.querySelectorAll('.wp-sms-notification-sidebar__tab-pane');
    const dismissAllBtn = document.querySelector(".wp-sms-notification-sidebar__dismiss-all");

    // Toggle notification menu
    if (tabs.length > 0 && tabPanes.length > 0) {
        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                tabs.forEach(function (t) {
                    t.classList.remove('wp-sms-notification-sidebar__tab--active');
                });
                tabPanes.forEach(function (pane) {
                    pane.classList.remove('wp-sms-notification-sidebar__tab-pane--active');
                });

                const targetTab = tab.getAttribute('data-tab');
                tab.classList.add('wp-sms-notification-sidebar__tab--active');
                document.getElementById(targetTab).classList.add('wp-sms-notification-sidebar__tab-pane--active');
            });
        });
    }

    if (wpsmsNotificationButtons.length > 0 && wpsmsSidebar && wpsmsOverlay) {
        const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
        document.documentElement.style.setProperty('--scrollbar-width', `${scrollbarWidth}px`);

        wpsmsNotificationButtons.forEach(function (button) {
            button.addEventListener('click', function (e) {
                e.preventDefault();
                wpsmsSidebar.classList.toggle('is-active');
                wpsmsOverlay.classList.toggle('is-active');
                setTimeout(() => {
                    body.classList.toggle('wp-sms-no-scroll');
                }, 250);
            });
        });

        wpsmsOverlay.addEventListener('click', function () {
            wpsmsSidebar.classList.remove('is-active');
            wpsmsOverlay.classList.remove('is-active');
            setTimeout(() => {
                body.classList.remove('wp-sms-no-scroll');
            }, 250);
        });
        if (wpsmsCloseNotificationMenu) {
            wpsmsCloseNotificationMenu.addEventListener('click', function () {
                wpsmsSidebar.classList.remove('is-active');
                wpsmsOverlay.classList.remove('is-active');
                body.classList.remove('wp-sms-no-scroll');
            });
        }

    }

    const updateDismissAllVisibility = () => {
        const activeTab = document.querySelector(".wp-sms-notification-sidebar__tab--active");
        if (!activeTab) {
            return;
        }

        if (activeTab.dataset.tab === "tab-2") {
            if (dismissAllBtn) dismissAllBtn.style.display = "none";
        } else {
            const activeCards = document.querySelectorAll(
                ".wp-sms-notification-sidebar__cards--active .wp-sms-notification-sidebar__card:not(.wp-sms-notification-sidebar__no-card)"
            );
            const hasNotifications = activeCards.length > 0;
            if (dismissAllBtn) dismissAllBtn.style.display = hasNotifications ? "inline-flex" : "none";
        }
    };

    const checkEmptyNotifications = () => {
        let notificationsHasItems = jQuery('.wp-sms-notifications--has-items');
        let helpNotification = jQuery('.wp-sms-help__notification');
        let activeCards = jQuery('.wp-sms-notification-sidebar__tab-pane--active .wp-sms-notification-sidebar__card:not(.wp-sms-notification-sidebar__no-card)');
        let noCardMessages = jQuery('.wp-sms-notification-sidebar__tab-pane--active .wp-sms-notification-sidebar__no-card');
        let noCardMessage = noCardMessages.first();
        if (activeCards.length === 0) {
            noCardMessage.css('display', 'flex');
            helpNotification.hide();
            notificationsHasItems.removeClass('wp-sms-notifications--has-items');
        } else {
            noCardMessage.hide();
        }
        if (noCardMessages.length > 1) {
            noCardMessages.last().hide();
        }
    }

    tabs.forEach(tab => {
        tab.addEventListener("click", function () {
            tabs.forEach(t => t.classList.remove("wp-sms-notification-sidebar__tab--active"));
            this.classList.add("wp-sms-notification-sidebar__tab--active");
            updateDismissAllVisibility();
            checkEmptyNotifications();
        });
    });

    updateDismissAllVisibility();
    checkEmptyNotifications();

    jQuery(document).on('click', "a.wp-sms-notification-sidebar__dismiss, a.wp-sms-notification-sidebar__dismiss-all", function (e) {
        e.preventDefault();
        let $this = jQuery(this);
        let notificationId = '';

        if ($this.hasClass('wp-sms-notification-sidebar__dismiss')) {
            notificationId = $this.data('notification-id');
        }

        if ($this.hasClass('wp-sms-notification-sidebar__dismiss-all')) {
            notificationId = 'all';
        }


        if (notificationId === 'all') {
            jQuery('.wp-sms-notification-sidebar__cards--active .wp-sms-notification-sidebar__card:not(.wp-sms-notification-sidebar__no-card)').each(function () {
                let $card = jQuery(this);

                jQuery('.wp-sms-notification-sidebar__cards--dismissed').prepend($card.clone().hide().fadeIn(300));

                $card.fadeOut(300, function () {
                    jQuery(this).remove();
                    checkEmptyNotifications();
                });
            });
        } else {
            let $card = $this.closest('.wp-sms-notification-sidebar__card');

            jQuery('.wp-sms-notification-sidebar__cards--dismissed').prepend($card.clone().hide().fadeIn(300));

            $card.fadeOut(300, function () {
                jQuery(this).remove();
                checkEmptyNotifications();
            });

        }
        updateDismissAllVisibility();

        jQuery('.wp-sms-notification-sidebar__cards--dismissed .wp-sms-notification-sidebar__no-card').remove();

        let params = {
            'wpsms_nonce': wpsms_global.rest_api_nonce,
            'action': 'wp_sms_dismiss_notification',
            'notification_id': notificationId
        }

        jQuery.ajax({
            url: wpsms_global.admin_url + 'admin-ajax.php',
            type: 'GET',
            dataType: 'json',
            data: params,
            timeout: 30000,
            success: function ({data, success}) {
                if (!success) {
                    console.log(data);
                }
            },
            error: function (xhr, status, error) {
                console.log(error);
            }
        });
    });
});