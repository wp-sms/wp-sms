import {addClass, removeClass, getElement, checkInputSize, getNonce, getString} from "../utils/utilities";
import {sendGetRequest} from "../utils/ajaxHelper";
import {toggleAlertBox} from "../utils/errorHandler";

const wizardStepOne = () => {
    const licenseInput = getElement('.wpsms-addon__step__active-license input')
    const submitButton = getElement('.js-addon-active-license')

    if (!licenseInput || !submitButton) {
        return;
    }

    licenseInput.addEventListener('input', (e) => {
        toggleAlertBox(submitButton)
        if (checkInputSize(e)) {
            removeClass(submitButton, 'disabled')
        } else {
            addClass(submitButton, 'disabled')
        }
    })

    submitButton.addEventListener('click', async function (event) {
        event.stopPropagation();
        // Get and trim the license key input value
        const license_key = licenseInput.value.trim();
        const params = {
            'license_key': license_key,
            'sub_action': 'check_license'
        }
        if (license_key) {
            const result = await sendGetRequest(params, 'check_license');
            toggleAlertBox(submitButton)
            requestResult(result, submitButton)
            if (result.success) {
                submitButton.classList.add('redirecting');
                submitButton.textContent = getString('redirecting');
                window.location.href = `admin.php?page=wp-sms-add-ons-1&tab=downloads&license_key=${params.license_key}`;
            }
        } else {
            addClass(submitButton, 'disabled')
        }
    });
}

const requestResult = (data, button) => {
    const alertDiv = document.createElement('div');
    if (data.success === true) {
        button.parentElement.querySelector('input').classList.add('wpsms-warning');
        alertDiv.classList.add('wpsms-alert', 'wpsms-alert--warning');
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

export default wizardStepOne