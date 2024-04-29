import metadata from './block.json';
import { __ } from '@wordpress/i18n';
import { useState, useCallback } from '@wordpress/element';
// Global import
const { registerCheckoutBlock } = wc.blocksCheckout;
const Block = ({ checkoutExtensionData }) => {
    const [deliveryDate, setDeliveryDate] = useState('');
    const { setExtensionData } = checkoutExtensionData;
    const onDateChange = useCallback(
        (date) => {
            setDeliveryDate(date);
            setExtensionData('wp-sms', 'delivery_date', date);
        },
        [setDeliveryDate, setExtensionData]
    );
    return (
        <div className={'example-fields'}>
            <label htmlFor="delivery-date">Choose your delivery date:</label>
            <input
                type="checkbox"
                id="delivery-date"
                className={'orddd-datepicker'}
                placeholder={''}
                value={deliveryDate}
                onChange={(e) => onDateChange(e.target.value)}
                style={{ width: '100%' }}
            />
        </div>
    );
}
const options = {
    metadata,
    component: Block
};
registerCheckoutBlock(options);