import metadata from './block.json';
import { __ } from '@wordpress/i18n';


// Global import
const { registerCheckoutBlock } = wc.blocksCheckout;


const Block = ({ children, checkoutExtensionData }) => {
    return (
        <div className={ 'example-fields' }>
            <p>Hello World</p>
        </div>
    )
}


const options = {
    metadata,
    component: Block
};


registerCheckoutBlock( options );