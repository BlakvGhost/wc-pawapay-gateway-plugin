const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
const { getSetting, getSiteSettings } = window.wc.wcSettings;
const { createElement, useEffect, useState } = window.wp.element;
const { decodeEntities } = window.wp.htmlEntities;
const { usePaymentMethodData } = window.wc.wcBlocksCheckout;

const settings = getSetting('pawapay_data', {});

const PawaPayComponent = () => {
    const siteSettings = getSiteSettings();
    const description = decodeEntities(settings.description || '');
    const countries = settings.countries || {};
    const [selectedCountry, setSelectedCountry] = useState('');
    const [currencies, setCurrencies] = useState([]);
    const [convertedAmount, setConvertedAmount] = useState(null);
    const {
        emitResponse,
        getData,
        setData,
    } = usePaymentMethodData();
    const orderTotal = settings.total_price;
    const currentCurrency = settings.current_currency;

    useEffect(() => {
        if (selectedCountry && countries[selectedCountry]) {
            const extractedCurrencies = [];
            const seenCurrencyCodes = new Set();

            countries[selectedCountry].providers.forEach(provider => {
                provider.currencies.forEach(currency => {
                    if (!seenCurrencyCodes.has(currency.currency)) {
                        extractedCurrencies.push(currency);
                        seenCurrencyCodes.add(currency.currency);
                    }
                });
            });

            setCurrencies(extractedCurrencies);
            // Réinitialiser le montant converti et la devise si le pays change
            setConvertedAmount(null);
            setData({ pawapay_currency: '' });
        } else {
            setCurrencies([]);
            setConvertedAmount(null);
            setData({ pawapay_country: '', pawapay_currency: '' });
        }
    }, [selectedCountry, countries, setData]);

    const handleCountryChange = (event) => {
        setSelectedCountry(event.target.value);
        setData({ pawapay_country: event.target.value });
    };

    const handleCurrencyChange = async (event) => {
        const currencyCode = event.target.value;
        setData({ pawapay_currency: currencyCode });

        if (currencyCode && currencyCode !== currentCurrency) {
            // Appel AJAX pour la conversion de devise
            const response = await fetch(`${siteSettings.wc_ajax_url}?action=pawapay_convert_currency`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `from=${currentCurrency}&to=${currencyCode}&amount=${orderTotal}`,
            });
            const data = await response.json();
            if (data.success) {
                setConvertedAmount(data.data);
            } else {
                setConvertedAmount(null);
                emitResponse.notice(emitResponse.noticeTypes.ERROR, 'Erreur de conversion de devise.');
            }
        } else {
            setConvertedAmount(null);
        }
    };

    return createElement('div', null,
        description ? createElement('p', null, description) : null,
        createElement('div', { className: 'woocommerce-pawapay-fields' },
            createElement('p', { className: 'form-row form-row-wide' },
                createElement('label', { htmlFor: 'pawapay_country' }, 'Pays', createElement('span', { className: 'required' }, '*')),
                createElement('select', {
                    id: 'pawapay_country',
                    name: 'pawapay_country',
                    className: 'wc-pawapay-country-select',
                    onChange: handleCountryChange,
                    value: selectedCountry,
                },
                    createElement('option', { value: '' }, 'Sélectionnez un pays'),
                    Object.keys(countries).map(countryCode =>
                        createElement('option', { value: countryCode, key: countryCode }, countries[countryCode].displayName?.fr || countries[countryCode].displayName?.en)
                    )
                )
            ),
            selectedCountry && createElement('p', { className: 'form-row form-row-wide' },
                createElement('label', { htmlFor: 'pawapay_currency' }, 'Devise', createElement('span', { className: 'required' }, '*')),
                createElement('select', {
                    id: 'pawapay_currency',
                    name: 'pawapay_currency',
                    className: 'wc-pawapay-currency-select',
                    onChange: handleCurrencyChange,
                },
                    currencies.length > 0 ?
                        currencies.map(currency =>
                            createElement('option', { value: currency.currency, key: currency.currency }, `${currency.displayName} (${currency.currency})`)
                        ) : createElement('option', null, 'Aucune devise disponible')
                )
            )
        ),
        convertedAmount && createElement('div', { className: 'pawapay-converted-amount' },
            createElement('p', null,
                `Le montant total de votre commande de ${orderTotal} ${currentCurrency} sera converti et payé en `,
                createElement('strong', null, `${convertedAmount} ${getData('pawapay_currency')}`)
            )
        )
    );
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