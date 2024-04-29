import metadata from './block.json';
import {__} from '@wordpress/i18n';
import {useState, useCallback} from '@wordpress/element';

// Global import
const {registerCheckoutBlock} = wc.blocksCheckout;

const Block = ({checkoutExtensionData}) => {
    console.log(checkoutExtensionData)
    const [smsOptIn, setSmsOptIn] = useState('');
    const {setExtensionData} = checkoutExtensionData;

    const onCheckboxChange = useCallback(
        (checked) => {
            console.log(checked)
            setSmsOptIn(checked);
            setExtensionData('wp-sms', blockData.dataHandler, checked);
        },
        [setSmsOptIn, setExtensionData]
    );
    return (
        <div className="wpsms-block-sms-opt-in">
            <div className="wc-block-components-checkbox">
                <label htmlFor="wpsms_woocommerce_order_notification_field">
                    <input onChange={(e) => onCheckboxChange(e.target.checked)} name="wpsms_woocommerce_order_notification" id="wpsms_woocommerce_order_notification_field" data-priority="" className="wc-block-components-checkbox__input" type="checkbox" aria-invalid="false"/>
                    <svg className="wc-block-components-checkbox__mark" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 20">
                        <path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"></path>
                    </svg>
                    <span className="wc-block-components-checkbox__label">
                        {__('I would like to get notification about any change in my order via SMS.', 'wp-sms')}
                        <span className="optional">({__('optional', 'wp-sms')})</span>
                    </span>
                </label>
            </div>
        </div>
    );
}
const options = {
    metadata,
    component: Block
};
registerCheckoutBlock(options);