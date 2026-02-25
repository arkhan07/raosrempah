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
<div class="wrap komerce-wrap">
    <div class="komerce-header">
        <h1>🔍 Tracking Airwaybill</h1>
        <a href="<?php echo esc_url(admin_url('admin.php?page=komerce-orders')); ?>" class="button">← Order</a>
    </div>

    <div class="komerce-card-grid">
        <!-- Form Tracking -->
        <div class="komerce-card">
            <h2 class="komerce-section-title">Lacak Resi</h2>
            <table class="form-table">
                <tr>
                    <th><label for="track_shipping">Kurir</label></th>
                    <td>
                        <select id="track_shipping" class="regular-text">
                            <?php foreach ($couriers as $c): ?>
                                <option value="<?php echo esc_attr($c); ?>" <?php selected(strtoupper($shipping_prefill), $c); ?>>
                                    <?php echo esc_html($c); ?>
                                </option>
                            <?php
endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="track_awb">No. Resi (AWB)</label></th>
                    <td>
                        <input type="text" id="track_awb" class="regular-text"
                               value="<?php echo esc_attr($awb_prefill); ?>"
                               placeholder="Masukkan nomor resi">
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="button" id="komerce-do-track" class="button button-primary button-large">
                    🔍 Lacak Sekarang
                </button>
            </p>
        </div>

        <!-- Result -->
        <div class="komerce-card">
            <h2 class="komerce-section-title">Hasil Tracking</h2>
            <div id="komerce-tracking-loading" style="display:none;text-align:center;padding:24px">
                <span class="spinner is-active" style="float:none;width:40px;height:40px;margin:0 auto"></span>
                <p>Mengambil data tracking...</p>
            </div>
            <div id="komerce-tracking-result">
                <div class="komerce-tracking-placeholder">
                    <span class="dashicons dashicons-location" style="font-size:48px;color:#ddd"></span>
                    <p style="color:#999">Hasil tracking akan muncul di sini.</p>
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
