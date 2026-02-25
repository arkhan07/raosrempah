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
        // Trigger when WC order status → processing
        add_action('woocommerce_order_status_processing', array($this, 'auto_create_komerce_order'), 10, 1);

        // Also trigger when payment gateway marks order as complete/processing
        add_action('woocommerce_payment_complete', array($this, 'auto_create_komerce_order'), 10, 1);

        // Capture destination ID during order creation — classic & blocks checkout
        // This is more reliable than woocommerce_checkout_order_processed
        add_action('woocommerce_checkout_create_order', array($this, 'capture_destination_on_create'), 10, 2);

        // WooCommerce Blocks checkout (Store API)
        add_action('woocommerce_store_api_checkout_order_processed', array($this, 'on_blocks_checkout_order'), 10, 1);

        // Add Komerce meta box to WC order edit screen
        add_action('add_meta_boxes', array($this, 'add_order_meta_box'));

        // AJAX: inline search + create from meta box
        add_action('wp_ajax_komerce_inline_set_dest', array($this, 'ajax_inline_set_dest'));
    }

    // ─── Checkout Hooks ───────────────────────────────────────────────────────

    /**
     * Hook: woocommerce_checkout_create_order
     * Fires during order creation for CLASSIC checkout. $_POST still available.
     * More reliable than woocommerce_checkout_order_processed.
     */
    public function capture_destination_on_create($order, $data)
    {
        // Priority: POST field > session
        $dest_id = intval($_POST['komerce_receiver_destination_id'] ?? 0);
        if (!$dest_id && WC()->session) {
            $dest_id = intval(WC()->session->get('komerce_receiver_destination_id') ?? 0);
        }
        if ($dest_id) {
            $order->update_meta_data('_komerce_receiver_destination_id', $dest_id);
            if (WC()->session) {
                WC()->session->set('komerce_receiver_destination_id', null);
            }
            error_log('[Komerce] capture_destination_on_create: saved dest_id=' . $dest_id . ' for new order');
        }
    }

    /**
     * Hook: woocommerce_store_api_checkout_order_processed (WooCommerce Blocks)
     * Fires after blocks checkout creates the order.
     */
    public function on_blocks_checkout_order($order)
    {
        $this->auto_create_komerce_order($order->get_id());
    }

    // ─── Auto-Create Komerce Order ────────────────────────────────────────────

    /**
     * Auto-create Komerce order when WC order status = "processing"
     */
    public function auto_create_komerce_order($wc_order_id)
    {
        $settings = get_option('komerce_settings', array());
        if (($settings['auto_create_order'] ?? 'yes') !== 'yes') {
            return;
        }

        $wc_order = wc_get_order($wc_order_id);
        if (!$wc_order) {
            return;
        }

        // Skip if already created
        if ($wc_order->get_meta('_komerce_order_no')) {
            return;
        }

        // Check API configured first
        $api = new Komerce_API();
        if (!$api->is_configured()) {
            $wc_order->update_meta_data('_komerce_last_error', 'API key belum dikonfigurasi di pengaturan plugin.');
            $wc_order->add_order_note('[Komerce] Gagal: API key belum dikonfigurasi.');
            $wc_order->save();
            return;
        }

        $payload = $this->build_order_payload($wc_order, $settings);
        if (!$payload) {
            $err = $wc_order->get_meta('_komerce_last_error') ?: 'Data pengiriman tidak lengkap.';
            $wc_order->add_order_note('[Komerce] Gagal buat order: ' . $err . ' Buka order ini dan gunakan tombol "Buat Order Komerce" untuk pilih tujuan pengiriman secara manual.');
            $wc_order->save();
            return;
        }

        $result = $api->create_order($payload);
        error_log('[Komerce] create_order WC#' . $wc_order_id . ' payload_dest=' . ($payload['receiver_destination_id'] ?? 0) . ' result: ' . wp_json_encode($result));

        $code = (int)($result['meta']['code'] ?? 0);
        if (in_array($code, array(200, 201), true)) {
            $order_no = $result['data']['order_no'] ?? '';
            $order_id = $result['data']['order_id'] ?? '';
            $wc_order->update_meta_data('_komerce_order_no', $order_no);
            $wc_order->update_meta_data('_komerce_order_id', $order_id);
            $wc_order->delete_meta_data('_komerce_last_error');
            $wc_order->save_meta_data();
            $wc_order->add_order_note(sprintf('[Komerce] ✅ Order berhasil dibuat. No: %s', $order_no));
        } else {
            $msg = $result['meta']['message'] ?? 'Unknown error';
            $full_err = sprintf('API error (kode %d): %s', $code, $msg);
            $wc_order->update_meta_data('_komerce_last_error', $full_err);
            $wc_order->add_order_note(sprintf('[Komerce] ❌ Gagal membuat order — %s', $full_err));
            $wc_order->save();
        }
    }

    // ─── Order Payload ────────────────────────────────────────────────────────

    /**
     * Build Komerce order payload from WooCommerce order
     */
    public function build_order_payload($wc_order, $settings)
    {
        // Try to resolve destination ID: saved meta → auto-lookup from address
        $receiver_dest_id = $this->resolve_receiver_destination($wc_order);
        if (!$receiver_dest_id) {
            $wc_order->update_meta_data('_komerce_last_error', 'Destination ID tujuan tidak ditemukan. Kode pos atau kota pembeli tidak cocok di database Komerce.');
            $wc_order->save();
            return false;
        }

        $items = array();
        foreach ($wc_order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) {
                continue;
            }
            $weight_g = $product->get_weight() ? (int)(floatval($product->get_weight()) * 1000) : 500;
            $qty      = $item->get_quantity();
            $price    = (int)floatval($item->get_total());

            $items[] = array(
                'product_name'         => $product->get_name(),
                'product_variant_name' => $item->get_variation_id() ? $this->get_variation_label($item) : '',
                'product_price'        => (int)$product->get_price(),
                'product_width'        => (int)($product->get_width()  ?: 10),
                'product_height'       => (int)($product->get_height() ?: 10),
                'product_length'       => (int)($product->get_length() ?: 10),
                'product_weight'       => $weight_g,
                'qty'                  => $qty,
                'subtotal'             => $price,
            );
        }

        if (empty($items)) {
            $wc_order->update_meta_data('_komerce_last_error', 'Tidak ada produk valid dalam order.');
            $wc_order->save();
            return false;
        }

        // Shipping method meta
        $chosen_shipping = '';
        $chosen_type     = '';
        $shipping_cost   = (int)$wc_order->get_shipping_total();
        foreach ($wc_order->get_shipping_methods() as $method) {
            $chosen_shipping = $method->get_meta('komerce_courier') ?: strtoupper($method->get_method_id());
            $chosen_type     = $method->get_meta('komerce_service')  ?: '';
            break;
        }

        // Payment
        $payment_title = strtoupper($wc_order->get_payment_method_title());
        $is_cod        = str_contains($payment_title, 'COD') || str_contains($payment_title, 'TUNAI');
        $grand_total   = (int)$wc_order->get_total();

        // Receiver info — shipping fields with billing fallback
        $receiver_name = trim($wc_order->get_shipping_first_name() . ' ' . $wc_order->get_shipping_last_name());
        if (empty($receiver_name)) {
            $receiver_name = trim($wc_order->get_billing_first_name() . ' ' . $wc_order->get_billing_last_name());
        }
        $receiver_address = trim($wc_order->get_shipping_address_1() . ' ' . $wc_order->get_shipping_address_2());
        if (empty($receiver_address)) {
            $receiver_address = trim($wc_order->get_billing_address_1() . ' ' . $wc_order->get_billing_address_2());
        }

        return array(
            'order_date'               => current_time('Y-m-d H:i:s'),
            'brand_name'               => $settings['shipper_name'] ?? get_bloginfo('name'),
            'shipper_name'             => $settings['shipper_name'] ?? get_bloginfo('name'),
            'shipper_phone'            => $settings['shipper_phone'] ?? '',
            'shipper_destination_id'   => (int)($settings['shipper_dest_id'] ?? 0),
            'shipper_address'          => $settings['shipper_address'] ?? '',
            'origin_pin_point'         => '',
            'shipper_email'            => $settings['shipper_email'] ?? get_bloginfo('admin_email'),
            'receiver_name'            => $receiver_name,
            'receiver_phone'           => $wc_order->get_billing_phone(),
            'receiver_destination_id'  => $receiver_dest_id,
            'receiver_address'         => $receiver_address,
            'destination_pin_point'    => '',
            'shipping'                 => $chosen_shipping,
            'shipping_type'            => $chosen_type,
            'shipping_cost'            => $shipping_cost,
            'shipping_cashback'        => 0,
            'payment_method'           => $is_cod ? 'COD' : 'BANK TRANSFER',
            'service_fee'              => 0,
            'additional_cost'          => 0,
            'grand_total'              => $grand_total,
            'cod_value'                => $is_cod ? $grand_total : 0,
            'insurance_value'          => 0,
            'order_details'            => $items,
        );
    }

    /**
     * Resolve receiver destination ID:
     * 1. Saved order meta  (set during checkout via destination search)
     * 2. Auto-lookup via postal code (most specific)
     * 3. Auto-lookup via city name
     */
    private function resolve_receiver_destination($wc_order)
    {
        // 1. Saved meta
        $dest_id = (int)$wc_order->get_meta('_komerce_receiver_destination_id');
        if ($dest_id > 0) {
            return $dest_id;
        }

        $api = new Komerce_API();
        if (!$api->is_configured()) {
            return 0;
        }

        // 2. Try postal code first (most accurate match)
        $postcode = trim($wc_order->get_shipping_postcode() ?: $wc_order->get_billing_postcode());
        if ($postcode) {
            $result = $api->search_destination($postcode);
            $dest_id = $this->first_destination_id($result);
            if ($dest_id) {
                $wc_order->update_meta_data('_komerce_receiver_destination_id', $dest_id);
                $wc_order->save();
                error_log('[Komerce] Auto-resolved dest_id=' . $dest_id . ' via postcode=' . $postcode . ' for WC#' . $wc_order->get_id());
                $wc_order->add_order_note(sprintf('[Komerce] Tujuan otomatis terdeteksi dari kode pos %s (ID: %d). Harap verifikasi keakuratannya.', $postcode, $dest_id));
                return $dest_id;
            }
        }

        // 3. Try city name
        $city = trim($wc_order->get_shipping_city() ?: $wc_order->get_billing_city());
        if ($city) {
            $result = $api->search_destination($city);
            $dest_id = $this->first_destination_id($result);
            if ($dest_id) {
                $wc_order->update_meta_data('_komerce_receiver_destination_id', $dest_id);
                $wc_order->save();
                error_log('[Komerce] Auto-resolved dest_id=' . $dest_id . ' via city=' . $city . ' for WC#' . $wc_order->get_id());
                $wc_order->add_order_note(sprintf('[Komerce] Tujuan otomatis terdeteksi dari kota "%s" (ID: %d). Harap verifikasi keakuratannya.', $city, $dest_id));
                return $dest_id;
            }
        }

        error_log('[Komerce] Could not resolve destination for WC#' . $wc_order->get_id() . ' postcode=' . $postcode . ' city=' . $city);
        return 0;
    }

    /**
     * Extract first valid destination ID from API search result
     */
    private function first_destination_id($result)
    {
        if (empty($result['data']) || !is_array($result['data'])) {
            return 0;
        }
        foreach ($result['data'] as $item) {
            $id = (int)($item['id'] ?? 0);
            if ($id > 0) {
                return $id;
            }
        }
        return 0;
    }

    /**
     * Get product variation label from order item meta data
     */
    private function get_variation_label($item)
    {
        $attrs = array();
        foreach ($item->get_meta_data() as $meta) {
            $key = $meta->key;
            if ((strpos($key, 'pa_') === 0 || strpos($key, 'attribute_') === 0) && !empty($meta->value)) {
                $attrs[] = $meta->value;
            }
        }
        return implode(', ', $attrs);
    }

    // ─── Admin Meta Box ────────────────────────────────────────────────────────

    public function add_order_meta_box()
    {
        foreach (array('shop_order', 'woocommerce_page_wc-orders') as $screen) {
            add_meta_box('komerce-order-info', '🚚 RajaOngkir Komerce', array($this, 'render_order_meta_box'), $screen, 'side', 'default');
        }
    }

    public function render_order_meta_box($post_or_order)
    {
        if (is_a($post_or_order, 'WP_Post')) {
            $wc_order = wc_get_order($post_or_order->ID);
        } elseif (is_a($post_or_order, 'WC_Order')) {
            $wc_order = $post_or_order;
        } else {
            $wc_order = wc_get_order(absint($post_or_order));
        }
        if (!$wc_order) return;

        $order_id  = $wc_order->get_id();
        $order_no  = $wc_order->get_meta('_komerce_order_no');
        $awb       = $wc_order->get_meta('_komerce_awb');
        $pickup_id = $wc_order->get_meta('_komerce_pickup_id');
        $last_err  = $wc_order->get_meta('_komerce_last_error');
        $dest_id   = (int)$wc_order->get_meta('_komerce_receiver_destination_id');

        if ($order_no) {
            // Order sudah dibuat di Komerce
            echo '<table class="widefat" style="border:none">';
            echo '<tr><th>Order No</th><td><code>' . esc_html($order_no) . '</code></td></tr>';
            echo '<tr><th>AWB</th><td>' . ($awb ? '<code>' . esc_html($awb) . '</code>' : '<em>-</em>') . '</td></tr>';
            echo '<tr><th>Pickup ID</th><td>' . ($pickup_id ? esc_html($pickup_id) : '<em>-</em>') . '</td></tr>';
            echo '</table>';
            echo '<div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:10px">';
            printf('<a class="button button-small" href="%s">Detail</a>', esc_url(admin_url('admin.php?page=komerce-orders&action=detail&order_no=' . urlencode($order_no))));
            if ($awb) {
                printf('<a class="button button-small" href="%s">Track</a>', esc_url(admin_url('admin.php?page=komerce-tracking&awb=' . urlencode($awb))));
            }
            echo '</div>';
        } else {
            // Belum dibuat — tampilkan diagnostik + form buat order
            $api_ok = (new Komerce_API())->is_configured();

            // Status diagnostic
            echo '<div style="font-size:12px;margin-bottom:10px">';
            echo '<div style="display:flex;align-items:center;gap:6px;padding:4px 0">';
            echo ($api_ok ? '✅' : '❌') . ' <span>API Key ' . ($api_ok ? 'terkonfigurasi' : '<strong>belum diset</strong>') . '</span>';
            echo '</div>';
            echo '<div style="display:flex;align-items:center;gap:6px;padding:4px 0">';
            echo ($dest_id ? '✅' : '⚠️') . ' <span>Tujuan: ' . ($dest_id ? '<code>ID ' . $dest_id . '</code>' : '<strong>belum tersimpan</strong>') . '</span>';
            echo '</div>';
            echo '</div>';

            if ($last_err) {
                echo '<div style="background:#fef2f2;border-left:3px solid #ef4444;padding:7px 10px;margin-bottom:10px;font-size:11px;color:#991b1b;border-radius:4px">';
                echo esc_html($last_err);
                echo '</div>';
            }

            // Form buat order + inline destination search
            $nonce = wp_create_nonce('komerce_create_order_' . $order_id);
            ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="kmc-create-form-<?php echo $order_id; ?>">
                <input type="hidden" name="action" value="komerce_create_order">
                <input type="hidden" name="wc_order_id" value="<?php echo $order_id; ?>">
                <input type="hidden" name="_wpnonce" value="<?php echo $nonce; ?>">
                <input type="hidden" name="override_dest_id" id="kmc-dest-val-<?php echo $order_id; ?>" value="">

                <?php if (!$dest_id): ?>
                <!-- Inline destination search to set/override destination -->
                <p style="font-size:11px;color:#666;margin:0 0 5px">Set tujuan pengiriman (opsional — akan dicari otomatis jika kosong):</p>
                <div style="position:relative">
                    <input type="text" id="kmc-dest-input-<?php echo $order_id; ?>"
                           placeholder="Cari kelurahan / kota..."
                           autocomplete="off"
                           style="width:100%;font-size:12px;padding:5px 8px;border:1px solid #ddd;border-radius:4px;box-sizing:border-box">
                    <ul id="kmc-dest-list-<?php echo $order_id; ?>"
                        style="display:none;position:absolute;left:0;right:0;z-index:9999;list-style:none;margin:0;padding:0;background:#fff;border:1px solid #ddd;border-radius:0 0 4px 4px;max-height:160px;overflow-y:auto;font-size:12px"></ul>
                </div>
                <p id="kmc-dest-label-<?php echo $order_id; ?>" style="font-size:11px;color:#2271b1;margin:4px 0 8px;min-height:16px"></p>
                <?php endif; ?>

                <button type="submit" class="button button-primary button-small"
                        style="width:100%"
                        <?php echo (!$api_ok ? 'disabled title="Konfigurasi API key dulu"' : ''); ?>>
                    🚚 Buat Order Komerce
                </button>
            </form>

            <?php if (!$dest_id): ?>
            <script>
            (function($){
                var uid = <?php echo (int)$order_id; ?>;
                var $inp   = $('#kmc-dest-input-' + uid);
                var $list  = $('#kmc-dest-list-' + uid);
                var $val   = $('#kmc-dest-val-' + uid);
                var $lbl   = $('#kmc-dest-label-' + uid);
                var timer;

                $inp.on('input', function(){
                    clearTimeout(timer);
                    var kw = $(this).val().trim();
                    if (kw.length < 3) { $list.hide().empty(); return; }
                    timer = setTimeout(function(){
                        $list.html('<li style="padding:7px 10px;color:#999">Mencari...</li>').show();
                        $.post(KomerceAdmin.ajax_url, {
                            action: 'komerce_admin_search_dest',
                            nonce: KomerceAdmin.nonce,
                            keyword: kw
                        }, function(res){
                            $list.empty();
                            var items = (res && res.data) ? res.data : [];
                            if (!items.length) { $list.html('<li style="padding:7px 10px;color:#999">Tidak ditemukan</li>').show(); return; }
                            items.slice(0, 8).forEach(function(item){
                                var lbl = item.label || [item.subdistrict_name, item.district_name, item.city_name].filter(Boolean).join(', ');
                                $('<li style="padding:7px 10px;cursor:pointer;border-bottom:1px solid #f5f5f5">')
                                    .text(lbl + (item.zip_code ? ' (' + item.zip_code + ')' : ''))
                                    .data('id', item.id).data('lbl', lbl)
                                    .appendTo($list);
                            });
                            $list.show();
                        });
                    }, 350);
                });

                $list.on('click', 'li', function(){
                    var id = $(this).data('id'), lbl = $(this).data('lbl');
                    $val.val(id);
                    $inp.val(lbl);
                    $lbl.html('✅ ' + lbl + ' (ID: ' + id + ')');
                    $list.hide().empty();
                });

                $(document).on('click', function(e){
                    if (!$(e.target).closest('#kmc-dest-input-' + uid + ',#kmc-dest-list-' + uid).length) $list.hide();
                });

                // Hover style
                $list.on('mouseenter', 'li', function(){ $(this).css('background','#fff8f0'); })
                     .on('mouseleave', 'li', function(){ $(this).css('background',''); });
            })(jQuery);
            </script>
            <?php endif; ?>
            <?php
        }
    }

    // ─── Manual Create Order ──────────────────────────────────────────────────

    /**
     * Handle "Buat Order Komerce" button from meta box (POST form)
     */
    public function handle_manual_create_order()
    {
        // Support both GET (legacy link) and POST (new form)
        $wc_order_id = intval($_REQUEST['wc_order_id'] ?? 0);
        check_admin_referer('komerce_create_order_' . $wc_order_id);

        $wc_order = wc_get_order($wc_order_id);
        if (!$wc_order) {
            wp_die('Order tidak ditemukan.');
        }

        // If admin provided an override destination from inline search, save it first
        $override_dest = intval($_REQUEST['override_dest_id'] ?? 0);
        if ($override_dest > 0) {
            $wc_order->update_meta_data('_komerce_receiver_destination_id', $override_dest);
            $wc_order->save();
        }

        $settings = get_option('komerce_settings', array());
        $payload  = $this->build_order_payload($wc_order, $settings);

        if (!$payload) {
            $wc_order->add_order_note('[Komerce] Gagal buat order manual: ' . ($wc_order->get_meta('_komerce_last_error') ?: 'data tidak lengkap'));
            $wc_order->save();
            wp_safe_redirect(add_query_arg('komerce_msg', 'missing_data', wp_get_referer()));
            exit;
        }

        $api    = new Komerce_API();
        $result = $api->create_order($payload);
        error_log('[Komerce] manual create_order WC#' . $wc_order_id . ' result: ' . wp_json_encode($result));

        $code = (int)($result['meta']['code'] ?? 0);
        if (in_array($code, array(200, 201), true)) {
            $order_no = $result['data']['order_no'] ?? '';
            $wc_order->update_meta_data('_komerce_order_no', $order_no);
            $wc_order->update_meta_data('_komerce_order_id', $result['data']['order_id'] ?? '');
            $wc_order->delete_meta_data('_komerce_last_error');
            $wc_order->save_meta_data();
            $wc_order->add_order_note('[Komerce] ✅ Order manual dibuat. No: ' . $order_no);
            wp_safe_redirect(add_query_arg('komerce_msg', 'order_created', wp_get_referer()));
        } else {
            $error_msg = $result['meta']['message'] ?? 'Unknown error';
            $full_err  = sprintf('API error (kode %d): %s', $code, $error_msg);
            $wc_order->update_meta_data('_komerce_last_error', $full_err);
            $wc_order->add_order_note('[Komerce] ❌ Gagal buat order manual: ' . $full_err);
            $wc_order->save();
            wp_safe_redirect(add_query_arg('komerce_msg', 'order_failed', wp_get_referer()));
        }
        exit;
    }

    // ─── AJAX ──────────────────────────────────────────────────────────────────

    /**
     * AJAX: set destination ID for an order from the meta box inline search
     */
    public function ajax_inline_set_dest()
    {
        check_ajax_referer('komerce_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        $order_id = intval($_POST['order_id'] ?? 0);
        $dest_id  = intval($_POST['dest_id'] ?? 0);
        $wc_order = wc_get_order($order_id);
        if (!$wc_order || !$dest_id) {
            wp_send_json_error(array('message' => 'Invalid params'));
        }
        $wc_order->update_meta_data('_komerce_receiver_destination_id', $dest_id);
        $wc_order->delete_meta_data('_komerce_last_error');
        $wc_order->save();
        wp_send_json_success(array('message' => 'Destination ID ' . $dest_id . ' disimpan.'));
    }
}

// Register manual create order action
add_action('admin_post_komerce_create_order', function () {
    (new Komerce_Order())->handle_manual_create_order();
});
