const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
const { decodeEntities } = window.wp.htmlEntities;
const { getSetting } = window.wc.wcSettings;
const { useState, useEffect } = window.wp.element;
const { __ } = window.wp.i18n;

const pawapayBlocksData = getSetting('pawapay_data', {});

const getCurrenciesForCountry = (countryCode, countriesList) => {
    if (!countryCode) return [];

    const country = countriesList.find(c => c.country === countryCode);
    if (!country || !country.providers) return [];

    const currenciesSet = new Set();
    const currenciesList = [];

    country.providers.forEach(provider => {
        if (provider.currencies) {
            provider.currencies.forEach(currency => {
                if (!currenciesSet.has(currency.currency)) {
                    currenciesSet.add(currency.currency);
                    currenciesList.push({
                        code: currency.currency,
                        name: currency.displayName || currency.currency
                    });
                }
            });
        }
    });

    return currenciesList;
};

const formatAmount = (amount, currency) => {
    if (!amount) return amount;
    if (amount > 1000 && amount % 100 === 0) {
        amount = amount / 100;
    }
    return new Intl.NumberFormat('fr-FR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(amount) + ' ' + currency;
};

const PawaPayContent = ({ data, eventRegistration, emitResponse }) => {
    const { onPaymentSetup } = eventRegistration || {};
    const [selectedCountry, setSelectedCountry] = useState('');
    const [selectedCurrency, setSelectedCurrency] = useState('');
    const [convertedAmount, setConvertedAmount] = useState(null);
    const [isLoading, setIsLoading] = useState(false);
    const [errors, setErrors] = useState({});
    const [conversionAttempts, setConversionAttempts] = useState(0);

    const countries = data.countries || [];
    const currencies = getCurrenciesForCountry(selectedCountry, countries);

    useEffect(() => {
        if (selectedCountry && currencies.length > 0) {
            const firstCurrency = currencies[0].code;
            setSelectedCurrency(firstCurrency);

            setConversionAttempts(0);
        } else {
            setSelectedCurrency('');
            setConvertedAmount(null);
        }
    }, [selectedCountry, currencies]);

    useEffect(() => {
        if (selectedCountry && selectedCurrency) {
            handleCurrencyConversion(selectedCurrency);
        }
    }, [selectedCurrency]);

    const handleCountryChange = (event) => {
        const countryCode = event.target.value;
        setSelectedCountry(countryCode);
        setErrors(prev => ({ ...prev, country: '' }));
    };

    const handleCurrencyChange = (event) => {
        const currencyCode = event.target.value;
        setSelectedCurrency(currencyCode);
        setErrors(prev => ({ ...prev, currency: '' }));
        setConversionAttempts(0);
    };

    const handleCurrencyConversion = (currencyCode) => {
        if (!selectedCountry || !currencyCode) return;

        if (conversionAttempts >= 3) {
            return;
        }

        setIsLoading(true);

        const formData = new URLSearchParams();
        formData.append('action', 'pawapay_convert_currency');
        formData.append('nonce', data.nonce);
        formData.append('from', data.current_currency);
        formData.append('to', currencyCode);
        formData.append('amount', data.order_total);

        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 secondes timeout

        fetch(data.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData,
            signal: controller.signal
        })
            .then(response => {
                clearTimeout(timeoutId);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(result => {
                if (result.success) {
                    setConvertedAmount(result.data);
                    setConversionAttempts(0);
                } else {
                    setConvertedAmount(null);
                    console.error('Conversion error:', result.data);
                    setConversionAttempts(prev => prev + 1);
                }
            })
            .catch(error => {
                clearTimeout(timeoutId);
                console.error('Conversion fetch error:', error);
                setConvertedAmount(null);
                setConversionAttempts(prev => prev + 1);

                if (error.name !== 'AbortError' && conversionAttempts < 2) {
                    setTimeout(() => {
                        handleCurrencyConversion(currencyCode);
                    }, 1000 * conversionAttempts);
                }
            })
            .finally(() => {
                setIsLoading(false);
            });
    };

    useEffect(() => {
        if (!onPaymentSetup) return;

        const unsubscribe = onPaymentSetup(() => {
            const newErrors = {};

            if (!selectedCountry) {
                newErrors.country = data.i18n.country_required;
            }

            if (!selectedCurrency) {
                newErrors.currency = data.i18n.currency_required;
            }

            if (Object.keys(newErrors).length > 0) {
                setErrors(newErrors);
                return {
                    type: emitResponse?.responseTypes?.ERROR,
                    message: pawapayBlocksData.form_error ?? __('Veuillez corriger les erreurs dans le formulaire PawaPay.', 'wc-pawapay'),
                    messageContext: emitResponse?.noticeContexts?.PAYMENTS,
                };
            }

            return {
                type: emitResponse?.responseTypes?.SUCCESS,
                meta: {
                    paymentMethodData: {
                        'pawapay_country': selectedCountry,
                        'pawapay_currency': selectedCurrency
                    }
                }
            };
        });

        return unsubscribe;
    }, [onPaymentSetup, selectedCountry, selectedCurrency, emitResponse]);

    return React.createElement('div', { className: 'wc-pawapay-blocks-content' },
        React.createElement('p', { style: { marginBottom: '1em' } },
            decodeEntities(data.description || '')
        ),

        React.createElement('div', { className: 'wc-pawapay-field' },
            React.createElement('label', { htmlFor: 'pawapay_country_blocks' },
                pawapayBlocksData.country_label ?? __('Pays', 'wc-pawapay'),
                React.createElement('span', { className: 'required' }, ' *')
            ),
            React.createElement('select', {
                id: 'pawapay_country_blocks',
                name: 'pawapay_country',
                value: selectedCountry,
                onChange: handleCountryChange,
                required: true,
                className: `wc-pawapay-country-select ${errors.country ? 'has-error' : ''}`
            },
                React.createElement('option', { value: '' }, data.i18n.select_country),
                countries.map(country =>
                    React.createElement('option', {
                        key: country.country,
                        value: country.country
                    }, country.displayName?.fr || country.displayName?.en || country.country)
                )
            ),
            errors.country &&
            React.createElement('div', {
                className: 'wc-pawapay-field-error',
                style: {
                    color: '#e2401c',
                    fontSize: '12px',
                    marginTop: '4px'
                }
            }, errors.country)
        ),

        React.createElement('div', { className: 'wc-pawapay-field', style: { marginTop: '1em' } },
            React.createElement('label', { htmlFor: 'pawapay_currency_blocks' },
                pawapayBlocksData.currency_label ?? __('Devise', 'wc-pawapay'),
                React.createElement('span', { className: 'required' }, ' *')
            ),
            React.createElement('select', {
                id: 'pawapay_currency_blocks',
                name: 'pawapay_currency',
                value: selectedCurrency,
                onChange: handleCurrencyChange,
                required: true,
                disabled: !selectedCountry,
                className: `wc-pawapay-currency-select ${errors.currency ? 'has-error' : ''}`
            },
                React.createElement('option', { value: '' }, data.i18n.select_currency),
                currencies.map(currency =>
                    React.createElement('option', {
                        key: currency.code,
                        value: currency.code
                    }, `${currency.name} (${currency.code})`)
                )
            ),
            errors.currency &&
            React.createElement('div', {
                className: 'wc-pawapay-field-error',
                style: {
                    color: '#e2401c',
                    fontSize: '12px',
                    marginTop: '4px'
                }
            }, errors.currency)
        ),

        convertedAmount && !isLoading &&
        React.createElement('div', {
            className: 'pawapay-converted-amount',
            style: {
                marginTop: '1em',
                backgroundColor: '#f8f9fa',
                padding: '15px',
                borderRadius: '5px',
                borderLeft: '4px solid #007cba'
            }
        },
            data.i18n.converted_amount
                .replace('%s', formatAmount(data.order_total, data.current_currency))
                .replace('%s',
                    formatAmount(convertedAmount, selectedCurrency)
                ),
        ),

        isLoading &&
        React.createElement('div', {
            style: {
                marginTop: '1em',
                textAlign: 'center',
                color: '#666'
            }
        }, pawapayBlocksData.conversion_in_progress ?? __('Conversion en cours...', 'wc-pawapay')),

        conversionAttempts >= 3 &&
        React.createElement('div', {
            style: {
                marginTop: '1em',
                textAlign: 'center',
                color: '#e2401c',
                fontSize: '12px'
            }
        }, pawapayBlocksData.conversion_not_working ?? __('Service de conversion temporairement indisponible', 'wc-pawapay'))
    );
};

const PawaPayPaymentMethod = {
    name: 'pawapay',
    label: React.createElement('span', {},
        decodeEntities(pawapayBlocksData.title || 'PawaPay')
    ),
    content: React.createElement(PawaPayContent, { data: pawapayBlocksData }),
    edit: React.createElement(PawaPayContent, { data: pawapayBlocksData }),
    canMakePayment: () => true,
    ariaLabel: decodeEntities(pawapayBlocksData.title || 'PawaPay Mobile Money'),
    supports: {
        features: pawapayBlocksData.supports || ['products'],
    },
    placeOrderButtonLabel: pawapayBlocksData.pay_button_label ?? __('Payer avec PawaPay', 'wc-pawapay'),
};

if (typeof registerPaymentMethod === 'function') {
    registerPaymentMethod(PawaPayPaymentMethod);
} else {
    console.error('registerPaymentMethod function not available');
}