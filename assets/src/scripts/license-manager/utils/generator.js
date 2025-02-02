import {getElement, getString} from "./utilities";
import {sendGetRequest} from "./ajaxHelper";

const generateBadge = (type, content, size = "") => {
    const badge = document.createElement('span');
    badge.classList.add('wpsms_badge', `wpsms_badge--${type}`);
    console.log(size)
    if (size !== "") {
        console.log(size)
        badge.classList.add(`wpsms_badge--${size}`);
    }

    badge.textContent = content;
    return badge;
};

const processAddonDownload = (addonSlug, result) => {
    const addonCheckboxWrapper = getElement(`.wpsms-addon__download__item--select[data-addon-slug="${addonSlug}"]`)
    addonCheckboxWrapper.querySelector('span').innerHTML = "";
    if (result.data.success) {
        addonCheckboxWrapper.querySelector('span').appendChild(generateBadge('success', getString('success')))
    } else {
        addonCheckboxWrapper.querySelector('span').appendChild(generateBadge('danger', getString('failed')))
        addonCheckboxWrapper.querySelector('span').appendChild(generateRetryDownloadBtn(addonSlug, generateBadge('warning', getString('retry'), 'md')))
    }
}

const generateRetryDownloadBtn = (slug, children) => {
    // Create button element
    const button = document.createElement('button');
    button.setAttribute('type', 'button');
    button.setAttribute('data-slug', slug);
    button.classList.add('wpsms__btn--transparent'); // Add a class for styling if needed

    // Append the provided child element inside the button
    if (children instanceof HTMLElement) {
        button.appendChild(children);
    } else {
        button.textContent = children;
    }

    // Bind click event
    button.addEventListener('click', async () => {
        const addonCheckboxWrapper = getElement(`.wpsms-addon__download__item--select[data-addon-slug="${slug}"]`)
        addonCheckboxWrapper.querySelector('span').innerHTML = "";
        addonCheckboxWrapper.querySelector('span').appendChild(generateBadge('success', getString('downloading') + '...'))

        let params = {
            'sub_action': 'download_plugin',
            'plugin_slug': slug
        };

        try {
            const result = await sendGetRequest(params);

            if (result) {
                processAddonDownload(slug, result)
            }
        } catch (error) {

        } finally {

        }
    });

    return button;
}

const generateRetryActivateBtn = (slug, children) => {
    // Create button element
    const button = document.createElement('button');
    button.setAttribute('type', 'button');
    button.setAttribute('data-slug', slug);
    button.classList.add('wpsms__btn--transparent'); // Add a class for styling if needed

    // Append the provided child element inside the button
    if (children instanceof HTMLElement) {
        button.appendChild(children);
    } else {
        button.textContent = children;
    }

    // Bind click event
    button.addEventListener('click', async () => {
        const addonCheckboxWrapper = getElement(`.wpsms-addon__download__item--select[data-addon-slug="${slug}"]`)
        addonCheckboxWrapper.querySelector('span').innerHTML = "";
        addonCheckboxWrapper.querySelector('span').appendChild(generateBadge('success', getString('downloading') + '...'))

        let params = {
            'sub_action': 'activate_plugin',
            'plugin_slug': slug
        };

        try {
            const result = await sendGetRequest(params);

            if (result) {
                processAddonDownload(slug, result)
            }
        } catch (error) {

        }
    });

    return button;
}

export { generateBadge , generateRetryDownloadBtn, generateRetryActivateBtn};