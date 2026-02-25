<?php
/**
 * Admin View: Settings Page
 */
if (!defined('ABSPATH'))
    exit;

$settings = get_option('komerce_settings', array());
$saved = isset($_GET['saved']) && $_GET['saved'] == 1;
?>
<div class="wrap">
    <!-- Page Header -->
    <div class="tw-flex tw-items-center tw-justify-between tw-py-5 tw-mb-6" style="border-bottom:2px solid #f0f0f0">
        <div class="tw-flex tw-items-center tw-gap-3">
            <span class="dashicons dashicons-car" style="font-size:30px;width:30px;height:30px;color:#e65c00"></span>
            <div>
                <h1 class="tw-text-2xl tw-font-bold tw-text-gray-900 tw-m-0">
                    RajaOngkir <span style="color:#e65c00">Komerce</span>
                </h1>
                <p class="tw-text-sm tw-text-gray-500 tw-m-0 tw-mt-0.5">Integrasi pengiriman Komerce untuk WooCommerce Anda</p>
            </div>
        </div>
        <span class="tw-text-xs tw-text-gray-400 tw-bg-gray-100 tw-px-2 tw-py-1 tw-rounded-md">v<?php echo esc_html(KOMERCE_VERSION); ?></span>
    </div>

    <?php if ($saved): ?>
        <div class="notice notice-success is-dismissible tw-mb-4"><p>✅ <strong>Pengaturan berhasil disimpan!</strong></p></div>
    <?php endif; ?>

    <div class="tw-grid tw-gap-5" style="grid-template-columns: 1fr 320px; align-items: start;">

        <!-- ── Left: Main Settings ── -->
        <div>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('komerce_save_settings'); ?>
                <input type="hidden" name="action" value="komerce_save_settings">

                <!-- API Config Card -->
                <div class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-p-6 tw-mb-4" style="border:1px solid #e5e7eb">
                    <h2 class="tw-text-sm tw-font-bold tw-uppercase tw-tracking-wider tw-text-gray-500 tw-m-0 tw-mb-4 tw-pb-3" style="border-bottom:1px solid #f3f4f6">
                        🔑 API Configuration
                    </h2>

                    <!-- Mode Toggle -->
                    <div class="tw-flex tw-gap-3 tw-mb-5">
                        <label class="tw-flex tw-items-center tw-gap-2 tw-cursor-pointer tw-px-4 tw-py-2 tw-rounded-lg tw-border tw-text-sm tw-font-medium tw-transition-all" style="border-color:#f89820;background:#fff8f0;color:#e65c00">
                            <input type="radio" name="mode" value="sandbox" <?php checked($settings['mode'] ?? 'sandbox', 'sandbox'); ?>>
                            <span>SANDBOX</span>
                        </label>
                        <label class="tw-flex tw-items-center tw-gap-2 tw-cursor-pointer tw-px-4 tw-py-2 tw-rounded-lg tw-border tw-text-sm tw-font-medium tw-transition-all" style="border-color:#4caf50;background:#f0fdf4;color:#15803d">
                            <input type="radio" name="mode" value="live" <?php checked($settings['mode'] ?? 'sandbox', 'live'); ?>>
                            <span>LIVE</span>
                        </label>
                    </div>

                    <div class="tw-grid tw-gap-4">
                        <div>
                            <label for="api_key_sandbox" class="tw-block tw-text-sm tw-font-medium tw-text-gray-700 tw-mb-1">
                                API Key Sandbox
                            </label>
                            <input type="text" id="api_key_sandbox" name="api_key_sandbox"
                                   value="<?php echo esc_attr($settings['api_key_sandbox'] ?? ''); ?>"
                                   class="tw-w-full tw-text-sm tw-rounded-lg tw-px-3 tw-py-2 tw-border tw-border-gray-200 focus:tw-outline-none focus:tw-border-orange-400"
                                   placeholder="your-sandbox-api-key">
                            <p class="tw-text-xs tw-text-gray-400 tw-mt-1">
                                Dapatkan dari <a href="https://komerce.id" target="_blank" style="color:#e65c00">dashboard Komerce</a> → Developer → API Keys
                            </p>
                        </div>
                        <div>
                            <label for="api_key_live" class="tw-block tw-text-sm tw-font-medium tw-text-gray-700 tw-mb-1">
                                API Key Live
                            </label>
                            <input type="text" id="api_key_live" name="api_key_live"
                                   value="<?php echo esc_attr($settings['api_key_live'] ?? ''); ?>"
                                   class="tw-w-full tw-text-sm tw-rounded-lg tw-px-3 tw-py-2 tw-border tw-border-gray-200 focus:tw-outline-none focus:tw-border-orange-400"
                                   placeholder="your-live-api-key">
                        </div>
                    </div>
                </div>

                <!-- Shipper Info Card -->
                <div class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-p-6 tw-mb-4" style="border:1px solid #e5e7eb">
                    <h2 class="tw-text-sm tw-font-bold tw-uppercase tw-tracking-wider tw-text-gray-500 tw-m-0 tw-mb-4 tw-pb-3" style="border-bottom:1px solid #f3f4f6">
                        🏪 Informasi Pengirim (Shipper)
                    </h2>
                    <div class="tw-grid tw-gap-4">
                        <div class="tw-grid tw-grid-cols-2 tw-gap-4">
                            <div>
                                <label for="shipper_name" class="tw-block tw-text-sm tw-font-medium tw-text-gray-700 tw-mb-1">Nama Toko</label>
                                <input type="text" id="shipper_name" name="shipper_name"
                                       value="<?php echo esc_attr($settings['shipper_name'] ?? ''); ?>"
                                       class="tw-w-full tw-text-sm tw-rounded-lg tw-px-3 tw-py-2 tw-border tw-border-gray-200">
                            </div>
                            <div>
                                <label for="shipper_phone" class="tw-block tw-text-sm tw-font-medium tw-text-gray-700 tw-mb-1">No. HP Pengirim</label>
                                <input type="text" id="shipper_phone" name="shipper_phone"
                                       value="<?php echo esc_attr($settings['shipper_phone'] ?? ''); ?>"
                                       class="tw-w-full tw-text-sm tw-rounded-lg tw-px-3 tw-py-2 tw-border tw-border-gray-200"
                                       placeholder="628xxxxxxxxx">
                                <p class="tw-text-xs tw-text-gray-400 tw-mt-1">Format: 628xxxx (tanpa + dan spasi)</p>
                            </div>
                        </div>
                        <div>
                            <label for="shipper_email" class="tw-block tw-text-sm tw-font-medium tw-text-gray-700 tw-mb-1">Email Pengirim</label>
                            <input type="email" id="shipper_email" name="shipper_email"
                                   value="<?php echo esc_attr($settings['shipper_email'] ?? ''); ?>"
                                   class="tw-w-full tw-text-sm tw-rounded-lg tw-px-3 tw-py-2 tw-border tw-border-gray-200">
                        </div>
                        <div>
                            <label for="shipper_address" class="tw-block tw-text-sm tw-font-medium tw-text-gray-700 tw-mb-1">Alamat Pengirim</label>
                            <textarea id="shipper_address" name="shipper_address" rows="3"
                                      class="tw-w-full tw-text-sm tw-rounded-lg tw-px-3 tw-py-2 tw-border tw-border-gray-200 tw-resize-none"><?php echo esc_textarea($settings['shipper_address'] ?? ''); ?></textarea>
                        </div>
                        <div>
                            <label class="tw-block tw-text-sm tw-font-medium tw-text-gray-700 tw-mb-1">Kelurahan / Kecamatan Asal</label>
                            <div class="tw-relative">
                                <input type="text" id="shipper_dest_search"
                                       class="tw-w-full tw-text-sm tw-rounded-lg tw-px-3 tw-py-2 tw-border tw-border-gray-200"
                                       placeholder="Cari kelurahan / kecamatan..." autocomplete="off">
                                <ul id="shipper-dest-results" class="komerce-autocomplete-list"></ul>
                            </div>
                            <input type="hidden" id="shipper_dest_id" name="shipper_dest_id"
                                   value="<?php echo esc_attr($settings['shipper_dest_id'] ?? ''); ?>">
                            <p class="tw-text-xs tw-mt-1" id="shipper-dest-selected" style="color:#e65c00">
                                <?php if (!empty($settings['shipper_dest_id'])): ?>
                                    ✅ ID terpilih: <strong><?php echo esc_html($settings['shipper_dest_id']); ?></strong>
                                <?php else: ?>
                                    Ketik minimal 3 karakter untuk mencari
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Other Settings Card -->
                <div class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-p-6 tw-mb-5" style="border:1px solid #e5e7eb">
                    <h2 class="tw-text-sm tw-font-bold tw-uppercase tw-tracking-wider tw-text-gray-500 tw-m-0 tw-mb-4 tw-pb-3" style="border-bottom:1px solid #f3f4f6">
                        ⚙️ Pengaturan Lainnya
                    </h2>
                    <label class="tw-flex tw-items-start tw-gap-3 tw-cursor-pointer tw-p-3 tw-rounded-lg hover:tw-bg-gray-50 tw-transition-colors" style="border:1px solid #e5e7eb">
                        <input type="checkbox" id="auto_create_order" name="auto_create_order" value="yes"
                               <?php checked($settings['auto_create_order'] ?? 'yes', 'yes'); ?>
                               class="tw-mt-0.5">
                        <div>
                            <span class="tw-text-sm tw-font-semibold tw-text-gray-800">Otomatis buat order di Komerce</span>
                            <p class="tw-text-xs tw-text-gray-500 tw-m-0 tw-mt-0.5">Saat status WooCommerce berubah menjadi <em>Processing</em> (sudah bayar)</p>
                        </div>
                    </label>
                </div>

                <button type="submit" class="button button-primary button-hero" style="background:linear-gradient(135deg,#e65c00,#f89820);border-color:#c04f00;border-radius:8px;padding:8px 28px;height:auto;font-size:14px">
                    💾 Simpan Pengaturan
                </button>
            </form>
        </div>

        <!-- ── Right: Status + Links ── -->
        <div class="tw-flex tw-flex-col tw-gap-4">

            <!-- API Status Card -->
            <div class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-p-5" style="border:1px solid #e5e7eb">
                <h3 class="tw-text-sm tw-font-semibold tw-text-gray-700 tw-m-0 tw-mb-3 tw-pb-3" style="border-bottom:1px solid #f3f4f6">
                    📡 Status Koneksi API
                </h3>
                <?php
                $api = new Komerce_API();
                if ($api->is_configured()):
                    $test = $api->search_destination('jakarta');
                    $ok = isset($test['meta']['code']) && $test['meta']['code'] == 200;
                ?>
                    <div class="komerce-status <?php echo $ok ? 'status-ok' : 'status-error'; ?>">
                        <?php echo $ok ? '✅ Terhubung ke API Komerce' : '❌ ' . esc_html($test['meta']['message'] ?? 'API Error'); ?>
                    </div>
                    <p class="tw-text-xs tw-text-gray-500 tw-mt-2 tw-mb-0">
                        Mode: <strong class="tw-uppercase"><?php echo esc_html($settings['mode'] ?? 'sandbox'); ?></strong>
                    </p>
                <?php else: ?>
                    <div class="komerce-status status-warning">⚠️ API Key belum dikonfigurasi</div>
                <?php endif; ?>
            </div>

            <!-- Quick Links Card -->
            <div class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-p-5" style="border:1px solid #e5e7eb">
                <h3 class="tw-text-sm tw-font-semibold tw-text-gray-700 tw-m-0 tw-mb-3 tw-pb-3" style="border-bottom:1px solid #f3f4f6">
                    🔗 Link Berguna
                </h3>
                <div class="tw-flex tw-flex-col tw-gap-1">
                    <a href="https://collaborator.komerce.id/order-data" target="_blank"
                       class="tw-flex tw-items-center tw-gap-2 tw-text-sm tw-py-1.5 tw-px-2 tw-rounded-md hover:tw-bg-orange-50 tw-no-underline tw-transition-colors"
                       style="color:#e65c00">
                        🌐 Dashboard Komerce (Order Data)
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=komerce-orders')); ?>"
                       class="tw-flex tw-items-center tw-gap-2 tw-text-sm tw-py-1.5 tw-px-2 tw-rounded-md hover:tw-bg-gray-50 tw-no-underline tw-transition-colors tw-text-gray-700">
                        📦 Manajemen Order
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=komerce-tracking')); ?>"
                       class="tw-flex tw-items-center tw-gap-2 tw-text-sm tw-py-1.5 tw-px-2 tw-rounded-md hover:tw-bg-gray-50 tw-no-underline tw-transition-colors tw-text-gray-700">
                        🔍 Tracking AWB
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=wc-settings&tab=shipping')); ?>"
                       class="tw-flex tw-items-center tw-gap-2 tw-text-sm tw-py-1.5 tw-px-2 tw-rounded-md hover:tw-bg-gray-50 tw-no-underline tw-transition-colors tw-text-gray-700">
                        🚚 WooCommerce Shipping
                    </a>
                </div>
            </div>

            <!-- Info Card -->
            <div class="tw-rounded-xl tw-p-4 tw-text-xs tw-text-gray-500" style="background:#fff8f0;border:1px solid #ffe0c0">
                <p class="tw-font-semibold tw-text-gray-700 tw-m-0 tw-mb-2">💡 Cara Kerja Auto-Create Order</p>
                <ol class="tw-m-0 tw-pl-4 tw-space-y-1">
                    <li>Pelanggan checkout & memilih kurir Komerce</li>
                    <li>Pelanggan selesai bayar (status → <em>Processing</em>)</li>
                    <li>Order otomatis masuk ke dashboard Komerce</li>
                    <li>Admin request pickup & cetak label</li>
                </ol>
            </div>
        </div>
    </div>
</div>
