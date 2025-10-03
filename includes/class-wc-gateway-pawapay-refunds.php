<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_Gateway_PawaPay_Refunds
{
    public function __construct()
    {
        add_action('woocommerce_order_item_add_action_buttons', [$this, 'modify_refund_ui'], 10, 1);
        add_action('wp_ajax_woocommerce_refund_line_items', [$this, 'validate_refund'], 5);
    }

    public function modify_refund_ui($order)
    {
        if ($order->get_payment_method() !== 'pawapay') {
            return;
        }

        $pawapay_currency = $order->get_meta('pawapay_currency');
        $pawapay_converted_amount = $order->get_meta('pawapay_converted_amount');
        $order_total = $order->get_total();
        $order_currency = $order->get_currency();

        $display_amount = $pawapay_converted_amount ?: $order_total;
        $display_currency = $pawapay_currency ?: $order_currency;

?>
        <script type="text/javascript">
            jQuery(function($) {
                $('input#refund_amount').prop('disabled', true)
                    .attr('placeholder', '<?php echo esc_js(__("Remboursements partiels non disponibles", "wc-pawapay")); ?>');

                $('button.do-api-refund').text('<?php echo esc_js(__("Remboursement intégral via PawaPay", "wc-pawapay")); ?>');

                $('.refund-actions').before(
                    '<div class="pawapay-refund-info" style="background: #f8f8f8; padding: 12px; margin-bottom: 10px; border-radius: 4px; border-left: 4px solid #007cba;">' +
                    '<strong><?php echo esc_js(__("Informations PawaPay:", "wc-pawapay")); ?></strong><br>' +
                    '<?php echo esc_js(__("Montant à rembourser:", "wc-pawapay")); ?> <strong><?php echo esc_js($display_amount); ?> <?php echo esc_js($display_currency); ?></strong>' +
                    '<?php echo $pawapay_converted_amount ? esc_js(" (montant converti PawaPay)") : ""; ?>' +
                    '</div>'
                );

                $('button.refund-items').on('click', function() {
                    $('input#refund_amount').val("<?php echo esc_js($order->get_total()); ?>");
                });
            });
        </script>
<?php
    }

    public function validate_refund()
    {
        $order_id = absint($_POST['order_id']);
        $order = wc_get_order($order_id);

        if (!$order || $order->get_payment_method() !== 'pawapay') {
            return;
        }

        $refund_amount = wc_clean($_POST['refund_amount']);
        $order_total = $order->get_total();

        if ($refund_amount < $order_total) {
            wp_send_json_error([
                'error' => __('PawaPay ne permet que les remboursements intégraux.', 'wc-pawapay')
            ]);
        }
    }

    public static function init()
    {
        new self();
    }
}

add_action('woocommerce_init', ['WC_Gateway_PawaPay_Refunds', 'init']);
