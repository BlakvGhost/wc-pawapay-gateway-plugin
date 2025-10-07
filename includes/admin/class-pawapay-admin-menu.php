<?php

if (!defined('ABSPATH')) {
    exit;
}

class PawaPay_Admin_Menu
{

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_pawapay_process_refund', array($this, 'process_refund_ajax'));
    }

    public function add_admin_menu()
    {
        add_menu_page(
            __('PawaPay', 'wc-pawapay'),
            'PawaPay',
            'manage_woocommerce',
            'wc-pawapay',
            array($this, 'admin_dashboard'),
            'dashicons-money-alt',
            56
        );

        add_submenu_page(
            'wc-pawapay',
            __('Transactions PawaPay', 'wc-pawapay'),
            __('Transactions', 'wc-pawapay'),
            'manage_woocommerce',
            'wc-pawapay-transactions',
            array($this, 'transactions_page')
        );

        add_submenu_page(
            'wc-pawapay',
            __('Remboursements PawaPay', 'wc-pawapay'),
            __('Remboursements', 'wc-pawapay'),
            'manage_woocommerce',
            'wc-pawapay-refunds',
            array($this, 'refunds_page')
        );
    }

    public function enqueue_admin_scripts($hook)
    {
        if (strpos($hook, 'wc-pawapay') === false) {
            return;
        }

        wp_enqueue_style('pawapay-admin-css', plugin_dir_url(WC_PAWAPAY_PLUGIN_FILE) . 'assets/css/admin.css', array(), '1.0.0');
        wp_enqueue_script('pawapay-admin-js', plugin_dir_url(WC_PAWAPAY_PLUGIN_FILE) . 'assets/js/admin.js', array('jquery'), '1.0.0', true);

        wp_localize_script('pawapay-admin-js', 'pawapay_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pawapay_refund_nonce'),
            'processing_text' => __('Traitement en cours...', 'wc-pawapay'),
            'success_text' => __('Remboursement effectué avec succès', 'wc-pawapay'),
            'error_text' => __('Erreur lors du remboursement', 'wc-pawapay')
        ));
    }

    public function admin_dashboard()
    {
?>
        <div class="wrap">
            <h1><?php _e('Tableau de bord PawaPay', 'wc-pawapay'); ?></h1>

            <div class="pawapay-dashboard-stats">
                <?php $this->display_stats(); ?>
            </div>

            <div class="pawapay-recent-transactions">
                <h2><?php _e('Transactions récentes', 'wc-pawapay'); ?></h2>
                <?php $this->display_recent_transactions(); ?>
            </div>
        </div>
    <?php
    }

    public function transactions_page()
    {
    ?>
        <div class="wrap">
            <h1><?php _e('Transactions PawaPay', 'wc-pawapay'); ?></h1>
            <?php $this->display_transactions_table(); ?>
        </div>
    <?php
    }

    public function refunds_page()
    {
    ?>
        <div class="wrap">
            <h1><?php _e('Remboursements PawaPay', 'wc-pawapay'); ?></h1>
            <?php $this->display_refunds_interface(); ?>
        </div>
    <?php
    }

    private function display_stats()
    {
        $pawapay_orders = $this->get_pawapay_orders();

        $total_orders = count($pawapay_orders);
        $completed_orders = 0;
        $pending_orders = 0;
        $failed_orders = 0;
        $total_revenue = 0;

        foreach ($pawapay_orders as $order) {
            $order_total = $order->get_total();

            switch ($order->get_status()) {
                case 'completed':
                case 'processing':
                    $completed_orders++;
                    $total_revenue += $order_total;
                    break;
                case 'pending':
                case 'on-hold':
                    $pending_orders++;
                    break;
                case 'failed':
                case 'cancelled':
                    $failed_orders++;
                    break;
            }
        }

    ?>
        <div class="pawapay-stats-grid">
            <div class="pawapay-stat-card">
                <h3><?php echo $total_orders; ?></h3>
                <p><?php _e('Commandes totales', 'wc-pawapay'); ?></p>
            </div>
            <div class="pawapay-stat-card">
                <h3><?php echo $completed_orders; ?></h3>
                <p><?php _e('Commandes payées', 'wc-pawapay'); ?></p>
            </div>
            <div class="pawapay-stat-card">
                <h3><?php echo $pending_orders; ?></h3>
                <p><?php _e('Commandes en attente', 'wc-pawapay'); ?></p>
            </div>
            <div class="pawapay-stat-card">
                <h3><?php echo wc_price($total_revenue); ?></h3>
                <p><?php _e('Chiffre d\'affaires', 'wc-pawapay'); ?></p>
            </div>
        </div>
    <?php
    }

    private function display_recent_transactions($limit = 10)
    {
        $orders = $this->get_pawapay_orders($limit);
        $this->display_orders_table($orders);
    }

    private function display_transactions_table()
    {
        $orders = $this->get_pawapay_orders();
        $this->display_orders_table($orders, true);
    }

    private function display_orders_table($orders, $show_pagination = false)
    {
        if (empty($orders)) {
            echo '<p>' . __('Aucune transaction PawaPay trouvée.', 'wc-pawapay') . '</p>';
            return;
        }

    ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Commande', 'wc-pawapay'); ?></th>
                    <th><?php _e('Date', 'wc-pawapay'); ?></th>
                    <th><?php _e('Montant', 'wc-pawapay'); ?></th>
                    <th><?php _e('Statut', 'wc-pawapay'); ?></th>
                    <th><?php _e('Transaction ID', 'wc-pawapay'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                    <?php
                    $transaction_id = $order->get_meta('pawapay_deposit_id');
                    $order_status = $order->get_status();
                    $status_label = wc_get_order_status_name($order_status);
                    ?>
                    <tr>
                        <td>
                            <a href="<?php echo admin_url('post.php?post=' . $order->get_id() . '&action=edit'); ?>">
                                #<?php echo $order->get_id(); ?>
                            </a>
                        </td>
                        <td><?php echo $order->get_date_created()->format('d/m/Y H:i'); ?></td>
                        <td><?php echo $order->get_formatted_order_total(); ?></td>
                        <td>
                            <span class="order-status status-<?php echo $order_status; ?>">
                                <?php echo $status_label; ?>
                            </span>
                        </td>
                        <td><?php echo $transaction_id ?: __('N/A', 'wc-pawapay'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php
    }

    private function display_refunds_interface()
    {
        $completed_orders = $this->get_pawapay_orders(-1, ['completed', 'processing']);
    ?>

        <div class="pawapay-refund-interface">
            <div class="pawapay-refund-form">
                <h2><?php _e('Effectuer un remboursement', 'wc-pawapay'); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="pawapay_refund_order"><?php _e('Sélectionner une commande', 'wc-pawapay'); ?></label>
                        </th>
                        <td>
                            <select id="pawapay_refund_order" name="pawapay_refund_order" class="regular-text">
                                <option value=""><?php _e('Choisir une commande...', 'wc-pawapay'); ?></option>
                                <?php foreach ($completed_orders as $order): ?>
                                    <?php if ($order->get_transaction_id()): ?>
                                        <option value="<?php echo $order->get_id(); ?>"
                                            data-transaction-id="<?php echo $order->get_transaction_id(); ?>"
                                            data-amount="<?php echo $order->get_total(); ?>">
                                            Commande #<?php echo $order->get_id(); ?> -
                                            <?php echo $order->get_formatted_order_total(); ?> -
                                            <?php echo $order->get_date_created()->format('d/m/Y'); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <input type="hidden" name="pawapay_total_amount" id="pawapay_total_amount" value="<?php echo $order->get_total(); ?>">
                    <tr>
                        <th scope="row">
                            <label for="pawapay_refund_reason"><?php _e('Raison du remboursement', 'wc-pawapay'); ?></label>
                        </th>
                        <td>
                            <textarea id="pawapay_refund_reason"
                                name="pawapay_refund_reason"
                                class="large-text"
                                rows="3"></textarea>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="button" id="pawapay_process_refund" class="button button-primary">
                        <?php _e('Effectuer le remboursement', 'wc-pawapay'); ?>
                    </button>
                </p>
            </div>

            <div id="pawapay_refund_result"></div>
        </div>

        <div class="pawapay-refund-history">
            <h2><?php _e('Historique des remboursements', 'wc-pawapay'); ?></h2>
            <?php $this->display_refund_history(); ?>
        </div>
    <?php
    }

    private function display_refund_history()
    {
        $refunded_orders = $this->get_refunded_orders();

        if (empty($refunded_orders)) {
            echo '<p>' . __('Aucun remboursement effectué.', 'wc-pawapay') . '</p>';
            return;
        }

    ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Commande', 'wc-pawapay'); ?></th>
                    <th><?php _e('Date remboursement', 'wc-pawapay'); ?></th>
                    <th><?php _e('Montant remboursé', 'wc-pawapay'); ?></th>
                    <th><?php _e('Raison', 'wc-pawapay'); ?></th>
                    <th><?php _e('Statut', 'wc-pawapay'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($refunded_orders as $order): ?>
                    <?php
                    $refunds = $order->get_refunds();
                    foreach ($refunds as $refund):
                        $refund_reason = $refund->get_reason();
                    ?>
                        <tr>
                            <td>
                                <a href="<?php echo admin_url('post.php?post=' . $order->get_id() . '&action=edit'); ?>">
                                    #<?php echo $order->get_id(); ?>
                                </a>
                            </td>
                            <td><?php echo $refund->get_date_created()->format('d/m/Y H:i'); ?></td>
                            <td><?php echo wc_price($refund->get_amount()); ?></td>
                            <td><?php echo $refund_reason ?: __('Non spécifié', 'wc-pawapay'); ?></td>
                            <td>
                                <span class="refund-status status-completed">
                                    <?php _e('Complété', 'wc-pawapay'); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
<?php
    }

    private function get_pawapay_orders($limit = -1, $statuses = array())
    {
        $args = array(
            'limit' => $limit,
            'payment_method' => 'pawapay',
            'return' => 'objects',
        );

        if (!empty($statuses)) {
            $args['status'] = $statuses;
        }

        return wc_get_orders($args);
    }

    private function get_refunded_orders()
    {
        $args = array(
            'limit' => -1,
            'payment_method' => 'pawapay',
            'has_refund' => true,
            'return' => 'objects',
        );

        return wc_get_orders($args);
    }

    public function process_refund_ajax()
    {
        check_ajax_referer('pawapay_refund_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permissions insuffisantes.', 'wc-pawapay'));
        }

        $order_id = absint($_POST['order_id']);
        $transaction_id = sanitize_text_field($_POST['transaction_id']);
        $amount = floatval($_POST['amount']);
        $reason = sanitize_text_field($_POST['reason']);

        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error(__('Commande non trouvée.', 'wc-pawapay'));
        }

        if ($amount <= 0 || $amount > $order->get_total()) {
            wp_send_json_error(__('Montant de remboursement invalide.', 'wc-pawapay'));
        }

        $gateways = WC()->payment_gateways->payment_gateways();
        $gateway = $gateways['pawapay'] ?? null;

        if (!$gateway) {
            wp_send_json_error(__('Gateway PawaPay non trouvée.', 'wc-pawapay'));
        }

        try {
            $refund = wc_create_refund(array(
                'amount'         => $amount,
                'reason'         => $reason,
                'order_id'       => $order_id,
                'refund_payment' => false,
                'restock_items'  => true
            ));

            if (is_wp_error($refund)) {
                wp_send_json_error($refund->get_error_message());
            }

            $refund_result = $gateway->process_refund($order_id, $amount, $reason, $transaction_id);

            if (is_wp_error($refund_result)) {
                wp_delete_post($refund->get_id(), true);
                wp_send_json_error($refund_result->get_error_message());
            }

            if ($refund_result) {
                $refund->set_refunded_payment(true);
                $refund->save();

                if ($amount === $order->get_total()) {
                    $order->update_status('refunded', __('Commande entièrement remboursée via PawaPay.', 'wc-pawapay'));
                } else {
                    $order->update_status('completed', __('Remboursement partiel effectué via PawaPay.', 'wc-pawapay'));
                }

                $refund_type = ($amount === $order->get_total()) ? 'complet' : 'partiel';
                $order->add_order_note(
                    sprintf(
                        __('Remboursement %s PawaPay effectué. Montant: %s, Raison: %s, ID Transaction: %s', 'wc-pawapay'),
                        $refund_type,
                        wc_price($amount),
                        $reason,
                        $transaction_id
                    )
                );

                do_action('pawapay_refund_processed', $order_id, $order, $amount, $reason);
                wp_send_json_success(__('Remboursement effectué avec succès. Statut de la commande mis à jour.', 'wc-pawapay'));
            } else {
                wp_delete_post($refund->get_id(), true);
                wp_send_json_error(__('Erreur lors du remboursement via l\'API PawaPay.', 'wc-pawapay'));
            }
        } catch (Exception $e) {
            wp_send_json_error(__('Erreur lors du traitement du remboursement: ', 'wc-pawapay') . $e->getMessage());
        }
    }
}

new PawaPay_Admin_Menu();
