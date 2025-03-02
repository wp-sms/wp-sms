import {getElement, getElements, getString} from "../utils/utilities";
import {generateBadge} from "../utils/generator";
import {sendGetRequest} from "../utils/ajaxHelper";
import wpsms_js from "../../../../../../../../wp-includes/js/dist/vendor/lodash";

const initStepThree = () => {
    const activateButtons = getElements('.js-addon-active-plugin-btn')
    const activateAllAddons = getElement('.js-addon_active-all')

    if (activateAllAddons) {
        activateAllAddons.addEventListener('click', async (e) => {
            const addonWrappers = getElements('.wpsms-addon__download__item--actions--activation')
            for (const wrapper of addonWrappers) {
                if (wrapper.querySelector('.wpsms-postbox-addon__buttons .wpsms_badge--success')) {
                    return
                }
                const slug = wrapper.getAttribute('data-addon-slug');
                const alertsWrapperElement = wrapper.parentElement.querySelector('.wpsms-addon__download__item__info__alerts')

                let params = {
                    'sub_action': 'activate_plugin',
                    'plugin_slug': slug
                };

                toggleAlertBox(alertsWrapperElement)
                const result = await sendGetRequest(params);

                if (result) {
                    processAddonActivation(slug, result)
                    requestResult(result, alertsWrapperElement)
                }
            }
        })
    }

    if (activateButtons) {
        activateButtons.map(button => {
        button.addEventListener('click', async (e) => {
            button.classList.add('is-activating');

            if (getElement('.wpsms-postbox-addon__item__statuses')) {
                button.innerHTML = getString('activating')
            }
            e.preventDefault();
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
                        const addonSlug = button.parentElement.parentElement.getAttribute('data-addon-slug')

                        button.remove()
                        const statusesWrapper = getElement(`.wpsms-postbox-addon__item--actions[data-addon-slug=${addonSlug}]`)
                        statusesWrapper.querySelector('.js-addon-statuses-wrapper').innerHTML = ""

                        const statusElement = document.createElement('span');

                        statusElement.classList.add(
                            'wpsms-postbox-addon__status',
                            'wpsms-postbox-addon__status--success',
                            'js-wpsms-addon-status-success'
                        );

                        statusElement.innerText = getString('activated')
                        statusesWrapper.querySelector('.js-addon-statuses-wrapper').appendChild(statusElement)

                    } else {
                        button.innerHTML = getString('active')
                    }
                }
            }
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