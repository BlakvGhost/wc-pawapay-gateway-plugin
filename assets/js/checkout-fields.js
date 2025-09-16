jQuery(document).ready(function ($) {
    const $countrySelect = $('#pawapay_country');
    const $currencySelect = $('#pawapay_currency');

    function updateCurrencies() {
        const country = $countrySelect.val();
        if (!country) {
            $currencySelect.empty().attr('disabled', 'disabled');
            return;
        }

        $.ajax({
            url: wc_pawapay_params.ajax_url,
            type: 'POST',
            data: {
                action: 'pawapay_get_currencies',
                country_code: country
            },
            success: function (response) {
                $currencySelect.empty().removeAttr('disabled');
                if (response.success && response.data.length > 0) {
                    response.data.forEach(function (currency) {
                        $currencySelect.append('<option value="' + currency.code + '">' + currency.name + ' (' + currency.code + ')</option>');
                    });
                } else {
                    $currencySelect.attr('disabled', 'disabled').append('<option>Aucune devise disponible</option>');
                }
            },
            error: function () {
                $currencySelect.empty().attr('disabled', 'disabled').append('<option>Erreur de chargement</option>');
            }
        });
    }

    $countrySelect.on('change', updateCurrencies);
    updateCurrencies();
});