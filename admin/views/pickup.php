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
<div class="wrap">
    <!-- Header -->
    <div class="tw-flex tw-items-center tw-justify-between tw-py-5 tw-mb-6" style="border-bottom:2px solid #f0f0f0">
        <div class="tw-flex tw-items-center tw-gap-3">
            <span class="dashicons dashicons-car" style="font-size:24px;width:24px;height:24px;color:#e65c00"></span>
            <h1 class="tw-text-xl tw-font-bold tw-text-gray-900 tw-m-0">Request Pickup</h1>
        </div>
        <a href="<?php echo esc_url(admin_url('admin.php?page=komerce-orders')); ?>" class="button">← Order</a>
    </div>

    <?php if ($pickup_status === 'success'): ?>
        <div class="notice notice-success is-dismissible tw-mb-4"><p>✅ <strong>Pickup berhasil di-request!</strong> AWB telah disimpan ke order.</p></div>
    <?php elseif ($pickup_status === 'fail'): ?>
        <div class="notice notice-error is-dismissible tw-mb-4"><p>❌ Gagal request pickup: <strong><?php echo esc_html($pickup_msg); ?></strong></p></div>
    <?php endif; ?>

    <div class="tw-grid tw-gap-5" style="grid-template-columns: 1fr 320px; align-items: start;">

        <!-- Pickup Form -->
        <div class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-p-6" style="border:1px solid #e5e7eb">
            <h2 class="tw-text-sm tw-font-bold tw-uppercase tw-tracking-wider tw-text-gray-500 tw-m-0 tw-mb-5 tw-pb-3" style="border-bottom:1px solid #f3f4f6">
                Rincian Pickup
            </h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="komerce-pickup-form">
                <?php wp_nonce_field('komerce_request_pickup'); ?>
                <input type="hidden" name="action" value="komerce_request_pickup">

                <div class="tw-grid tw-grid-cols-3 tw-gap-4 tw-mb-6">
                    <div>
                        <label for="pickup_vehicle" class="tw-block tw-text-sm tw-font-medium tw-text-gray-700 tw-mb-1">Kendaraan</label>
                        <select id="pickup_vehicle" name="pickup_vehicle"
                                class="tw-w-full tw-text-sm tw-rounded-lg tw-px-3 tw-py-2 tw-border tw-border-gray-200">
                            <option value="Motor">🏍️ Motor</option>
                            <option value="Mobil">🚗 Mobil</option>
                        </select>
                    </div>
                    <div>
                        <label for="pickup_date" class="tw-block tw-text-sm tw-font-medium tw-text-gray-700 tw-mb-1">Tanggal Pickup</label>
                        <input type="text" id="pickup_date" name="pickup_date" required autocomplete="off"
                               class="tw-w-full tw-text-sm tw-rounded-lg tw-px-3 tw-py-2 tw-border tw-border-gray-200"
                               placeholder="YYYY-MM-DD">
                    </div>
                    <div>
                        <label for="pickup_time" class="tw-block tw-text-sm tw-font-medium tw-text-gray-700 tw-mb-1">Jam Pickup</label>
                        <input type="time" id="pickup_time" name="pickup_time" value="09:00" required
                               class="tw-w-full tw-text-sm tw-rounded-lg tw-px-3 tw-py-2 tw-border tw-border-gray-200">
                    </div>
                </div>

                <h3 class="tw-text-sm tw-font-semibold tw-text-gray-700 tw-mb-3">📦 Order yang akan di-pickup</h3>

                <?php if (empty($pending_orders)): ?>
                    <div class="tw-text-center tw-py-10 tw-text-gray-400">
                        <span class="dashicons dashicons-info-outline" style="font-size:40px;width:40px;height:40px;color:#d1d5db"></span>
                        <p class="tw-text-sm tw-mt-3">Tidak ada order yang menunggu pickup.</p>
                    </div>
                <?php else: ?>
                    <div class="tw-mb-3 tw-pb-3" style="border-bottom:1px solid #f3f4f6">
                        <label class="tw-flex tw-items-center tw-gap-2 tw-text-sm tw-font-semibold tw-cursor-pointer">
                            <input type="checkbox" id="komerce-check-all"> Pilih Semua
                        </label>
                    </div>
                    <div class="tw-flex tw-flex-col tw-gap-2 tw-max-h-80 tw-overflow-y-auto tw-pr-1">
                        <?php foreach ($pending_orders as $wc_order):
                            $komerce_no = $wc_order->get_meta('_komerce_order_no');
                        ?>
                            <label class="tw-flex tw-items-center tw-gap-3 tw-p-3 tw-rounded-lg tw-cursor-pointer tw-transition-colors hover:tw-bg-orange-50"
                                   style="border:1px solid #e5e7eb">
                                <input type="checkbox" name="order_numbers[]" value="<?php echo esc_attr($komerce_no); ?>">
                                <div class="tw-flex tw-items-center tw-gap-3 tw-text-sm tw-flex-wrap">
                                    <strong class="tw-text-gray-800">#<?php echo esc_html($wc_order->get_order_number()); ?></strong>
                                    <code class="tw-text-xs tw-text-gray-500"><?php echo esc_html($komerce_no); ?></code>
                                    <span class="tw-font-medium tw-text-gray-700"><?php echo esc_html($wc_order->get_billing_first_name() . ' ' . $wc_order->get_billing_last_name()); ?></span>
                                    <span class="tw-text-xs tw-px-2 tw-py-0.5 tw-rounded tw-bg-gray-100 tw-text-gray-600">
                                        Rp <?php echo number_format($wc_order->get_total(), 0, ',', '.'); ?>
                                    </span>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="tw-mt-5">
                        <button type="submit" class="button button-primary button-large"
                                style="background:linear-gradient(135deg,#e65c00,#f89820);border-color:#c04f00;border-radius:8px">
                            🚗 Request Pickup Sekarang
                        </button>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- Manual Input -->
        <div class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-p-5" style="border:1px solid #e5e7eb">
            <h2 class="tw-text-sm tw-font-bold tw-uppercase tw-tracking-wider tw-text-gray-500 tw-m-0 tw-mb-4 tw-pb-3" style="border-bottom:1px solid #f3f4f6">
                ✍️ Input Manual Order No.
            </h2>
            <p class="tw-text-xs tw-text-gray-500 tw-mb-3">Jika order tidak muncul di list, tambahkan No. Order Komerce secara manual di sini.</p>

            <div id="komerce-manual-orders" class="tw-flex tw-flex-col tw-gap-2 tw-mb-3">
                <div class="manual-order-row tw-flex tw-gap-2">
                    <input type="text" class="regular-text manual-order-no"
                           placeholder="Contoh: KOM-20250808-XXXX"
                           style="border-radius:6px;font-size:12px">
                    <button type="button" class="button komerce-remove-manual" style="min-width:36px">✕</button>
                </div>
            </div>

            <button type="button" id="komerce-add-manual" class="button"
                    style="width:100%;text-align:center;border-radius:6px;border-style:dashed">
                + Tambah Order
            </button>

            <div class="tw-mt-4 tw-p-3 tw-rounded-lg tw-text-xs tw-text-gray-500" style="background:#f9fafb;border:1px solid #f3f4f6">
                Isi tanggal &amp; jam pickup di form sebelah kiri, lalu klik "Request Pickup" untuk memproses semua order.
            </div>
        </div>
    </div>
</div>
