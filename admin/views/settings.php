<?php
/**
 * Admin View: Settings Page
 */
if (!defined('ABSPATH'))
    exit;

$settings = get_option('komerce_settings', array());
$saved = isset($_GET['saved']) && $_GET['saved'] == 1;
?>
<div class="wrap komerce-wrap">
    <div class="komerce-header">
        <div class="komerce-logo">
            <span class="dashicons dashicons-car"></span>
            <h1>RajaOngkir <span>Komerce</span></h1>
        </div>
        <p class="komerce-tagline">Integrasi pengiriman Komerce untuk WooCommerce Anda</p>
    </div>

    <?php if ($saved): ?>
        <div class="notice notice-success is-dismissible"><p>✅ <strong>Pengaturan berhasil disimpan!</strong></p></div>
    <?php
endif; ?>

    <div class="komerce-card-grid">
        <!-- Left: Main Settings -->
        <div class="komerce-card komerce-card-main">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('komerce_save_settings'); ?>
                <input type="hidden" name="action" value="komerce_save_settings">

                <h2 class="komerce-section-title">🔑 API Configuration</h2>

                <div class="komerce-mode-toggle">
                    <label class="komerce-toggle-label">
                        <input type="radio" name="mode" value="sandbox" <?php checked($settings['mode'] ?? 'sandbox', 'sandbox'); ?>>
                        <span class="komerce-badge badge-sandbox">SANDBOX</span>
                    </label>
                    <label class="komerce-toggle-label">
                        <input type="radio" name="mode" value="live" <?php checked($settings['mode'] ?? 'sandbox', 'live'); ?>>
                        <span class="komerce-badge badge-live">LIVE</span>
                    </label>
                </div>

                <table class="form-table">
                    <tr>
                        <th><label for="api_key_sandbox">API Key Sandbox</label></th>
                        <td>
                            <input type="text" id="api_key_sandbox" name="api_key_sandbox"
                                   value="<?php echo esc_attr($settings['api_key_sandbox'] ?? ''); ?>"
                                   class="regular-text" placeholder="your-sandbox-api-key">
                            <p class="description">Dapatkan dari <a href="https://komerce.id" target="_blank">dashboard Komerce</a> → Developer → API Keys</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="api_key_live">API Key Live</label></th>
                        <td>
                            <input type="text" id="api_key_live" name="api_key_live"
                                   value="<?php echo esc_attr($settings['api_key_live'] ?? ''); ?>"
                                   class="regular-text" placeholder="your-live-api-key">
                        </td>
                    </tr>
                </table>

                <h2 class="komerce-section-title" style="margin-top:28px">🏪 Informasi Pengirim (Shipper)</h2>
                <table class="form-table">
                    <tr>
                        <th><label for="shipper_name">Nama Toko</label></th>
                        <td><input type="text" id="shipper_name" name="shipper_name" value="<?php echo esc_attr($settings['shipper_name'] ?? ''); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="shipper_phone">No. HP Pengirim</label></th>
                        <td>
                            <input type="text" id="shipper_phone" name="shipper_phone" value="<?php echo esc_attr($settings['shipper_phone'] ?? ''); ?>" class="regular-text" placeholder="6281234567890">
                            <p class="description">Format: 628xxxx (tanpa + dan spasi)</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="shipper_email">Email Pengirim</label></th>
                        <td><input type="email" id="shipper_email" name="shipper_email" value="<?php echo esc_attr($settings['shipper_email'] ?? ''); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="shipper_address">Alamat Pengirim</label></th>
                        <td><textarea id="shipper_address" name="shipper_address" class="regular-text" rows="3"><?php echo esc_textarea($settings['shipper_address'] ?? ''); ?></textarea></td>
                    </tr>
                    <tr>
                        <th><label>Kelurahan/Kecamatan Asal</label></th>
                        <td>
                            <input type="text" id="shipper_dest_search" class="regular-text" placeholder="Cari kelurahan / kecamatan..." autocomplete="off">
                            <ul id="shipper-dest-results" class="komerce-autocomplete-list"></ul>
                            <input type="hidden" id="shipper_dest_id" name="shipper_dest_id" value="<?php echo esc_attr($settings['shipper_dest_id'] ?? ''); ?>">
                            <?php if (!empty($settings['shipper_dest_id'])): ?>
                                <p class="description" id="shipper-dest-selected">ID terpilih: <strong><?php echo esc_html($settings['shipper_dest_id']); ?></strong></p>
                            <?php
else: ?>
                                <p class="description" id="shipper-dest-selected">Ketik minimal 3 karakter untuk mencari</p>
                            <?php
endif; ?>
                        </td>
                    </tr>
                </table>

                <h2 class="komerce-section-title" style="margin-top:28px">⚙️ Pengaturan Lainnya</h2>
                <table class="form-table">
                    <tr>
                        <th><label for="auto_create_order">Auto-Create Order</label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="auto_create_order" name="auto_create_order" value="yes" <?php checked($settings['auto_create_order'] ?? 'yes', 'yes'); ?>>
                                <strong>Otomatis buat order di Komerce</strong> saat status WooCommerce berubah menjadi <em>Processing</em>
                            </label>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary button-hero komerce-btn-save">💾 Simpan Pengaturan</button>
                </p>
            </form>
        </div>

        <!-- Right: Status Card -->
        <div class="komerce-card komerce-card-side">
            <h3>📡 Status Koneksi API</h3>
            <?php
$api = new Komerce_API();
if ($api->is_configured()):
    $test = $api->search_destination('jakarta');
    $ok = isset($test['meta']['code']) && $test['meta']['code'] == 200;
?>
                <div class="komerce-status <?php echo $ok ? 'status-ok' : 'status-error'; ?>">
                    <?php echo $ok ? '✅ Terhubung ke API Komerce' : '❌ API Key tidak valid atau error: ' . esc_html($test['meta']['message'] ?? '-'); ?>
                </div>
                <p class="description">Mode: <strong><?php echo esc_html(strtoupper($settings['mode'] ?? 'sandbox')); ?></strong></p>
            <?php
else: ?>
                <div class="komerce-status status-warning">⚠️ API Key belum dikonfigurasi</div>
            <?php
endif; ?>

            <hr>
            <h3>🔗 Link Berguna</h3>
            <ul>
                <li><a href="https://komerce.id" target="_blank">🌐 Dashboard Komerce</a></li>
                <li><a href="<?php echo esc_url(admin_url('admin.php?page=komerce-orders')); ?>">📦 Manajemen Order</a></li>
                <li><a href="<?php echo esc_url(admin_url('admin.php?page=komerce-tracking')); ?>">🔍 Tracking AWB</a></li>
                <li><a href="<?php echo esc_url(admin_url('admin.php?page=wc-settings&tab=shipping')); ?>">🚚 WooCommerce Shipping</a></li>
            </ul>

            <hr>
            <h3>📋 Versi Plugin</h3>
            <p>RajaOngkir Komerce v<?php echo esc_html(KOMERCE_VERSION); ?></p>
        </div>
    </div>
</div>
