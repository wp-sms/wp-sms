const errorHandeler = (params, button, data) => {
    if (params.action === "wp_sms_check_license") {
        toggleAlertBox(button);
        const alertDiv = document.createElement('div');
        if (data?.data?.message?.toLowerCase().includes('domain')) {
            button.parentElement.querySelector('input').classList.add('wps-warning');
            alertDiv.classList.add('wpsms-alert', 'wpsms-alert--warning');
        } else {
            button.parentElement.querySelector('input').classList.add('wps-danger');
            alertDiv.classList.add('wpsms-alert', 'wpsms-alert--danger');
        }
        alertDiv.innerHTML = `
                                <span class="icon"></span>
                                <div>
                                    <p>${data?.data?.message}</p>
                                </div>
                                `;
        let activeLicenseDiv;
        if (params.tab) {
            activeLicenseDiv = document.querySelector('.wpsms-addon__step__active-license');
        } else {
            activeLicenseDiv = button.parentElement;
        }

        if (activeLicenseDiv) {
            activeLicenseDiv.parentNode.insertBefore(alertDiv, activeLicenseDiv.nextSibling);
        }
    }
    if (params.action === "wp_sms_download_plugin") {
        const current_plugin_checkbox = document.querySelector(`[data-slug="${params.plugin_slug}"]`);
        if (current_plugin_checkbox) {
            const downloadingStatus = current_plugin_checkbox.parentElement.parentElement.querySelector('.wpsms-postbox-addon__status');
            if (downloadingStatus) {
                downloadingStatus.remove();
            }
            const statusSpan = document.createElement('span');
            statusSpan.classList.add('wps-postbox-addon__status', 'wps-postbox-addon__status--danger');
            statusSpan.textContent = wpsms_js._('failed');
            current_plugin_checkbox.parentElement.parentElement.insertBefore(statusSpan, current_plugin_checkbox.parentElement.parentElement.firstChild);

            if (params.tab === 'get-started') {
                const retryBtn = document.createElement('a');
                retryBtn.classList.add('wps-postbox-addon__button', 'button-retry-addon-download', 'js-addon-retry-btn');
                retryBtn.textContent = wpsms_js._('retry');
                retryBtn.setAttribute('data-slug', params.plugin_slug);
                current_plugin_checkbox.parentElement.parentElement.insertBefore(retryBtn, statusSpan.nextSibling);
            }
        }
    }
    if (params.action === "wp_sms_activate_plugin") {
        const current_plugin = document.querySelector(`[data-slug="${params.plugin_slug}"]`);
        if (current_plugin) {
            const loadingStatus = current_plugin.parentElement.parentElement.querySelector('.wpsms-postbox-addon__status');
            if (loadingStatus) {
                loadingStatus.remove();
            }
            const statusSpan = document.createElement('span');
            statusSpan.classList.add('wps-postbox-addon__status', 'wps-postbox-addon__status--danger');
            statusSpan.textContent = wpsms_js._('failed');
            current_plugin.parentElement.parentElement.insertBefore(statusSpan, current_plugin.parentElement.parentElement.firstChild);
            current_plugin.style.display = 'flex';
        }
    }
}

const toggleAlertBox = (btn) => {
    const existingAlertDiv = btn.parentElement.parentElement.querySelector('.wpsms-alert');
    if (existingAlertDiv) {
        existingAlertDiv.remove();
    }
}

export {errorHandeler, toggleAlertBox};