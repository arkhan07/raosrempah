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
<div class="wrap">
    <!-- Header -->
    <div class="tw-flex tw-items-center tw-justify-between tw-py-5 tw-mb-6" style="border-bottom:2px solid #f0f0f0">
        <div class="tw-flex tw-items-center tw-gap-3">
            <span class="dashicons dashicons-package" style="font-size:24px;width:24px;height:24px;color:#e65c00"></span>
            <h1 class="tw-text-xl tw-font-bold tw-text-gray-900 tw-m-0">
                Detail Order: <code class="tw-text-base" style="color:#e65c00"><?php echo esc_html($order_no); ?></code>
            </h1>
        </div>
        <a href="<?php echo esc_url(admin_url('admin.php?page=komerce-orders')); ?>" class="button">← Kembali ke Daftar</a>
    </div>

    <?php if (!$order): ?>
        <div class="notice notice-error">
            <p>❌ Gagal mengambil detail: <?php echo esc_html($meta['message'] ?? 'Error'); ?></p>
        </div>
    <?php else: ?>

        <!-- Info Grid -->
        <div class="tw-grid tw-grid-cols-2 tw-gap-4 tw-mb-4">

            <!-- Order Info -->
            <div class="tw-bg-white tw-rounded-xl tw-p-5 tw-shadow-sm" style="border:1px solid #e5e7eb">
                <h3 class="tw-text-sm tw-font-bold tw-uppercase tw-tracking-wider tw-text-gray-500 tw-m-0 tw-mb-4 tw-pb-3" style="border-bottom:1px solid #f3f4f6">📋 Info Pesanan</h3>
                <table class="komerce-detail-table">
                    <tr><th>No. Order</th><td><?php echo esc_html($order['order_no'] ?? '-'); ?></td></tr>
                    <tr><th>AWB</th><td><strong><?php echo esc_html($order['awb'] ?? '-'); ?></strong></td></tr>
                    <tr><th>Status</th><td><span class="komerce-badge badge-status"><?php echo esc_html($order['order_status'] ?? '-'); ?></span></td></tr>
                    <tr><th>Tanggal</th><td><?php echo esc_html($order['order_date'] ?? '-'); ?></td></tr>
                    <tr><th>Kurir</th><td><?php echo esc_html(($order['shipping'] ?? '') . ' ' . ($order['shipping_type'] ?? '')); ?></td></tr>
                    <tr><th>Metode Bayar</th><td><?php echo esc_html($order['payment_method'] ?? '-'); ?></td></tr>
                </table>
            </div>

            <!-- Cost Info -->
            <div class="tw-bg-white tw-rounded-xl tw-p-5 tw-shadow-sm" style="border:1px solid #e5e7eb">
                <h3 class="tw-text-sm tw-font-bold tw-uppercase tw-tracking-wider tw-text-gray-500 tw-m-0 tw-mb-4 tw-pb-3" style="border-bottom:1px solid #f3f4f6">💰 Rincian Biaya</h3>
                <table class="komerce-detail-table">
                    <tr><th>Ongkir</th><td>Rp <?php echo number_format($order['shipping_cost'] ?? 0, 0, ',', '.'); ?></td></tr>
                    <tr><th>Cashback</th><td>Rp <?php echo number_format($order['shipping_cashback'] ?? 0, 0, ',', '.'); ?></td></tr>
                    <tr><th>Service Fee</th><td>Rp <?php echo number_format($order['service_fee'] ?? 0, 0, ',', '.'); ?></td></tr>
                    <tr><th>Asuransi</th><td>Rp <?php echo number_format($order['insurance_value'] ?? 0, 0, ',', '.'); ?></td></tr>
                    <tr>
                        <th><strong>Total</strong></th>
                        <td><strong style="color:#e65c00;font-size:15px">Rp <?php echo number_format($order['grand_total'] ?? 0, 0, ',', '.'); ?></strong></td>
                    </tr>
                    <tr><th>COD Value</th><td>Rp <?php echo number_format($order['cod_value'] ?? 0, 0, ',', '.'); ?></td></tr>
                </table>
            </div>

            <!-- Shipper Info -->
            <div class="tw-bg-white tw-rounded-xl tw-p-5 tw-shadow-sm" style="border:1px solid #e5e7eb">
                <h3 class="tw-text-sm tw-font-bold tw-uppercase tw-tracking-wider tw-text-gray-500 tw-m-0 tw-mb-4 tw-pb-3" style="border-bottom:1px solid #f3f4f6">🏪 Pengirim</h3>
                <table class="komerce-detail-table">
                    <tr><th>Nama</th><td><?php echo esc_html($order['shipper_name'] ?? '-'); ?></td></tr>
                    <tr><th>Telepon</th><td><?php echo esc_html($order['shipper_phone'] ?? '-'); ?></td></tr>
                    <tr><th>Alamat</th><td><?php echo esc_html($order['shipper_address'] ?? '-'); ?></td></tr>
                </table>
            </div>

            <!-- Receiver Info -->
            <div class="tw-bg-white tw-rounded-xl tw-p-5 tw-shadow-sm" style="border:1px solid #e5e7eb">
                <h3 class="tw-text-sm tw-font-bold tw-uppercase tw-tracking-wider tw-text-gray-500 tw-m-0 tw-mb-4 tw-pb-3" style="border-bottom:1px solid #f3f4f6">🏠 Penerima</h3>
                <table class="komerce-detail-table">
                    <tr><th>Nama</th><td><?php echo esc_html($order['receiver_name'] ?? '-'); ?></td></tr>
                    <tr><th>Telepon</th><td><?php echo esc_html($order['receiver_phone'] ?? '-'); ?></td></tr>
                    <tr><th>Alamat</th><td><?php echo esc_html($order['receiver_address'] ?? '-'); ?></td></tr>
                </table>
            </div>
        </div>

        <!-- Products Table -->
        <?php if (!empty($order['order_details'])): ?>
        <div class="tw-bg-white tw-rounded-xl tw-p-5 tw-shadow-sm tw-mb-4" style="border:1px solid #e5e7eb">
            <h3 class="tw-text-sm tw-font-bold tw-uppercase tw-tracking-wider tw-text-gray-500 tw-m-0 tw-mb-4 tw-pb-3" style="border-bottom:1px solid #f3f4f6">🛍️ Produk</h3>
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th>Produk</th><th>Varian</th><th>Berat (g)</th>
                        <th>Harga</th><th>Qty</th><th>Subtotal</th>
                    </tr>
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
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Action Bar -->
        <div class="tw-flex tw-items-center tw-gap-3 tw-py-3">
            <?php if ($order['awb']): ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=komerce-tracking&awb=' . urlencode($order['awb']) . '&shipping=' . urlencode($order['shipping'] ?? ''))); ?>"
                   class="button button-primary">🔍 Tracking AWB</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=komerce-label&order_no=' . urlencode($order_no))); ?>"
                   class="button">🖨️ Cetak Label</a>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline"
                  onsubmit="return confirm('Yakin ingin membatalkan order ini?')">
                <?php wp_nonce_field('komerce_cancel_order'); ?>
                <input type="hidden" name="action" value="komerce_cancel_order">
                <input type="hidden" name="order_no" value="<?php echo esc_attr($order_no); ?>">
                <button type="submit" class="button" style="background:#dc3232;border-color:#aa0000;color:#fff">❌ Cancel Order</button>
            </form>
        </div>

    <?php endif; ?>
</div>

<?php
// ── List View ────────────────────────────────────────────────────────────────
else:
    $cancel_status = sanitize_text_field($_GET['cancel'] ?? '');
    $cancel_msg = sanitize_text_field($_GET['msg'] ?? '');
    $wc_orders = wc_get_orders(array(
        'meta_key' => '_komerce_order_no',
        'meta_compare' => '!=',
        'meta_value' => '',
        'limit' => 50,
        'orderby' => 'date',
        'order' => 'DESC',
    ));
?>
<div class="wrap">
    <!-- Header -->
    <div class="tw-flex tw-items-center tw-justify-between tw-py-5 tw-mb-6" style="border-bottom:2px solid #f0f0f0">
        <div class="tw-flex tw-items-center tw-gap-3">
            <span class="dashicons dashicons-cart" style="font-size:24px;width:24px;height:24px;color:#e65c00"></span>
            <h1 class="tw-text-xl tw-font-bold tw-text-gray-900 tw-m-0">Manajemen Order Komerce</h1>
        </div>
        <a href="<?php echo esc_url(admin_url('admin.php?page=komerce-pickup')); ?>" class="button button-primary">
            🚗 Request Pickup
        </a>
    </div>

    <?php if ($cancel_status === 'success'): ?>
        <div class="notice notice-success is-dismissible tw-mb-4"><p>✅ Order berhasil dibatalkan.</p></div>
    <?php elseif ($cancel_status === 'fail'): ?>
        <div class="notice notice-error is-dismissible tw-mb-4"><p>❌ Gagal cancel: <?php echo esc_html(urldecode($cancel_msg)); ?></p></div>
    <?php endif; ?>

    <div class="tw-bg-white tw-rounded-xl tw-shadow-sm" style="border:1px solid #e5e7eb">
        <?php if (empty($wc_orders)): ?>
            <div class="tw-text-center tw-py-16 tw-text-gray-400">
                <span class="dashicons dashicons-cart" style="font-size:52px;width:52px;height:52px;color:#d1d5db"></span>
                <p class="tw-text-sm tw-mt-3 tw-mb-4">Belum ada order Komerce. Order akan muncul di sini setelah WooCommerce order diproses.</p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=komerce-settings')); ?>" class="button">⚙️ Periksa Pengaturan</a>
            </div>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped" style="border-radius:12px;overflow:hidden">
                <thead>
                    <tr style="background:#f9fafb">
                        <th class="tw-text-xs tw-font-semibold tw-uppercase tw-tracking-wider tw-text-gray-500" style="width:100px">WC Order</th>
                        <th class="tw-text-xs tw-font-semibold tw-uppercase tw-tracking-wider tw-text-gray-500">No. Komerce</th>
                        <th class="tw-text-xs tw-font-semibold tw-uppercase tw-tracking-wider tw-text-gray-500">AWB</th>
                        <th class="tw-text-xs tw-font-semibold tw-uppercase tw-tracking-wider tw-text-gray-500">Customer</th>
                        <th class="tw-text-xs tw-font-semibold tw-uppercase tw-tracking-wider tw-text-gray-500">Total</th>
                        <th class="tw-text-xs tw-font-semibold tw-uppercase tw-tracking-wider tw-text-gray-500">Tanggal</th>
                        <th class="tw-text-xs tw-font-semibold tw-uppercase tw-tracking-wider tw-text-gray-500" style="width:160px">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($wc_orders as $wc_order):
                    $komerce_no = $wc_order->get_meta('_komerce_order_no');
                    $awb = $wc_order->get_meta('_komerce_awb');
                ?>
                    <tr>
                        <td>
                            <a href="<?php echo esc_url($wc_order->get_edit_order_url()); ?>" target="_blank"
                               class="tw-font-semibold" style="color:#e65c00;text-decoration:none">
                                #<?php echo esc_html($wc_order->get_order_number()); ?>
                            </a>
                        </td>
                        <td>
                            <?php if ($komerce_no): ?>
                                <code class="tw-text-xs tw-bg-orange-50 tw-px-1.5 tw-py-0.5 tw-rounded" style="color:#e65c00"><?php echo esc_html($komerce_no); ?></code>
                            <?php else: ?>
                                <span class="tw-text-xs tw-text-gray-400">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($awb): ?>
                                <code class="tw-text-xs tw-bg-green-50 tw-px-1.5 tw-py-0.5 tw-rounded tw-text-green-700"><?php echo esc_html($awb); ?></code>
                            <?php else: ?>
                                <span class="tw-text-xs tw-italic tw-text-gray-400">Belum pickup</span>
                            <?php endif; ?>
                        </td>
                        <td class="tw-text-sm"><?php echo esc_html($wc_order->get_billing_first_name() . ' ' . $wc_order->get_billing_last_name()); ?></td>
                        <td class="tw-text-sm tw-font-medium">Rp <?php echo number_format($wc_order->get_total(), 0, ',', '.'); ?></td>
                        <td class="tw-text-xs tw-text-gray-500"><?php echo esc_html($wc_order->get_date_created()->date('d M Y')); ?></td>
                        <td>
                            <div class="tw-flex tw-gap-1 tw-flex-wrap">
                                <?php if ($komerce_no): ?>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=komerce-orders&action=detail&order_no=' . urlencode($komerce_no))); ?>"
                                       class="button button-small">Detail</a>
                                <?php endif; ?>
                                <?php if ($awb): ?>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=komerce-tracking&awb=' . urlencode($awb))); ?>"
                                       class="button button-small">Track</a>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=komerce-label&order_no=' . urlencode($komerce_no))); ?>"
                                       class="button button-small">Label</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?php
endif; ?>
