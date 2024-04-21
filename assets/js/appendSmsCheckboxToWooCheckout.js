// Wait for the full page to load, including styles and images
document.addEventListener('DOMContentLoaded', function() {
    // Define the HTML structure as a string
    var htmlContent = `
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

    // Try to append the content to the end of the form
    var form = document.querySelector('.woocommerce-checkout');
    if (form) {
        var placeToInsert = form.querySelector('.wc-block-checkout__order-notes');
        if (placeToInsert) {
            placeToInsert.insertAdjacentHTML('afterend', htmlContent);
        } else {
            // As a fallback, append directly to the form if the specific target isn't found
            form.insertAdjacentHTML('beforeend', htmlContent);
        }
    }
});
