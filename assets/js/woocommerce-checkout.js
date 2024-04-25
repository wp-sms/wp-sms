// Wait for the full page to load, including styles and images
document.addEventListener('DOMContentLoaded', function() {
    // Define the HTML structure as a string
    setTimeout(function () {
        const wpSmsOptinCheckbox = `
        <div class="wc-block-components-checkbox">
            <label for="wpsms_woocommerce_order_notification_field">
                <input name="wpsms_woocommerce_order_notification" id="wpsms_woocommerce_order_notification_field" class="wc-block-components-checkbox__input" value="1" type="checkbox" aria-invalid="false"/>
                <svg class="wc-block-components-checkbox__mark" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 20">
                    <path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"></path>
                </svg>
                <span class="wc-block-components-checkbox__label">
                    I would like to get notification about any change in my order via SMS.
                    <span class="optional">(optional)</span>
                </span>
            </label>
        </div>
    `;

        const checkoutForm = document.querySelector('.woocommerce-checkout');
        const checkoutBlock = document.querySelector('.wc-block-checkout');

        if (checkoutForm && checkoutBlock) {
            let placeToInsert = checkoutForm.querySelector('.wc-block-checkout__order-notes');

            if (placeToInsert) {
                placeToInsert.insertAdjacentHTML('afterend', wpSmsOptinCheckbox);
            } else {
                // As a fallback, append directly to the form if the specific target isn't found
                checkoutForm.insertAdjacentHTML('beforeend', wpSmsOptinCheckbox);
            }

            const smsCheckbox = document.getElementById('wpsms_woocommerce_order_notification_field');
            if (smsCheckbox) {
                smsCheckbox.addEventListener('change', function() {
                    window.wc_order_attribution.fields.wpsms_woocommerce_order_notification = smsCheckbox.checked;
                });
            }
        }
    }, 3000)

    if (typeof window.wc_order_attribution !== 'undefined') {
        // Add a new field or modify existing fields
        window.wc_order_attribution.fields.wpsms_woocommerce_order_notification = false;
    }
});
