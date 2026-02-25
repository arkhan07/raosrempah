<?php
/**
 * Admin View: Print Label Page
 */
if (!defined('ABSPATH'))
    exit;

$order_no_prefill = sanitize_text_field($_GET['order_no'] ?? '');
?>
<div class="wrap komerce-wrap">
    <div class="komerce-header">
        <h1>🖨️ Cetak Label Pengiriman</h1>
        <a href="<?php echo esc_url(admin_url('admin.php?page=komerce-orders')); ?>" class="button">← Order</a>
    </div>

    <div class="komerce-card-grid">
        <div class="komerce-card">
            <h2 class="komerce-section-title">Generate Label PDF</h2>
            <table class="form-table">
                <tr>
                    <th><label for="label_order_no">No. Order Komerce</label></th>
                    <td>
                        <input type="text" id="label_order_no" class="regular-text"
                               value="<?php echo esc_attr($order_no_prefill); ?>"
                               placeholder="KOM-20250808-XXXX">
                    </td>
                </tr>
                <tr>
                    <th><label for="label_page">Ukuran Label</label></th>
                    <td>
                        <select id="label_page" class="regular-text">
                            <option value="page_2">2 Label per Halaman</option>
                            <option value="page_3">3 Label per Halaman</option>
                            <option value="page_4">4 Label per Halaman</option>
                        </select>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="button" id="komerce-generate-label" class="button button-primary button-large">
                    🖨️ Generate Label PDF
                </button>
            </p>

            <div id="komerce-label-result" style="display:none" class="komerce-label-result">
                <div class="komerce-label-success">
                    <span class="dashicons dashicons-yes-alt" style="color:#46b450;font-size:32px"></span>
                    <div>
                        <strong>Label berhasil dibuat!</strong>
                        <p id="komerce-label-msg"></p>
                        <a id="komerce-label-link" href="#" target="_blank" class="button button-primary">📥 Buka / Download PDF</a>
                    </div>
                </div>
            </div>

            <div id="komerce-label-error" style="display:none" class="notice notice-error">
                <p id="komerce-label-error-msg"></p>
            </div>
        </div>

        <!-- Bulk Label Print -->
        <div class="komerce-card">
            <h2 class="komerce-section-title">📋 Order dengan AWB (Siap Cetak)</h2>
            <?php
$ready_orders = wc_get_orders(array(
    'meta_key' => '_komerce_awb',
    'meta_compare' => '!=',
    'meta_value' => '',
    'limit' => 30,
));
?>
            <?php if (empty($ready_orders)): ?>
                <p class="description">Belum ada order dengan AWB yang siap cetak. Lakukan pickup request terlebih dahulu.</p>
            <?php
else: ?>
                <div style="max-height:400px;overflow-y:auto">
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr><th>WC #</th><th>No. Komerce</th><th>AWB</th><th>Aksi</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($ready_orders as $wc_order):
        $komerce_no = $wc_order->get_meta('_komerce_order_no');
        $awb = $wc_order->get_meta('_komerce_awb');
?>
                        <tr>
                            <td>#<?php echo esc_html($wc_order->get_order_number()); ?></td>
                            <td><code><?php echo esc_html($komerce_no); ?></code></td>
                            <td><code><?php echo esc_html($awb); ?></code></td>
                            <td>
                                <button type="button" class="button button-small komerce-quick-label"
                                        data-order-no="<?php echo esc_attr($komerce_no); ?>">
                                    🖨️ Cetak
                                </button>
                            </td>
                        </tr>
                    <?php
    endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php
endif; ?>
        </div>
    </div>
</div>
