const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
const { getSetting } = window.wc.wcSettings;
const { createElement } = window.wp.element;
const { decodeEntities } = window.wp.htmlEntities;

const settings = getSetting('pawapay_data', {});

const PawaPayComponent = () => {
    const description = decodeEntities(settings.description || '');
    // Retourne null si pas de description, pour un affichage plus propre.
    return description ? createElement('div', null, description) : null;
};

const label = decodeEntities(settings.title || 'Mobile Money (PawaPay)');

const pawapayPaymentMethod = {
    name: 'pawapay',
    label: label,
    content: createElement(PawaPayComponent, null),
    edit: createElement(PawaPayComponent, null),
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings.supports || ['products'],
    },
};

registerPaymentMethod(pawapayPaymentMethod);