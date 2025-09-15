const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
const { decodeEntities } = window.wp.htmlEntities;
const { getSetting } = window.wc.wcSettings;

const settings = getSetting('pawapay_data', {});
const defaultLabel = decodeEntities(settings.title) || 'PawaPay';

const Label = (props) => {
    const { PaymentMethodLabel } = window.wc.components;
    const label = props.title || defaultLabel;

    return React.createElement(PaymentMethodLabel, {
        text: label,
        icon: null,
    });
};

registerPaymentMethod({
    name: 'pawapay',
    label: React.createElement(Label, null),
    ariaLabel: 'PawaPay',
    canMakePayment: () => true,
    content: React.createElement('div', null,
        decodeEntities(settings.description || '')
    ),
    edit: React.createElement('div', null,
        decodeEntities(settings.description || '')
    ),
    supports: {
        features: settings.supports || [],
    },
});