jQuery(document).ready(function ($) {
    if (typeof pawapayData === 'undefined') {
        return;
    }

    var $countrySelect = $('#pawapay_country');
    var $currencySelect = $('#pawapay_currency');
    var $convertedAmountDiv = $('.pawapay-converted-amount');

    function formatAmount(amount, currency) {
        if (!amount) return amount;
        if (amount > 1000 && amount % 100 === 0) {
            amount = amount / 100;
        }
        return new Intl.NumberFormat('fr-FR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(amount) + ' ' + currency;
    }

    function updateCurrencyDropdown(countryCode) {
        $currencySelect = $('#pawapay_currency');
        $currencySelect.empty().append('<option value="">' + pawapayData.i18n.select_currency + '</option>');
        $convertedAmountDiv.hide();

        if (!countryCode) {
            return;
        }

        if (!pawapayData.countries || !Array.isArray(pawapayData.countries)) {
            return;
        }

        var country = pawapayData.countries.find(function (c) {
            return c.country === countryCode;
        });

        if (country && country.providers) {
            var seenCurrencies = new Set();
            var firstCurrency = null;
            country.providers.forEach(function (provider) {
                if (provider.currencies && Array.isArray(provider.currencies)) {
                    provider.currencies.forEach(function (currency) {
                        if (!seenCurrencies.has(currency.currency)) {
                            var optionText = (currency.displayName || currency.currency) + ' (' + currency.currency + ')';
                            var $option = $('<option></option>')
                                .val(currency.currency)
                                .text(optionText);
                            $currencySelect.append($option);
                            seenCurrencies.add(currency.currency);
                            if (!firstCurrency) {
                                firstCurrency = currency.currency;
                            }
                        }
                    });
                }
            });

            if (firstCurrency) {
                $currencySelect.val(firstCurrency).trigger('change');
            }
        }
    }

    $(document).on('change', '#pawapay_country', function () {
        var countryCode = $(this).val();
        updateCurrencyDropdown(countryCode);
    });

    $(document).on('updated_checkout', function () {
        $countrySelect = $('#pawapay_country');
        if ($countrySelect.length && $countrySelect.val()) {
            updateCurrencyDropdown($countrySelect.val());
        }
    });

    $(document).on('change', '#pawapay_currency', function () {
        var currencyCode = $(this).val();
        var countryCode = $countrySelect.val();

        if (countryCode && currencyCode) {
            $.ajax({
                url: pawapayData.ajax_url,
                type: 'POST',
                data: {
                    action: 'pawapay_convert_currency',
                    nonce: pawapayData.nonce,
                    from: pawapayData.current_currency,
                    to: currencyCode,
                    amount: pawapayData.order_total
                },
                success: function (response) {
                    if (response.success) {
                        $('.pawapay-order-total').text(formatAmount(pawapayData.order_total, pawapayData.current_currency));
                        $('.pawapay-converted-total').text(formatAmount(response.data, currencyCode));
                        $('.pawapay-converted-amount').show();
                    } else {
                        $('.pawapay-converted-amount').hide();
                    }
                },
                error: function (xhr, status, error) {
                    $('.pawapay-converted-amount').hide();
                }
            });
        } else {
            $('.pawapay-converted-amount').hide();
        }
    });

    setTimeout(function () {
        $countrySelect = $('#pawapay_country');
        if ($countrySelect.length) {
            updateCurrencyDropdown($countrySelect.val());
        }
    }, 1000);
});