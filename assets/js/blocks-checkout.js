const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
const { getSetting } = window.wc.wcSettings;
const { createElement, useEffect, useState } = window.wp.element;
const { decodeEntities } = window.wp.htmlEntities;

const wcBlocksCheckout = window.wc.wcBlocksCheckout || {};
const { usePaymentMethodData } = wcBlocksCheckout;

const settings = getSetting('pawapay_data', {});

// Fonction pour formater le montant
const formatAmount = (amount, currencyCode) => {
    if (!amount) return amount;

    // Si le montant est très grand, c'est probablement en centimes
    if (amount > 1000 && amount % 100 === 0) {
        amount = amount / 100;
    }

    // Formater avec séparateurs de milliers
    return new Intl.NumberFormat('fr-FR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(amount);
};

const PawaPayComponent = () => {
    const description = decodeEntities(settings.description || '');
    const countries = settings.countries || {};
    const [selectedCountry, setSelectedCountry] = useState('');
    const [selectedCurrency, setSelectedCurrency] = useState('');
    const [currencies, setCurrencies] = useState([]);
    const [convertedAmount, setConvertedAmount] = useState(null);

    const paymentMethodData = usePaymentMethodData ? usePaymentMethodData() : {
        emitResponse: {
            notice: () => { },
            noticeTypes: { ERROR: 'error' }
        },
        getData: () => ({}),
        setData: () => { }
    };

    const { emitResponse, getData, setData } = paymentMethodData;
    const orderTotal = settings.total_price;
    const currentCurrency = settings.current_currency;

    // Debug des changements d'état
    useEffect(() => {
        console.log('convertedAmount changé:', convertedAmount);
    }, [convertedAmount]);

    useEffect(() => {
        console.log('selectedCurrency changé:', selectedCurrency);
    }, [selectedCurrency]);

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
            setConvertedAmount(null);
            setSelectedCurrency('');
            setData({
                pawapay_country: selectedCountry,
                pawapay_currency: ''
            });
        } else {
            setCurrencies([]);
            setConvertedAmount(null);
            setSelectedCurrency('');
            setData({
                pawapay_country: '',
                pawapay_currency: ''
            });
        }
    }, [selectedCountry, countries, setData]);

    const handleCountryChange = (event) => {
        const country = event.target.value;
        setSelectedCountry(country);
        setData({
            pawapay_country: country,
            pawapay_currency: selectedCurrency
        });
    };

    const handleCurrencyChange = async (event) => {
        const currencyCode = event.target.value;
        setSelectedCurrency(currencyCode);
        setData({
            pawapay_country: selectedCountry,
            pawapay_currency: currencyCode
        });

        if (currencyCode && currencyCode !== currentCurrency) {
            try {
                console.log('Début conversion:', { from: currentCurrency, to: currencyCode, amount: orderTotal });

                const response = await fetch('/wp-json/pawapay/v1/convert-currency', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        from: currentCurrency,
                        to: currencyCode,
                        amount: orderTotal
                    }),
                });

                const data = await response.json();
                console.log('Réponse API conversion:', data);

                if (data.success) {
                    console.log('Conversion réussie:', data.data);
                    setConvertedAmount(data.data);
                } else {
                    console.log('Conversion échouée:', data.message);
                    setConvertedAmount(null);
                    if (emitResponse.notice) {
                        emitResponse.notice(emitResponse.noticeTypes.ERROR, data.message || 'Erreur de conversion de devise.');
                    }
                }
            } catch (error) {
                console.error('Erreur de conversion:', error);
                setConvertedAmount(null);
            }
        } else {
            console.log('Aucune conversion nécessaire');
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
                    required: true
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
                    value: selectedCurrency,
                    required: true
                },
                    createElement('option', { value: '' }, 'Sélectionnez une devise'),
                    currencies.length > 0 ?
                        currencies.map(currency =>
                            createElement('option', { value: currency.currency, key: currency.currency }, `${currency.displayName} (${currency.currency})`)
                        ) : createElement('option', null, 'Aucune devise disponible')
                )
            )
        ),
        convertedAmount && selectedCurrency && createElement('div', { className: 'pawapay-converted-amount' },
            createElement('p', null,
                `Le montant total de votre commande de ${formatAmount(orderTotal, currentCurrency)} ${currentCurrency} sera converti et payé en `,
                createElement('strong', null, `${formatAmount(convertedAmount, selectedCurrency)} ${selectedCurrency}`)
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