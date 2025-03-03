import {addClass, getElement, getElements, getLicenseKey, getString, removeClass} from "../utils/utilities";
import {sendGetRequest} from "../utils/ajaxHelper";
import {generateBadge, generateRetryDownloadBtn} from "../utils/generator";

const initStepTwo = () => {
    const selectAllCheckboxes = getElement('.js-wpsms-addon-select-all')
    const addOneCheckboxes = getElements('.js-wpsms-addon-check-box')
    const submitStepTwo = getElement('.js-addon-download-button')
    let selectedSlugs = []
    let allAddonsDownloaded = addOneCheckboxes.length;

    if (!submitStepTwo) {
        return;
    }

    if (selectAllCheckboxes) {
        selectAllCheckboxes.addEventListener('click', () => {
            addOneCheckboxes.map(checkbox => checkbox.checked = true)
            updateDownloadButtonState()
        })
    }

    addOneCheckboxes.map(checkbox => {
        checkbox.addEventListener('change', () => updateDownloadButtonState());
    });

    submitStepTwo.addEventListener('click', async () => {
        let submitButtonLabel = submitStepTwo.textContent

        addClass(submitStepTwo, 'wpsms-loading-button')
        submitStepTwo.textContent = ""

        for (const slug of selectedSlugs) {
            let params = {
                'sub_action': 'download_plugin',
                'plugin_slug': slug
            };

            const addonCheckboxWrapper = getElement(`.wpsms-addon__download__item--select[data-addon-slug="${slug}"]`)
            const downloadedAddonCheckbox = addonCheckboxWrapper.querySelector('input[type="checkbox"]')

            if (downloadedAddonCheckbox) {
                downloadedAddonCheckbox.remove()
                addonCheckboxWrapper.querySelector('span').appendChild(generateBadge('success', getString('downloading') + '...'))

                const result = await sendGetRequest(params);
                if (result) {
                    allAddonsDownloaded--;
                    processAddonDownload(slug, result)
                }
            }
        }
    })

    const updateDownloadButtonState = () => {
        let anyChecked = false;
        addOneCheckboxes.map(checkbox => {
            if (checkbox.checked === true) {
                anyChecked = true;
            }
        })

        if (anyChecked) {
            submitStepTwo.classList.remove('disabled');
        } else {
            submitStepTwo.classList.add('disabled');
        }
        updateSelectedSlugs();
    }

    const updateSelectedSlugs = () => {
        selectedSlugs = [];
        addOneCheckboxes.map(checkbox => {
            if (checkbox.checked) {
                selectedSlugs.push(checkbox.getAttribute('data-slug'));
            }
        });
        console.log(selectedSlugs);

    }

    const processAddonDownload = (addonSlug, result) => {
        const addonCheckboxWrapper = getElement(`.wpsms-addon__download__item--select[data-addon-slug="${addonSlug}"]`)
        addonCheckboxWrapper.querySelector('span').innerHTML = "";
        if (result.success) {
            addonCheckboxWrapper.querySelector('span').appendChild(generateBadge('success', getString('installed')))
            if (allAddonsDownloaded === 0) {
                const licenseKey = getLicenseKey()
                submitStepTwo.classList.add('redirecting');
                submitStepTwo.textContent = getString('redirecting');
                window.location.href = `admin.php?page=wp-sms-add-ons&tab=get-started&license_key=${licenseKey}`;
                removeClass(submitStepTwo, 'wpsms-loading-button')
                submitStepTwo.textContent = ""
            }
        } else {
            addonCheckboxWrapper.querySelector('span').appendChild(generateBadge('danger', getString('failed')))
            addonCheckboxWrapper.querySelector('span').appendChild(generateRetryDownloadBtn(addonSlug, generateBadge('warning', getString('retry'), 'md')))
        }
    }
}

export default initStepTwo