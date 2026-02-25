=== RajaOngkir Komerce ===
Contributors: komerce
Tags: woocommerce, shipping, rajaongkir, komerce, ongkir, logistic
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Plugin integrasi layanan pengiriman Komerce (RajaOngkir) untuk WordPress & WooCommerce.

== Description ==

Plugin **RajaOngkir Komerce** mengintegrasikan layanan pengiriman Komerce ke dalam toko WooCommerce Anda secara penuh.

**Fitur Utama:**

* 🔍 **Cek Ongkir Real-time** — Tampilkan pilihan kurir (NINJA, JNE, SiCepat, IDE, SAP, LION, JNT) beserta ongkos kirim langsung di halaman checkout
* 📦 **Buat Order Otomatis** — Order Komerce dibuat secara otomatis saat status WooCommerce berubah ke "Processing"
* 🚗 **Request Pickup** — Request pickup batch dari halaman admin dengan pilihan kendaraan dan jadwal
* 🖨️ **Cetak Label** — Generate label pengiriman PDF langsung dari admin, 2/3/4 label per halaman
* 🔍 **Tracking AWB** — Lacak resi pengiriman dengan tampilan timeline yang informatif
* ❌ **Cancel Order** — Batalkan order Komerce langsung dari admin WordPress
* 🗺️ **WooCommerce Checkout Integration** — Search kelurahan/kecamatan tujuan langsung di checkout

**Mode yang didukung:**

* Sandbox (untuk testing dengan API Komerce Sandbox)
* Live (untuk produksi)

**Shortcode Tracking:**

Tambahkan halaman tracking untuk pelanggan dengan shortcode:
`[komerce_tracking]`

== Installation ==

1. Upload folder `rajaongkir-komerce` ke `/wp-content/plugins/`
2. Aktifkan plugin dari menu **Plugins > Installed Plugins**
3. Buka **RajaOngkir > Pengaturan**
4. Masukkan API Key dari dashboard Komerce (https://komerce.id)
5. Pilih mode Sandbox atau Live
6. Isi informasi Pengirim (nama toko, telepon, alamat)
7. Simpan pengaturan
8. Aktifkan Komerce Shipping Method di **WooCommerce > Settings > Shipping > Shipping Zones**

== API Endpoints yang Digunakan ==

Plugin ini menggunakan Komerce API (dikelola oleh PT Komerce Inovasi Indonesia):

* Destination Search — `/tariff/api/v1/destination/search`
* Calculate Shipping — `/tariff/api/v1/calculate`
* Create Order — `/order/api/v1/orders/store`
* Order Detail — `/order/api/v1/orders/detail`
* Cancel Order — `/order/api/v1/orders/cancel`
* Request Pickup — `/order/api/v1/pickup/request`
* Print Label — `/order/api/v1/orders/print-label`
* Track Airwaybill — `/order/api/v1/orders/history-airway-bill`

== Frequently Asked Questions ==

= Di mana saya mendapatkan API Key? =
Daftar dan login ke https://komerce.id, lalu buka menu Developer > API Keys.

= Apakah plugin ini memerlukan WooCommerce? =
WooCommerce diperlukan untuk fitur shipping di checkout dan auto-create order. Halaman admin (tracking, dll) tetap bisa digunakan tanpa WooCommerce.

= Bagaimana cara menambahkan halaman tracking untuk pelanggan? =
Buat halaman WordPress baru, lalu tambahkan shortcode `[komerce_tracking]` di kontennya.

= Apa perbedaan mode Sandbox dan Live? =
Mode Sandbox menggunakan URL `api-sandbox.collaborator.komerce.id` untuk keperluan testing. Mode Live menggunakan API produksi.

== Changelog ==

= 1.0.0 =
* Rilis pertama
* Fitur: Destination Search, Calculate Shipping, Create Order, Detail Order, Cancel Order, Request Pickup, Print Label, Tracking AWB
* Integrasi WooCommerce Shipping Method
* Admin panel lengkap

== Upgrade Notice ==

= 1.0.0 =
Rilis pertama.
