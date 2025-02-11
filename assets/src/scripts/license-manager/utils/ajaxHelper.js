import {getAjaxUrl} from "./utilities";

const sendGetRequest = async (data, subAction) => {
    if (!data.action) {
        data.action = 'wp_sms_license_manager'
    }

    if (subAction) {
        data.sub_action = subAction
    }

    return new Promise((resolve, reject) => {
        jQuery.ajax({
            url: getAjaxUrl(),
            type: 'GET',
            dataType: 'json',
            data: data,
            timeout: 30000,
            success: function (data) {
                if (data.success) {
                    resolve(data);  // Resolve the Promise with the data
                } else {
                    resolve(data);  // Resolve with data, even if not a success
                }
            },
            error: function (xhr, status, error) {
                reject(error);  // Reject the Promise in case of an error
            }
        });
    });
}

const sendPostRequest = (data, subAction) => {
    console.log('Init');
}

export {sendGetRequest, sendPostRequest};