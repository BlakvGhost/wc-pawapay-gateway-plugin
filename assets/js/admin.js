jQuery(document).ready(function ($) {

    $('#pawapay_refund_order').on('change', function () {
        const selectedOption = $(this).find('option:selected');
        const maxAmount = selectedOption.data('amount') || 0;

        $('#pawapay_refund_amount').val(maxAmount);
        $('#pawapay_refund_amount').attr('max', maxAmount);
        $('#pawapay_max_amount').text(maxAmount);
    });

    $('.pawapay-refund-btn').on('click', function () {
        const orderId = $(this).data('order-id');
        const transactionId = $(this).data('transaction-id');
        const amount = $(this).data('amount');

        $('#pawapay_refund_order').val(orderId).trigger('change');
        $('html, body').animate({
            scrollTop: $('.pawapay-refund-form').offset().top - 50
        }, 500);
    });

    $('#pawapay_process_refund').on('click', function () {
        const $button = $(this);
        const orderId = $('#pawapay_refund_order').val();
        const transactionId = $('#pawapay_refund_order option:selected').data('transaction-id');
        const amount = $('#pawapay_total_amount').val();
        const reason = $('#pawapay_refund_reason').val();

        if (!orderId || !amount) {
            alert(pawapay_ajax.error_text + ' - ' + 'Veuillez remplir tous les champs requis.');
            return;
        }

        $button.prop('disabled', true).text(pawapay_ajax.processing_text);

        $.ajax({
            url: pawapay_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'pawapay_process_refund',
                nonce: pawapay_ajax.nonce,
                order_id: orderId,
                transaction_id: transactionId,
                amount: amount,
                reason: reason
            },
            success: function (response) {
                if (response.success) {
                    $('#pawapay_refund_result')
                        .removeClass('pawapay-error')
                        .addClass('pawapay-success')
                        .html('<p>' + response.data + '</p>');

                    setTimeout(function () {
                        location.reload();
                    }, 2000);
                } else {
                    $('#pawapay_refund_result')
                        .removeClass('pawapay-success')
                        .addClass('pawapay-error')
                        .html('<p>' + response.data + '</p>');
                }
            },
            error: function () {
                $('#pawapay_refund_result')
                    .removeClass('pawapay-success')
                    .addClass('pawapay-error')
                    .html('<p>' + pawapay_ajax.error_text + '</p>');
            },
            complete: function () {
                $button.prop('disabled', false).text('Effectuer le remboursement');
            }
        });
    });
});