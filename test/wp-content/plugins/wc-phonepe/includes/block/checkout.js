const settings = window.wc.wcSettings.getSetting( 'sg-phonepe_data', {} );
const label = window.wp.htmlEntities.decodeEntities( settings.title ) || window.wp.i18n.__( 'Phonepe', 'wc-phonepe' );
const Content = () => {
	return window.wp.htmlEntities.decodeEntities( settings.description || '' );
};
const Woa_Gateway = {
	name: 'sg-phonepe',
	label: label,
	content: Object( window.wp.element.createElement )( Content, null ),
	edit: Object( window.wp.element.createElement )( Content, null ),
	canMakePayment: () => true,
	ariaLabel: label,
	supports: {
		features: settings.supports,
	},
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Woa_Gateway );