jQuery(document).ready(function () {
    const wpsNotificationButtons = document.querySelectorAll('.js-wpsms-open-notification');
    const wpsSidebar = document.querySelector('.wpsms-notification-sidebar');
    const wpsOverlay = document.querySelector('.wpsms-notification-sidebar__overlay');
    const body = document.body;
    const tabs = document.querySelectorAll('.wpsms-notification-sidebar__tab');
    const wpsCloseNotificationMenu = document.querySelector('.wpsms-notification-sidebar__close');
    const tabPanes = document.querySelectorAll('.wpsms-notification-sidebar__tab-pane');
    const dismissAllBtn = document.querySelector(".wpsms-notification-sidebar__dismiss-all");

    // Toggle notification menu
    if (tabs.length > 0 && tabPanes.length > 0) {
        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                tabs.forEach(function (t) {
                    t.classList.remove('wpsms-notification-sidebar__tab--active');
                });
                tabPanes.forEach(function (pane) {
                    pane.classList.remove('wpsms-notification-sidebar__tab-pane--active');
                });

                const targetTab = tab.getAttribute('data-tab');
                tab.classList.add('wpsms-notification-sidebar__tab--active');
                document.getElementById(targetTab).classList.add('wpsms-notification-sidebar__tab-pane--active');
            });
        });
    }

    if (wpsNotificationButtons.length > 0 && wpsSidebar && wpsOverlay) {
        const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
        document.documentElement.style.setProperty('--scrollbar-width', `${scrollbarWidth}px`);

        wpsNotificationButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                wpsSidebar.classList.toggle('is-active');
                wpsOverlay.classList.toggle('is-active');
                setTimeout(() => {
                    body.classList.toggle('wpsms-no-scroll');
                }, 250);
             });
        });

        wpsOverlay.addEventListener('click', function () {
            wpsSidebar.classList.remove('is-active');
            wpsOverlay.classList.remove('is-active');
            setTimeout(() => {
                body.classList.remove('wpsms-no-scroll');
            }, 250);
        });
        if (wpsCloseNotificationMenu) {
            wpsCloseNotificationMenu.addEventListener('click', function () {
                wpsSidebar.classList.remove('is-active');
                wpsOverlay.classList.remove('is-active');
                body.classList.remove('wpsms-no-scroll');
            });
        }

    }

    const updateDismissAllVisibility = () => {
        const activeTab = document.querySelector(".wpsms-notification-sidebar__tab--active");
        if (!activeTab) {
            return;
        }

        if (activeTab.dataset.tab === "tab-2") {
            if(dismissAllBtn) dismissAllBtn.style.display = "none";
        } else {
            const activeCards = document.querySelectorAll(
                ".wpsms-notification-sidebar__cards--active .wpsms-notification-sidebar__card:not(.wpsms-notification-sidebar__no-card)"
            );
            const hasNotifications = activeCards.length > 0;
            if(dismissAllBtn) dismissAllBtn.style.display = hasNotifications ? "inline-flex" : "none";
        }
    };

    const checkEmptyNotifications = () => {
        let activeCards = jQuery('.wpsms-notification-sidebar__tab-pane--active .wpsms-notification-sidebar__card:not(.wpsms-notification-sidebar__no-card)');
        let noCardMessages = jQuery('.wpsms-notification-sidebar__tab-pane--active .wpsms-notification-sidebar__no-card');
        let noCardMessage = noCardMessages.first();
        if (activeCards.length === 0) {
            noCardMessage.css('display', 'flex');
        } else {
            noCardMessage.hide();
        }
        if (noCardMessages.length > 1) {
            noCardMessages.last().hide();
        }
    }

    tabs.forEach(tab => {
        tab.addEventListener("click", function () {
            tabs.forEach(t => t.classList.remove("wpsms-notification-sidebar__tab--active"));
            this.classList.add("wpsms-notification-sidebar__tab--active");
            updateDismissAllVisibility();
            checkEmptyNotifications();
        });
    });

    updateDismissAllVisibility();
    checkEmptyNotifications();

    jQuery(document).on('click', "a.wpsms-notification-sidebar__dismiss, a.wpsms-notification-sidebar__dismiss-all", function (e) {
        e.preventDefault();
        let $this = jQuery(this);
        let notificationId = '';

        if ($this.hasClass('wpsms-notification-sidebar__dismiss')) {
            notificationId = $this.data('notification-id');
        }

        if ($this.hasClass('wpsms-notification-sidebar__dismiss-all')) {
            notificationId = 'all';
        }


        if (notificationId === 'all') {
            jQuery('.wpsms-notification-sidebar__cards--active .wpsms-notification-sidebar__card:not(.wpsms-notification-sidebar__no-card)').each(function () {
                let $card = jQuery(this);

                jQuery('.wpsms-notification-sidebar__cards--dismissed').prepend($card.clone().hide().fadeIn(300));

                $card.fadeOut(300, function () {
                    jQuery(this).remove();
                    checkEmptyNotifications();
                });
            });
        } else {
            let $card = $this.closest('.wpsms-notification-sidebar__card');

            jQuery('.wpsms-notification-sidebar__cards--dismissed').prepend($card.clone().hide().fadeIn(300));

            $card.fadeOut(300, function () {
                jQuery(this).remove();
                checkEmptyNotifications();
            });

        }
        updateDismissAllVisibility();

        jQuery('.wpsms-notification-sidebar__cards--dismissed .wpsms-notification-sidebar__no-card').remove();

        let params = {
            'wps_nonce': wpsms_global.rest_api_nonce,
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

    jQuery(document).on('click', "a.wpsms-notifications--has-items", function (e) {
        e.preventDefault();

        let $this = jQuery(this);

        $this.removeClass('wpsms-notifications--has-items');

        let params = {
            'wps_nonce': wpsms_global.rest_api_nonce,
            'action': 'wp_sms_update_notifications_status',
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