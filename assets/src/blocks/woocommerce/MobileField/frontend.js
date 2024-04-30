import metadata from './block.json';
import {__} from '@wordpress/i18n';
import {useState, useCallback} from '@wordpress/element';

// Global import
const {registerCheckoutBlock} = wc.blocksCheckout;

const Block = ({checkoutExtensionData, isRequired}) => {
    console.log(checkoutExtensionData)
    const [mobile, setMobile] = useState('');
    const {setExtensionData} = checkoutExtensionData;

    const onMobileFieldChange = useCallback(
        (value) => {
            setMobile(value);
            setExtensionData('wp-sms', blockData.dataHandler, value);
        },
        [setMobile, setExtensionData]
    );
    return (
        <div className="wpsms-block-order-mobile-field">
            <div className="wc-block-components-text-input">
                <p className="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                    <label htmlFor="reg_mobile">Mobile<span className='required'>*</span></label>
                    <input required={isRequired}
                           onChange={e => onMobileFieldChange(e.target.value)}
                           type="tel"
                           className="woocommerce-Input woocommerce-Input--text input-text"
                           id="reg_mobile"
                           autoComplete="mobile"
                           value={mobile} />
                </p>
            </div>
        </div>
    );
}
const options = {
    metadata,
    component: Block
};
registerCheckoutBlock(options);