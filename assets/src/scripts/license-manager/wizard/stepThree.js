import {getElement, getElements, getString} from "../utils/utilities";
import {generateBadge} from "../utils/generator";
import {sendGetRequest} from "../utils/ajaxHelper";

const initStepThree = () => {
    const activateButtons = getElements('.js-addon-active-plugin-btn')
    const activateAllAddons = getElement('.js-addon_active-all')

    if (activateAllAddons) {
        activateAllAddons.addEventListener('click', (e) => {
            const addonWrappers = getElements('.wpsms-addon__download__item--actions--activation')
            addonWrappers.map(async (wrapper) => {
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
            })
        })
    }

    if (activateButtons) {
        activateButtons.map(button => {
        button.addEventListener('click', async (e) => {
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