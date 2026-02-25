<?php
/**
 * Admin View: Pickup Request Page
 */
if (!defined('ABSPATH'))
    exit;

$pickup_status = sanitize_text_field($_GET['pickup'] ?? '');
$pickup_msg = urldecode(sanitize_text_field($_GET['msg'] ?? ''));

// Get WC orders with komerce_order_no that have no AWB yet
$pending_orders = wc_get_orders(array(
    'meta_key' => '_komerce_order_no',
    'meta_compare' => '!=',
    'meta_value' => '',
    'limit' => 100,
    'orderby' => 'date',
    'order' => 'DESC',
));
$pending_orders = array_filter($pending_orders, function ($o) {
    return !$o->get_meta('_komerce_awb');
});
?>
<div class="wrap komerce-wrap">
    <div class="komerce-header">
        <h1>🚗 Request Pickup</h1>
        <a href="<?php echo esc_url(admin_url('admin.php?page=komerce-orders')); ?>" class="button">← Order</a>
    </div>

    <?php if ($pickup_status === 'success'): ?>
        <div class="notice notice-success is-dismissible"><p>✅ <strong>Pickup berhasil di-request!</strong> AWB telah disimpan ke order.</p></div>
    <?php
elseif ($pickup_status === 'fail'): ?>
        <div class="notice notice-error is-dismissible"><p>❌ Gagal request pickup: <strong><?php echo esc_html($pickup_msg); ?></strong></p></div>
    <?php
endif; ?>

    <div class="komerce-card-grid">
        <!-- Form Pickup -->
        <div class="komerce-card">
            <h2 class="komerce-section-title">Rincian Pickup</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="komerce-pickup-form">
                <?php wp_nonce_field('komerce_request_pickup'); ?>
                <input type="hidden" name="action" value="komerce_request_pickup">

                <table class="form-table">
                    <tr>
                        <th><label for="pickup_vehicle">Kendaraan Pickup</label></th>
                        <td>
                            <select id="pickup_vehicle" name="pickup_vehicle" class="regular-text">
                                <option value="Motor">🏍️ Motor</option>
                                <option value="Mobil">🚗 Mobil</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="pickup_date">Tanggal Pickup</label></th>
                        <td>
                            <input type="text" id="pickup_date" name="pickup_date" class="regular-text" placeholder="YYYY-MM-DD" autocomplete="off" required>
                            <p class="description">Minimal hari ini. Format: YYYY-MM-DD</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="pickup_time">Jam Pickup</label></th>
                        <td>
                            <input type="time" id="pickup_time" name="pickup_time" class="regular-text" value="09:00" required>
                        </td>
                    </tr>
                </table>

                <h3 style="margin-top:20px">📦 Order yang akan di-pickup</h3>

                <?php if (empty($pending_orders)): ?>
                    <div class="komerce-empty-state">
                        <p>Tidak ada order yang menunggu pickup. Semua order sudah memiliki AWB atau belum dibuat di Komerce.</p>
                    </div>
                <?php
else: ?>
                    <div class="komerce-pickup-orders">
                        <div class="komerce-pickup-select-all">
                            <label>
                                <input type="checkbox" id="komerce-check-all"> <strong>Pilih Semua</strong>
                            </label>
                        </div>
                        <div class="komerce-pickup-list">
                        <?php foreach ($pending_orders as $wc_order):
        $komerce_no = get_post_meta($wc_order->get_id(), '_komerce_order_no', true);
?>
                            <label class="komerce-pickup-item">
                                <input type="checkbox" name="order_numbers[]" value="<?php echo esc_attr($komerce_no); ?>">
                                <div class="pickup-item-info">
                                    <strong>#<?php echo esc_html($wc_order->get_order_number()); ?></strong>
                                    <span class="pickup-komerce-no"><?php echo esc_html($komerce_no); ?></span>
                                    <span class="pickup-customer"><?php echo esc_html($wc_order->get_billing_first_name() . ' ' . $wc_order->get_billing_last_name()); ?></span>
                                    <span class="pickup-total">Rp <?php echo number_format($wc_order->get_total(), 0, ',', '.'); ?></span>
                                </div>
                            </label>
                        <?php
    endforeach; ?>
                        </div>
                    </div>

                    <p class="submit">
                        <button type="submit" class="button button-primary button-large komerce-btn-save">🚗 Request Pickup Sekarang</button>
                    </p>
                <?php
endif; ?>
            </form>
        </div>

        <!-- Manual input -->
        <div class="komerce-card">
            <h2 class="komerce-section-title">✍️ Input Manual Order No.</h2>
            <p class="description">Jika order tidak muncul di list, tambahkan No. Order Komerce secara manual.</p>
            <div id="komerce-manual-orders">
                <div class="manual-order-row">
                    <input type="text" class="regular-text manual-order-no" placeholder="Contoh: KOM-20250808-XXXX">
                    <button type="button" class="button komerce-remove-manual">✕</button>
                </div>
            </div>
            <button type="button" class="button" id="komerce-add-manual" style="margin-top:8px">+ Tambah</button>
            <p class="description" style="margin-top:12px">Klik "Request Pickup" setelah mengisi tanggal & jam pickup, lalu semua order akan diproses.</p>
        </div>
    </div>
</div>
