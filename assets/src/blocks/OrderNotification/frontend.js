import metadata from './block.json';
import { CheckboxControl} from "@wordpress/components";
import { __ } from '@wordpress/i18n';


// Global import
const { registerCheckoutBlock } = wc.blocksCheckout;

console.log("Loaded")

const Block = ({ children, checkoutExtensionData }) => {
    return (
        <div className={ 'example-fields' }>
           Hello World
        </div>
    )
}


const options = {
    metadata,
    component: Block
};


registerCheckoutBlock( options );