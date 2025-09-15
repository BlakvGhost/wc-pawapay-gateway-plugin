jQuery(function ($) {
    'use strict';

    // Charger les opérateurs quand le pays change
    $(document).on('change', '.pawapay-country-select', function () {
        var country = $(this).val();
        var providerSelect = $('.pawapay-provider-select');

        if (!country) {
            providerSelect.html('<option value="">' + pawapay_vars.select_country_first + '</option>');
            return;
        }

        providerSelect.html('<option value="">' + pawapay_vars.loading_providers + '</option>');

        // Appel AJAX pour récupérer les opérateurs
        $.ajax({
            url: pawapay_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'pawapay_get_providers',
                country: country,
                security: pawapay_vars.ajax_nonce
            },
            success: function (response) {
                if (response.success && response.data && response.data.providers) {
                    var options = '<option value="">' + pawapay_vars.select_provider + '</option>';

                    $.each(response.data.providers, function (index, provider) {
                        var logo = provider.logoUrl ? '<img src="' + provider.logoUrl + '" alt="' + provider.name + '" class="pawapay-provider-logo" /> ' : '';
                        options += '<option value="' + provider.provider + '" data-logo="' + (provider.logoUrl || '') + '">' + logo + provider.name + '</option>';
                    });

                    providerSelect.html(options);
                } else {
                    providerSelect.html('<option value="">' + pawapay_vars.no_providers + '</option>');
                }
            },
            error: function () {
                providerSelect.html('<option value="">' + pawapay_vars.error_loading + '</option>');
            }
        });
    });

    // Déclencher le changement initial si un pays est déjà sélectionné
    var initialCountry = $('.pawapay-country-select').val();
    if (initialCountry) {
        $('.pawapay-country-select').trigger('change');
    }
});