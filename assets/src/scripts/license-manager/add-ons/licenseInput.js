import {addClass, getElement, getElements, getString, removeClass} from "../utils/utilities";
import {sendGetRequest} from "../utils/ajaxHelper";
import {generateBadge, generateRetryDownloadBtn} from "../utils/generator";

const initLicenseInput = () => {
    const licenseButtons = getElements('.js-wpsms-addon-license-button');

    if (licenseButtons.length === 0) {
        return;
    }

    licenseButtons.map( (button) => {
        button.addEventListener('click', function (event) {
            event.stopPropagation();
            const isActive = this.classList.contains('active');
            document.querySelectorAll('.js-wpsms-addon-license-button').forEach(function (otherButton) {
                otherButton.classList.remove('active');
                otherButton.closest('.wpsms-postbox-addon__item').classList.remove('active');
            });
            if (!isActive) {
                this.classList.add('active');
                const closestItem = this.closest('.wpsms-postbox-addon__item');
                if (closestItem) {
                    closestItem.classList.add('active');
                    let active_input = closestItem.querySelector('.wpsms-addon__item__update_license input');
                    let active_button = closestItem.querySelector('.wpsms-addon__item__update_license button');
                    if (active_input && active_button) {
                        function toggleButtonState() {
                            active_input.classList.remove('wps-danger', 'wps-warning');
                            if (active_input.value.trim() === '') {
                                active_button.classList.add('disabled');
                                active_button.disabled = true;
                            } else {
                                active_button.classList.remove('disabled');
                                active_button.disabled = false;
                            }
                        }

                        // Initial check when the page loads
                        toggleButtonState();

                        // Listen for input event to enable button when typing
                        active_input.addEventListener('input', function () {
                            toggleAlertBox(active_input);
                            toggleButtonState();
                        });

                    }
                    if (active_button) {
                        active_button.addEventListener('click', async (event) => {
                            toggleAlertBox(active_button);
                            setLoadingState(active_button)
                            event.stopPropagation();
                            // Get and trim the license key input value
                            const license_key = active_input.value.trim();
                            const addon_slug = active_input.dataset.addonSlug;

                            if (license_key && addon_slug) {
                                const active_params = {
                                    'license_key': license_key,
                                    'addon_slug': addon_slug,
                                    'sub_action': 'check_license'
                                }
                                const result = await sendGetRequest(active_params);

                                if (result) {
                                    requestResult(result, active_button)
                                    setDoneState(active_button)
                                }
                            }
                        });
                    }


                }
            }
        });

    });

    const toggleAlertBox = (btn) => {
        const existingAlertDiv = btn.parentElement.parentElement.querySelector('.wpsms-alert');
        if (existingAlertDiv) {
            existingAlertDiv.remove();
        }
    }

    const setLoadingState = (button) => {
        addClass(button, 'wpsms-loading-button')
        button.textContent = ""
    }

    const setDoneState = (button) => {
        removeClass(button, 'wpsms-loading-button')
        button.textContent = getString('update_license')
    }

    const requestResult = (data, button) => { console.log(data)
        const alertDiv = document.createElement('div');
        if (data.success === true) {
            if (data?.data?.message?.toLowerCase().includes('domain')) {
                button.parentElement.querySelector('input').classList.add('wps-warning');
                alertDiv.classList.add('wps-alert', 'wps-alert--warning');
            } else {
                button.parentElement.querySelector('input').classList.add('wpsms-success');
                alertDiv.classList.add('wpsms-alert', 'wpsms-alert--success');
            }
        } else {
            button.parentElement.querySelector('input').classList.add('wpsms-danger');
            alertDiv.classList.add('wpsms-alert', 'wpsms-alert--danger');
        }
        alertDiv.innerHTML = `
                                <span class="icon"></span>
                                <div>
                                    <p>${data?.data?.message}</p>
                                </div>
                                `;
        let activeLicenseDiv;
        if (getElement('.wpsms-addon__step__active-license')) {
            activeLicenseDiv = document.querySelector('.wpsms-addon__step__active-license');
        } else {
            activeLicenseDiv = button.parentElement;
        }

        if (activeLicenseDiv) {
            activeLicenseDiv.parentNode.insertBefore(alertDiv, activeLicenseDiv.nextSibling);
        }
    }
}

export {initLicenseInput}