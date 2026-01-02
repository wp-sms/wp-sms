import {getAjaxUrl} from "./utilities";

/**
 * Get the license nonce from wpsms_global
 */
const getLicenseNonce = () => {
    return wpsms_global.license_nonce || '';
}

/**
 * Get the license action prefix (e.g., 'wp_sms')
 */
const getLicensePrefix = () => {
    return wpsms_global.license_prefix || 'wp_sms';
}

/**
 * Map old sub_action names to new SDK action names
 */
const mapAction = (subAction) => {
    const prefix = getLicensePrefix();
    const actionMap = {
        'check_license': `${prefix}_activate_license`,
        'activate_license': `${prefix}_activate_license`,
        'deactivate_license': `${prefix}_deactivate_license`,
        'validate_license': `${prefix}_validate_license`,
        'get_license_status': `${prefix}_get_license_status`,
        'get_addons': `${prefix}_get_addons`,
        'refresh_licenses': `${prefix}_refresh_licenses`,
    };
    return actionMap[subAction] || `${prefix}_${subAction}`;
}

/**
 * Send AJAX request using SDK's action format
 */
const sendGetRequest = async (data, subAction) => {
    // Map sub_action to SDK action
    if (subAction) {
        data.action = mapAction(subAction);
    } else if (data.sub_action) {
        data.action = mapAction(data.sub_action);
        delete data.sub_action;
    }

    // Add nonce
    data.nonce = getLicenseNonce();

    return new Promise((resolve, reject) => {
        jQuery.ajax({
            url: getAjaxUrl(),
            type: 'POST',
            dataType: 'json',
            data: data,
            timeout: 30000,
            success: function (response) {
                resolve(response);
            },
            error: function (xhr, status, error) {
                // Try to parse response body for error details
                let errorResponse = {
                    success: false,
                    data: { message: error || 'Network error', code: 'ajax_error' }
                };

                try {
                    if (xhr.responseText) {
                        const parsed = JSON.parse(xhr.responseText);
                        if (parsed && typeof parsed === 'object') {
                            errorResponse = parsed;
                        }
                    }
                } catch (e) {
                    // Keep default error response
                }

                // Resolve with error response so UI can display the message
                resolve(errorResponse);
            }
        });
    });
}

const sendPostRequest = async (data, subAction) => {
    return sendGetRequest(data, subAction);
}

export {sendGetRequest, sendPostRequest, getLicenseNonce, getLicensePrefix};
