import {addClass, getElement, getElements, getString, removeClass} from "../utils/utilities";
import {generateBadge} from "../utils/generator";
import {sendGetRequest} from "../utils/ajaxHelper";
import wpsms_js from "../../../../../../../../wp-includes/js/dist/vendor/lodash";

const initStepThree = () => {
    const activateButtons = getElements('.js-addon-active-plugin-btn')
    const activateAllAddons = getElement('.js-addon_active-all')
    const toggleActiveAll = () => {
        const addonWrappers = document.querySelectorAll('.wpsms-addon__download__item--actions--activation');
        const hasPendingAddons = Array.from(addonWrappers).some(wrapper => {
            const hasPostboxSuccess = wrapper.querySelector('.wpsms-postbox-addon__status--success');
            const hasBadgeSuccess = wrapper.querySelector('.wpsms_badge--success');
            return !(hasPostboxSuccess || hasBadgeSuccess);
        });
        if (activateAllAddons) {
            if (hasPendingAddons) {
                removeClass(activateAllAddons, 'wpsms-hide');
            } else {
                addClass(activateAllAddons, 'wpsms-hide');
            }
        }
    };
    toggleActiveAll();

    if (activateAllAddons) {
        activateAllAddons.addEventListener('click', async (e) => {
            e.stopPropagation();
            addClass(activateAllAddons, 'wpsms-loading-button');
            const addonWrappers = getElements('.wpsms-addon__download__item--actions--activation');
            const slugs = Array.from(addonWrappers)
                .filter(wrapper => {
                     return !wrapper.querySelector('.wpsms-postbox-addon__buttons .wpsms_badge--success') &&
                        !wrapper.querySelector('.wpsms-postbox-addon__status.wpsms-postbox-addon__status--success');
                })
                .map(wrapper => wrapper.getAttribute('data-addon-slug'));
            addonWrappers.forEach(wrapper => {
                const slug = wrapper.getAttribute('data-addon-slug');
                if (slugs.includes(slug)) {
                    const alertsWrapperElement = wrapper.parentElement.querySelector('.wpsms-addon__download__item__info__alerts');
                    toggleAlertBox(alertsWrapperElement);
                    const buttonsWrapper = wrapper.querySelector('.wpsms-postbox-addon__buttons');
                    if (slugs.indexOf(slug) > 0) {
                        const statusSpan = document.createElement('span');
                        statusSpan.classList.add('wpsms-postbox-addon__status', 'wpsms-postbox-addon__status--waiting');
                        statusSpan.textContent = getString('waiting');
                        wrapper.insertBefore(statusSpan, wrapper.firstChild);
                        addClass(buttonsWrapper, 'wps-hide');
                    }
                }
            });
            for (let index = 0; index < slugs.length; index++) {
                const slug = slugs[index];
                const wrapper = getElement(`.wpsms-addon__download__item--actions--activation[data-addon-slug="${slug}"]`);
                const alertsWrapperElement = wrapper.parentElement.querySelector('.wpsms-addon__download__item__info__alerts');
                const buttonsWrapper = wrapper.querySelector('.wpsms-postbox-addon__buttons');

                const waitingStatus = wrapper.querySelector('.wpsms-postbox-addon__status--waiting');
                if (waitingStatus) waitingStatus.remove();
                removeClass(buttonsWrapper, 'wps-hide');
                buttonsWrapper.innerHTML = "";
                buttonsWrapper.appendChild(generateBadge('is-activating', getString('activating') + '...'));

                let params = {
                    'sub_action': 'activate_plugin',
                    'plugin_slug': slug
                };

                const result = await sendGetRequest(params);
                if (result) {
                    processAddonActivation(slug, result);
                    requestResult(result, alertsWrapperElement);
                }
            }

            removeClass(activateAllAddons, 'wpsms-loading-button');
            activateAllAddons.textContent = getString('activate_all');
            toggleActiveAll();
        })
    }

    if (activateButtons && activateButtons.length) {
        activateButtons.map(button => {
        button.addEventListener('click', async (e) => {
            e.preventDefault();
            button.classList.add('is-activating');
            button.innerHTML = getString('activating')+ '...';
            const alertsWrapperElement = button.parentElement.parentElement.parentElement.querySelector('.wpsms-addon__download__item__info__alerts')
            const slug = button.getAttribute('data-slug');
            let params = {
                'sub_action': 'activate_plugin',
                'plugin_slug': slug
            };

            toggleAlertBox(alertsWrapperElement)
            const result = await sendGetRequest(params);

            if (result) {
                if (!getElement('.wpsms-install-addon-btn')) {
                    processAddonActivation(slug, result)
                    requestResult(result, alertsWrapperElement)
                } else {
                    const addonActivateBtnInList = getElement(`.js-addon-active-plugin-btn[data-slug="${slug}"]`)
                    const addOnAlertWrapper = addonActivateBtnInList.parentElement.parentElement.parentElement.parentElement.querySelector('.js-wpsms-addon-alert-wrapper')
                    addOnAlertWrapper.innerHTML = ""
                    requestResult(result, addOnAlertWrapper)
                }
               if (getElement('.js-addon-statuses-wrapper')) {
                    if (result.success === true) {
                        processAddonActivation(slug, result)
                     } else {
                        button.innerHTML = getString('active')
                    }
               }
            }
            setTimeout(toggleActiveAll, 0);
        })
    })
    }

    const processAddonActivation = (addonSlug, result) => {
        const addonButtonsWrapper = getElement(`.wpsms-addon__download__item--actions[data-addon-slug="${addonSlug}"] .wpsms-postbox-addon__buttons`)
        if (result.success) {
            addonButtonsWrapper.innerHTML = "";
            addonButtonsWrapper.appendChild(generateBadge('success', getString('activated')))
        } else {
            const errorBadge = addonButtonsWrapper.querySelector('.wpsms_badge.wpsms_badge--danger')
            if (errorBadge) {
                errorBadge.remove()
            }
            addonButtonsWrapper.appendChild(generateBadge('danger', getString('failed')))
        }
    }

    const toggleAlertBox = (alertsWrapperElement) => {
        if (alertsWrapperElement) {
            alertsWrapperElement.innerHTML = "";
        }
    }

    const requestResult = (data, alertsWrapper) => {
        const alertDiv = document.createElement('div');
        if (data.success === true) {
            alertDiv.classList.add('wpsms-alert', 'wpsms-alert--success');
        } else {
            alertDiv.classList.add('wpsms-alert', 'wpsms-alert--danger');
        }
        alertDiv.innerHTML = `
                                <span class="icon"></span>
                                <div>
                                    <p>${data?.data?.message}</p>
                                </div>
                                `;

        alertsWrapper.appendChild(alertDiv);
    }
}

export default initStepThree