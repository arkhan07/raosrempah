<?php
/**
 * Admin View: Print Label Page
 */
if (!defined('ABSPATH'))
    exit;

$order_no_prefill = sanitize_text_field($_GET['order_no'] ?? '');
?>
<div class="wrap">
    <!-- Header -->
    <div class="tw-flex tw-items-center tw-justify-between tw-py-5 tw-mb-6" style="border-bottom:2px solid #f0f0f0">
        <div class="tw-flex tw-items-center tw-gap-3">
            <span class="dashicons dashicons-printer" style="font-size:24px;width:24px;height:24px;color:#e65c00"></span>
            <h1 class="tw-text-xl tw-font-bold tw-text-gray-900 tw-m-0">Cetak Label Pengiriman</h1>
        </div>
        <a href="<?php echo esc_url(admin_url('admin.php?page=komerce-orders')); ?>" class="button">← Order</a>
    </div>

    <div class="tw-grid tw-gap-5" style="grid-template-columns: 1fr 1fr; align-items: start;">

        <!-- Generate Form -->
        <div class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-p-6" style="border:1px solid #e5e7eb">
            <h2 class="tw-text-sm tw-font-bold tw-uppercase tw-tracking-wider tw-text-gray-500 tw-m-0 tw-mb-5 tw-pb-3" style="border-bottom:1px solid #f3f4f6">
                Generate Label PDF
            </h2>

            <div class="tw-grid tw-gap-4 tw-mb-5">
                <div>
                    <label for="label_order_no" class="tw-block tw-text-sm tw-font-medium tw-text-gray-700 tw-mb-1">
                        No. Order Komerce
                    </label>
                    <input type="text" id="label_order_no"
                           value="<?php echo esc_attr($order_no_prefill); ?>"
                           placeholder="KOM-20250808-XXXX"
                           class="tw-w-full tw-text-sm tw-rounded-lg tw-px-3 tw-py-2 tw-border tw-border-gray-200">
                </div>
                <div>
                    <label for="label_page" class="tw-block tw-text-sm tw-font-medium tw-text-gray-700 tw-mb-1">
                        Ukuran Label
                    </label>
                    <select id="label_page"
                            class="tw-w-full tw-text-sm tw-rounded-lg tw-px-3 tw-py-2 tw-border tw-border-gray-200">
                        <option value="page_2">2 Label per Halaman</option>
                        <option value="page_3">3 Label per Halaman</option>
                        <option value="page_4">4 Label per Halaman</option>
                    </select>
                </div>
            </div>

            <button type="button" id="komerce-generate-label" class="button button-primary button-large"
                    style="background:linear-gradient(135deg,#e65c00,#f89820);border-color:#c04f00;border-radius:8px;width:100%">
                🖨️ Generate Label PDF
            </button>

            <!-- Success Result -->
            <div id="komerce-label-result" style="display:none" class="tw-mt-4 tw-p-4 tw-rounded-xl tw-flex tw-items-start tw-gap-3"
                 style="background:#f0fdf4;border:1px solid #bbf7d0">
                <span class="dashicons dashicons-yes-alt" style="color:#16a34a;font-size:28px;width:28px;height:28px;margin-top:2px;flex-shrink:0"></span>
                <div>
                    <p class="tw-text-sm tw-font-semibold tw-text-green-800 tw-m-0">Label berhasil dibuat!</p>
                    <p id="komerce-label-msg" class="tw-text-xs tw-text-gray-500 tw-m-0 tw-mt-1"></p>
                    <a id="komerce-label-link" href="#" target="_blank" class="button button-small tw-mt-2" style="display:inline-block">
                        📥 Buka / Download PDF
                    </a>
                </div>
            </div>

            <!-- Error Result -->
            <div id="komerce-label-error" style="display:none" class="notice notice-error tw-mt-3">
                <p id="komerce-label-error-msg"></p>
            </div>
        </div>

        <!-- Ready Orders List -->
        <div class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-p-6" style="border:1px solid #e5e7eb">
            <h2 class="tw-text-sm tw-font-bold tw-uppercase tw-tracking-wider tw-text-gray-500 tw-m-0 tw-mb-5 tw-pb-3" style="border-bottom:1px solid #f3f4f6">
                📋 Order dengan AWB (Siap Cetak)
            </h2>
            <?php
            $ready_orders = wc_get_orders(array(
                'meta_key' => '_komerce_awb',
                'meta_compare' => '!=',
                'meta_value' => '',
                'limit' => 30,
            ));
            ?>
            <?php if (empty($ready_orders)): ?>
                <div class="tw-text-center tw-py-10 tw-text-gray-400">
                    <span class="dashicons dashicons-printer" style="font-size:40px;width:40px;height:40px;color:#d1d5db"></span>
                    <p class="tw-text-sm tw-mt-3">Belum ada order dengan AWB. Lakukan pickup request terlebih dahulu.</p>
                </div>
            <?php else: ?>
                <div style="max-height:400px;overflow-y:auto">
                    <table class="wp-list-table widefat striped">
                        <thead>
                            <tr>
                                <th class="tw-text-xs tw-text-gray-500">WC #</th>
                                <th class="tw-text-xs tw-text-gray-500">No. Komerce</th>
                                <th class="tw-text-xs tw-text-gray-500">AWB</th>
                                <th class="tw-text-xs tw-text-gray-500">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($ready_orders as $wc_order):
                            $komerce_no = $wc_order->get_meta('_komerce_order_no');
                            $awb = $wc_order->get_meta('_komerce_awb');
                        ?>
                            <tr>
                                <td class="tw-text-sm tw-font-semibold">#<?php echo esc_html($wc_order->get_order_number()); ?></td>
                                <td><code class="tw-text-xs" style="color:#e65c00"><?php echo esc_html($komerce_no); ?></code></td>
                                <td><code class="tw-text-xs tw-text-green-700"><?php echo esc_html($awb); ?></code></td>
                                <td>
                                    <button type="button" class="button button-small komerce-quick-label"
                                            data-order-no="<?php echo esc_attr($komerce_no); ?>">
                                        🖨️ Cetak
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
