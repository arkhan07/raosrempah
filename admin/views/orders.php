<?php
/**
 * Admin View: Orders Page
 */
if (!defined('ABSPATH'))
    exit;

$action = sanitize_text_field($_GET['action'] ?? '');
$order_no = sanitize_text_field($_GET['order_no'] ?? '');

// ── Detail View ──────────────────────────────────────────────────────────────
if ($action === 'detail' && $order_no):
    $api = new Komerce_API();
    $result = $api->get_order_detail($order_no);
    $order = $result['data'] ?? null;
    $meta = $result['meta'] ?? array();
?>
<div class="wrap komerce-wrap">
    <div class="komerce-header">
        <h1>📦 Detail Order: <?php echo esc_html($order_no); ?></h1>
        <a href="<?php echo esc_url(admin_url('admin.php?page=komerce-orders')); ?>" class="button">← Kembali</a>
    </div>

    <?php if (!$order): ?>
        <div class="notice notice-error"><p>❌ Gagal mengambil detail: <?php echo esc_html($meta['message'] ?? 'Error'); ?></p></div>
    <?php
    else: ?>
        <div class="komerce-detail-grid">
            <div class="komerce-card">
                <h3>📋 Info Pesanan</h3>
                <table class="komerce-detail-table">
                    <tr><th>No. Order</th><td><?php echo esc_html($order['order_no'] ?? '-'); ?></td></tr>
                    <tr><th>AWB</th><td><strong><?php echo esc_html($order['awb'] ?? '-'); ?></strong></td></tr>
                    <tr><th>Status</th><td><span class="komerce-badge badge-status"><?php echo esc_html($order['order_status'] ?? '-'); ?></span></td></tr>
                    <tr><th>Tanggal</th><td><?php echo esc_html($order['order_date'] ?? '-'); ?></td></tr>
                    <tr><th>Kurir</th><td><?php echo esc_html(($order['shipping'] ?? '') . ' ' . ($order['shipping_type'] ?? '')); ?></td></tr>
                    <tr><th>Metode Bayar</th><td><?php echo esc_html($order['payment_method'] ?? '-'); ?></td></tr>
                </table>
            </div>

            <div class="komerce-card">
                <h3>🏪 Pengirim (Shipper)</h3>
                <table class="komerce-detail-table">
                    <tr><th>Nama</th><td><?php echo esc_html($order['shipper_name'] ?? '-'); ?></td></tr>
                    <tr><th>Telepon</th><td><?php echo esc_html($order['shipper_phone'] ?? '-'); ?></td></tr>
                    <tr><th>Alamat</th><td><?php echo esc_html($order['shipper_address'] ?? '-'); ?></td></tr>
                </table>
            </div>

            <div class="komerce-card">
                <h3>🏠 Penerima (Receiver)</h3>
                <table class="komerce-detail-table">
                    <tr><th>Nama</th><td><?php echo esc_html($order['receiver_name'] ?? '-'); ?></td></tr>
                    <tr><th>Telepon</th><td><?php echo esc_html($order['receiver_phone'] ?? '-'); ?></td></tr>
                    <tr><th>Alamat</th><td><?php echo esc_html($order['receiver_address'] ?? '-'); ?></td></tr>
                </table>
            </div>

            <div class="komerce-card">
                <h3>💰 Rincian Biaya</h3>
                <table class="komerce-detail-table">
                    <tr><th>Ongkir</th><td>Rp <?php echo number_format($order['shipping_cost'] ?? 0, 0, ',', '.'); ?></td></tr>
                    <tr><th>Cashback</th><td>Rp <?php echo number_format($order['shipping_cashback'] ?? 0, 0, ',', '.'); ?></td></tr>
                    <tr><th>Service Fee</th><td>Rp <?php echo number_format($order['service_fee'] ?? 0, 0, ',', '.'); ?></td></tr>
                    <tr><th>Asuransi</th><td>Rp <?php echo number_format($order['insurance_value'] ?? 0, 0, ',', '.'); ?></td></tr>
                    <tr><th><strong>Total</strong></th><td><strong>Rp <?php echo number_format($order['grand_total'] ?? 0, 0, ',', '.'); ?></strong></td></tr>
                    <tr><th>COD Value</th><td>Rp <?php echo number_format($order['cod_value'] ?? 0, 0, ',', '.'); ?></td></tr>
                </table>
            </div>
        </div>

        <!-- Products -->
        <?php if (!empty($order['order_details'])): ?>
        <div class="komerce-card" style="margin-top:16px">
            <h3>🛍️ Produk</h3>
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr><th>Produk</th><th>Varian</th><th>Berat (g)</th><th>Harga</th><th>Qty</th><th>Subtotal</th></tr>
                </thead>
                <tbody>
                <?php foreach ($order['order_details'] as $item): ?>
                    <tr>
                        <td><?php echo esc_html($item['product_name'] ?? '-'); ?></td>
                        <td><?php echo esc_html($item['product_variant_name'] ?? '-'); ?></td>
                        <td><?php echo esc_html($item['product_weight'] ?? '-'); ?></td>
                        <td>Rp <?php echo number_format($item['product_price'] ?? 0, 0, ',', '.'); ?></td>
                        <td><?php echo esc_html($item['qty'] ?? 0); ?></td>
                        <td>Rp <?php echo number_format($item['subtotal'] ?? 0, 0, ',', '.'); ?></td>
                    </tr>
                <?php
            endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        endif; ?>

        <!-- Actions -->
        <div class="komerce-action-bar">
            <?php if ($order['awb']): ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=komerce-tracking&awb=' . urlencode($order['awb']) . '&shipping=' . urlencode($order['shipping'] ?? ''))); ?>" class="button button-primary">🔍 Tracking AWB</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=komerce-label&order_no=' . urlencode($order_no))); ?>" class="button">🖨️ Cetak Label</a>
            <?php
        endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline" onsubmit="return confirm('Yakin ingin membatalkan order ini?')">
                <?php wp_nonce_field('komerce_cancel_order'); ?>
                <input type="hidden" name="action" value="komerce_cancel_order">
                <input type="hidden" name="order_no" value="<?php echo esc_attr($order_no); ?>">
                <button type="submit" class="button komerce-btn-danger">❌ Cancel Order</button>
            </form>
        </div>
    <?php
    endif; ?>
</div>

<?php
// ── List View ────────────────────────────────────────────────────────────────
else:
    $cancel_status = sanitize_text_field($_GET['cancel'] ?? '');
    $cancel_msg = sanitize_text_field($_GET['msg'] ?? '');
    // Get WooCommerce orders that have Komerce order_no
    $wc_orders = wc_get_orders(array(
        'meta_key' => '_komerce_order_no',
        'meta_compare' => '!=',
        'meta_value' => '',
        'limit' => 50,
        'orderby' => 'date',
        'order' => 'DESC',
    ));
?>
<div class="wrap komerce-wrap">
    <div class="komerce-header">
        <h1>📦 Manajemen Order Komerce</h1>
        <a href="<?php echo esc_url(admin_url('admin.php?page=komerce-pickup')); ?>" class="button button-primary">🚗 Request Pickup</a>
    </div>

    <?php if ($cancel_status === 'success'): ?>
        <div class="notice notice-success is-dismissible"><p>✅ Order berhasil dibatalkan.</p></div>
    <?php
    elseif ($cancel_status === 'fail'): ?>
        <div class="notice notice-error is-dismissible"><p>❌ Gagal cancel: <?php echo esc_html(urldecode($cancel_msg)); ?></p></div>
    <?php
    endif; ?>

    <div class="komerce-card">
        <?php if (empty($wc_orders)): ?>
            <div class="komerce-empty-state">
                <span class="dashicons dashicons-cart" style="font-size:48px;color:#ccc"></span>
                <p>Belum ada order Komerce. Order akan muncul di sini setelah WooCommerce order diproses.</p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=komerce-settings')); ?>" class="button">⚙️ Periksa Pengaturan</a>
            </div>
        <?php
    else: ?>
            <table class="wp-list-table widefat fixed striped komerce-orders-table">
                <thead>
                    <tr>
                        <th>WC Order</th>
                        <th>No. Komerce</th>
                        <th>AWB</th>
                        <th>Customer</th>
                        <th>Total</th>
                        <th>Tanggal</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($wc_orders as $wc_order):
            $komerce_no = $wc_order->get_meta('_komerce_order_no');
            $awb = $wc_order->get_meta('_komerce_awb');
?>
                    <tr>
                        <td>
                            <a href="<?php echo esc_url($wc_order->get_edit_order_url()); ?>" target="_blank">
                                #<?php echo esc_html($wc_order->get_order_number()); ?>
                            </a>
                        </td>
                        <td><code><?php echo esc_html($komerce_no ?: '-'); ?></code></td>
                        <td><?php echo $awb ? '<code>' . esc_html($awb) . '</code>' : '<em style="color:#999">Belum pickup</em>'; ?></td>
                        <td><?php echo esc_html($wc_order->get_billing_first_name() . ' ' . $wc_order->get_billing_last_name()); ?></td>
                        <td>Rp <?php echo number_format($wc_order->get_total(), 0, ',', '.'); ?></td>
                        <td><?php echo esc_html($wc_order->get_date_created()->date('d M Y')); ?></td>
                        <td class="komerce-actions">
                            <?php if ($komerce_no): ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=komerce-orders&action=detail&order_no=' . urlencode($komerce_no))); ?>" class="button button-small">Detail</a>
                            <?php
            endif; ?>
                            <?php if ($awb): ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=komerce-tracking&awb=' . urlencode($awb))); ?>" class="button button-small">Track</a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=komerce-label&order_no=' . urlencode($komerce_no))); ?>" class="button button-small">Label</a>
                            <?php
            endif; ?>
                        </td>
                    </tr>
                <?php
        endforeach; ?>
                </tbody>
            </table>
        <?php
    endif; ?>
    </div>
</div>
<?php
endif; ?>
