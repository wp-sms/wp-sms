const getNonce = () => {
    return wpsms_global.rest_api_nonce
}

const getAdminUrl = () => {
    return wpsms_global.admin_url
}

const getString = (key) => {
    return (key in wpsms_global.i18n ? wpsms_global.i18n[key] : '');
}

const getAjaxUrl = () => {
    return wpsms_global.ajax_url
}

const getLicenseKey = () => {
    return wpsms_global.license_key
}

const getElements = (selector) => {
    return Array.from(document.querySelectorAll(selector))
}

const getElement = (selector) => {
    return document.querySelector(selector)
}

const addClass = (element, classname) => {
    element.classList.add(classname)
}

const removeClass = (element, classname) => {
    if (Array.isArray(element)) {
        element.map(el => removeClass(el, classname))
        return;
    }

    element.classList.remove(classname)
}

const toggleClass = (element, classname) => {
    element.classList.toggle(classname)
}

const getInputValue = (element) => {
    return element.getAttribute('value');
}

const checkInputSize = (e) => {
    return e.target.value.length > 1;
}

export {getElements, getElement, addClass, removeClass, toggleClass, checkInputSize, getNonce, getAdminUrl, getAjaxUrl, getString, getLicenseKey}
