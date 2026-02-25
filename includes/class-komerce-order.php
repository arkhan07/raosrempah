<?php
/**
 * Komerce Order Manager
 * Handles WooCommerce order ↔ Komerce order synchronization.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Komerce_Order
{

    public function __construct()
    {
        // Auto-create Komerce order when WooCommerce order moves to "processing"
        add_action('woocommerce_order_status_processing', array($this, 'auto_create_komerce_order'), 10, 1);
        // Add Komerce meta box to WC order edit screen
        add_action('add_meta_boxes', array($this, 'add_order_meta_box'));
    }

    /**
     * Auto-create Komerce order when WC order status = "processing"
     */
    public function auto_create_komerce_order($wc_order_id)
    {
        $settings = get_option('komerce_settings', array());
        if (($settings['auto_create_order'] ?? 'yes') !== 'yes') {
            return;
        }

        // Skip if already created — use WC Order meta API (HPOS compatible)
        $wc_order = wc_get_order($wc_order_id);
        if (!$wc_order) {
            return;
        }
        if ($wc_order->get_meta('_komerce_order_no')) {
            return;
        }

        $payload = $this->build_order_payload($wc_order, $settings);
        if (!$payload) {
            $wc_order->add_order_note('[Komerce] Gagal membuat order: data pengiriman tidak lengkap.');
            return;
        }

        $api = new Komerce_API();
        $result = $api->create_order($payload);

        if (isset($result['meta']['code']) && in_array($result['meta']['code'], array(200, 201))) {
            $order_no = $result['data']['order_no'] ?? '';
            $order_id = $result['data']['order_id'] ?? '';
            $wc_order->update_meta_data('_komerce_order_no', $order_no);
            $wc_order->update_meta_data('_komerce_order_id', $order_id);
            $wc_order->save_meta_data();
            $wc_order->add_order_note(sprintf('[Komerce] Order berhasil dibuat. No: %s', $order_no));
        }
        else {
            $msg = $result['meta']['message'] ?? 'Unknown error';
            $wc_order->add_order_note(sprintf('[Komerce] Gagal membuat order: %s', $msg));
        }
    }

    /**
     * Build Komerce order payload from WooCommerce order
     */
    public function build_order_payload($wc_order, $settings)
    {
        // Get destination ID via WC Order meta API (HPOS compatible)
        $receiver_dest_id = $wc_order->get_meta('_komerce_receiver_destination_id');
        if (!$receiver_dest_id) {
            return false; // Destination ID must be set during checkout
        }

        $items = array();
        $total_weight = 0;
        $total_value = 0;

        foreach ($wc_order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) {
                continue;
            }
            $weight = $product->get_weight() ? floatval($product->get_weight()) * 1000 : 500; // kg→g
            $qty = $item->get_quantity();
            $price = floatval($item->get_total());
            $total_weight += $weight * $qty;
            $total_value += $price;

            $items[] = array(
                'product_name' => $product->get_name(),
                'product_variant_name' => $item->get_variation_id() ? implode(', ', array_values($item->get_meta('pa_', false))) : '',
                'product_price' => (int)$product->get_price(),
                'product_width' => (int)($product->get_width() ?: 10),
                'product_height' => (int)($product->get_height() ?: 10),
                'product_length' => (int)($product->get_length() ?: 10),
                'product_weight' => (int)$weight,
                'qty' => $qty,
                'subtotal' => (int)$price,
            );
        }

        $shipping_methods = $wc_order->get_shipping_methods();
        $chosen_shipping = '';
        $chosen_type = '';
        $shipping_cost = (int)$wc_order->get_shipping_total();

        foreach ($shipping_methods as $method) {
            $meta = $method->get_meta_data();
            $chosen_shipping = $method->get_meta('komerce_courier') ?: strtoupper($method->get_method_id());
            $chosen_type = $method->get_meta('komerce_service') ?: '';
            break;
        }

        $payment_method = strtoupper($wc_order->get_payment_method_title());
        $is_cod = (strpos($payment_method, 'COD') !== false || strpos($payment_method, 'TUNAI') !== false);
        $grand_total = (int)$wc_order->get_total();

        return array(
            'order_date' => current_time('Y-m-d H:i:s'),
            'brand_name' => $settings['shipper_name'] ?? get_bloginfo('name'),
            'shipper_name' => $settings['shipper_name'] ?? get_bloginfo('name'),
            'shipper_phone' => $settings['shipper_phone'] ?? '',
            'shipper_destination_id' => (int)($settings['shipper_dest_id'] ?? 0),
            'shipper_address' => $settings['shipper_address'] ?? '',
            'shipper_email' => $settings['shipper_email'] ?? get_bloginfo('admin_email'),
            'receiver_name' => $wc_order->get_shipping_first_name() . ' ' . $wc_order->get_shipping_last_name(),
            'receiver_phone' => $wc_order->get_billing_phone(),
            'receiver_destination_id' => (int)$receiver_dest_id,
            'receiver_address' => trim($wc_order->get_shipping_address_1() . ' ' . $wc_order->get_shipping_address_2()),
            'shipping' => $chosen_shipping,
            'shipping_type' => $chosen_type,
            'shipping_cost' => $shipping_cost,
            'shipping_cashback' => 0,
            'payment_method' => $is_cod ? 'COD' : 'BANK TRANSFER',
            'service_fee' => 0,
            'additional_cost' => 0,
            'grand_total' => $grand_total,
            'cod_value' => $is_cod ? $grand_total : 0,
            'insurance_value' => 0,
            'order_details' => $items,
        );
    }

    /**
     * Add Komerce info meta box on WC order edit screen
     */
    public function add_order_meta_box()
    {
        // Support both classic CPT orders and HPOS orders
        $screens = array('shop_order', 'woocommerce_page_wc-orders');
        foreach ($screens as $screen) {
            add_meta_box(
                'komerce-order-info',
                '🚚 RajaOngkir Komerce',
                array($this, 'render_order_meta_box'),
                $screen,
                'side',
                'default'
            );
        }
    }

    /**
     * Render meta box content on WC order edit screen
     */
    public function render_order_meta_box($post_or_order)
    {
        // Resolve to WC_Order (works for both classic and HPOS)
        if (is_a($post_or_order, 'WP_Post')) {
            $wc_order = wc_get_order($post_or_order->ID);
        }
        elseif (is_a($post_or_order, 'WC_Order')) {
            $wc_order = $post_or_order;
        }
        else {
            $wc_order = wc_get_order(absint($post_or_order));
        }
        if (!$wc_order)
            return;

        $order_id = $wc_order->get_id();
        $order_no = $wc_order->get_meta('_komerce_order_no');
        $awb = $wc_order->get_meta('_komerce_awb');
        $pickup_id = $wc_order->get_meta('_komerce_pickup_id');

        echo '<table class="widefat" style="border:none">';
        echo '<tr><th>' . esc_html__('Order No', 'rajaongkir-komerce') . '</th><td>' . ($order_no ? esc_html($order_no) : '<em>Belum dibuat</em>') . '</td></tr>';
        echo '<tr><th>' . esc_html__('AWB', 'rajaongkir-komerce') . '</th><td>' . ($awb ? esc_html($awb) : '<em>-</em>') . '</td></tr>';
        echo '<tr><th>' . esc_html__('Pickup ID', 'rajaongkir-komerce') . '</th><td>' . ($pickup_id ? esc_html($pickup_id) : '<em>-</em>') . '</td></tr>';
        echo '</table>';

        if ($order_no) {
            $tracking_url = admin_url('admin.php?page=komerce-tracking&awb=' . urlencode($awb));
            echo '<p style="margin-top:8px">';
            printf(
                '<a class="button button-small" href="%s">%s</a> ',
                esc_url(admin_url('admin.php?page=komerce-orders&action=detail&order_no=' . urlencode($order_no))),
                esc_html__('Detail', 'rajaongkir-komerce')
            );
            if ($awb) {
                printf(
                    '<a class="button button-small" href="%s" target="_blank">%s</a>',
                    esc_url($tracking_url),
                    esc_html__('Tracking', 'rajaongkir-komerce')
                );
            }
            echo '</p>';
        }
        else {
            printf(
                '<p><a class="button button-primary button-small" href="%s">%s</a></p>',
                esc_url(add_query_arg(array('action' => 'komerce_create_order', 'wc_order_id' => $order_id, '_wpnonce' => wp_create_nonce('komerce_create_order_' . $order_id)), admin_url('admin-post.php'))),
                esc_html__('Buat Order Komerce', 'rajaongkir-komerce')
            );
        }
    }

    /**
     * Handle manual create-order action from meta box button
     */
    public function handle_manual_create_order()
    {
        $wc_order_id = intval($_GET['wc_order_id'] ?? 0);
        check_admin_referer('komerce_create_order_' . $wc_order_id);

        $wc_order = wc_get_order($wc_order_id);
        if (!$wc_order) {
            wp_die('Order tidak ditemukan.');
        }

        $settings = get_option('komerce_settings', array());
        $payload = $this->build_order_payload($wc_order, $settings);

        if (!$payload) {
            wp_safe_redirect(add_query_arg('komerce_msg', 'missing_data', wp_get_referer()));
            exit;
        }

        $api = new Komerce_API();
        $result = $api->create_order($payload);

        if (isset($result['meta']['code']) && in_array($result['meta']['code'], array(200, 201))) {
            $order_no = $result['data']['order_no'] ?? '';
            $wc_order->update_meta_data('_komerce_order_no', $order_no);
            $wc_order->update_meta_data('_komerce_order_id', $result['data']['order_id'] ?? '');
            $wc_order->save_meta_data();
            $wc_order->add_order_note('[Komerce] Order manual dibuat. No: ' . $order_no);
            wp_safe_redirect(add_query_arg('komerce_msg', 'order_created', wp_get_referer()));
        }
        else {
            $error_msg = $result['meta']['message'] ?? 'Unknown error';
            $wc_order->add_order_note('[Komerce] Gagal buat order: ' . $error_msg);
            wp_safe_redirect(add_query_arg('komerce_msg', 'order_failed', wp_get_referer()));
        }
        exit;
    }
}

// Register manual create order action
add_action('admin_post_komerce_create_order', function () {
    $manager = new Komerce_Order();
    $manager->handle_manual_create_order();
});
