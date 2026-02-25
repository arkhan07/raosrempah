<?php
/**
 * Admin View: Tracking AWB Page
 */
if (!defined('ABSPATH'))
    exit;

$awb_prefill = sanitize_text_field($_GET['awb'] ?? '');
$shipping_prefill = sanitize_text_field($_GET['shipping'] ?? '');
$couriers = array('NINJA', 'JNE', 'SICEPAT', 'IDE', 'SAP', 'LION', 'JNT', 'ANTERAJA', 'POS');
?>
<div class="wrap">
    <!-- Header -->
    <div class="tw-flex tw-items-center tw-justify-between tw-py-5 tw-mb-6" style="border-bottom:2px solid #f0f0f0">
        <div class="tw-flex tw-items-center tw-gap-3">
            <span class="dashicons dashicons-location" style="font-size:24px;width:24px;height:24px;color:#e65c00"></span>
            <h1 class="tw-text-xl tw-font-bold tw-text-gray-900 tw-m-0">Tracking Airwaybill</h1>
        </div>
        <a href="<?php echo esc_url(admin_url('admin.php?page=komerce-orders')); ?>" class="button">← Order</a>
    </div>

    <div class="tw-grid tw-gap-5" style="grid-template-columns: 360px 1fr; align-items: start;">

        <!-- Tracking Form -->
        <div class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-p-6" style="border:1px solid #e5e7eb">
            <h2 class="tw-text-sm tw-font-bold tw-uppercase tw-tracking-wider tw-text-gray-500 tw-m-0 tw-mb-5 tw-pb-3" style="border-bottom:1px solid #f3f4f6">
                Lacak Resi
            </h2>

            <div class="tw-flex tw-flex-col tw-gap-4">
                <div>
                    <label for="track_shipping" class="tw-block tw-text-sm tw-font-medium tw-text-gray-700 tw-mb-1">Kurir</label>
                    <select id="track_shipping"
                            class="tw-w-full tw-text-sm tw-rounded-lg tw-px-3 tw-py-2 tw-border tw-border-gray-200">
                        <?php foreach ($couriers as $c): ?>
                            <option value="<?php echo esc_attr($c); ?>" <?php selected(strtoupper($shipping_prefill), $c); ?>>
                                <?php echo esc_html($c); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="track_awb" class="tw-block tw-text-sm tw-font-medium tw-text-gray-700 tw-mb-1">No. Resi (AWB)</label>
                    <input type="text" id="track_awb"
                           value="<?php echo esc_attr($awb_prefill); ?>"
                           placeholder="Masukkan nomor resi"
                           class="tw-w-full tw-text-sm tw-rounded-lg tw-px-3 tw-py-2 tw-border tw-border-gray-200">
                </div>
            </div>

            <button type="button" id="komerce-do-track" class="button button-primary tw-mt-5"
                    style="background:linear-gradient(135deg,#e65c00,#f89820);border-color:#c04f00;border-radius:8px;width:100%;height:40px;font-size:14px">
                🔍 Lacak Sekarang
            </button>
        </div>

        <!-- Tracking Results -->
        <div class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-p-6" style="border:1px solid #e5e7eb">
            <h2 class="tw-text-sm tw-font-bold tw-uppercase tw-tracking-wider tw-text-gray-500 tw-m-0 tw-mb-5 tw-pb-3" style="border-bottom:1px solid #f3f4f6">
                Hasil Tracking
            </h2>

            <div id="komerce-tracking-loading" style="display:none;text-align:center;padding:40px 0">
                <span class="spinner is-active" style="float:none;width:40px;height:40px;margin:0 auto"></span>
                <p class="tw-text-sm tw-text-gray-500 tw-mt-3">Mengambil data tracking...</p>
            </div>

            <div id="komerce-tracking-result">
                <div class="tw-text-center tw-py-16 tw-text-gray-300">
                    <span class="dashicons dashicons-location" style="font-size:52px;width:52px;height:52px;color:#e5e7eb"></span>
                    <p class="tw-text-sm tw-text-gray-400 tw-mt-3">Masukkan kurir & nomor resi, lalu klik <strong>Lacak Sekarang</strong>.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tracking result template (rendered by JS) -->
<script type="text/html" id="komerce-tracking-template">
    <div class="komerce-tracking-header">
        <div class="tracking-awb-info">
            <span class="tracking-awb-label">AWB:</span>
            <strong class="tracking-awb-number">{{awb}}</strong>
        </div>
        <span class="komerce-badge badge-status">{{last_status}}</span>
    </div>
    <div class="komerce-timeline" id="komerce-timeline-list">
        <!-- injected by JS -->
    </div>
</script>
