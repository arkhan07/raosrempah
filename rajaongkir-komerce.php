<?php
/**
 * Plugin Name: RajaOngkir Komerce
 * Plugin URI:  https://jagosoftware.my.id
 * Description: Custome Integrasi layanan pengiriman Komerce (RajaOngkir) untuk WordPress & WooCommerce. Mendukung cek ongkir, buat order, pickup, cetak label, dan tracking resi.
 * Version:     1.0.0
 * Author:      Jagosoftware
 * Author URI:  https://jagosoftware.my.id
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: rajaongkir-komerce
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 9.9
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// ─── Constants ───────────────────────────────────────────────────────────────
define('KOMERCE_VERSION', '1.0.0');
define('KOMERCE_PLUGIN_FILE', __FILE__);
define('KOMERCE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KOMERCE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('KOMERCE_API_SANDBOX', 'https://api-sandbox.collaborator.komerce.id');
define('KOMERCE_API_LIVE', 'https://api.komerce.id'); // placeholder live URL

// ─── WooCommerce Feature Compatibility ──────────────────────────────────────
// Declare HPOS (High-Performance Order Storage) + Cart/Checkout Blocks support.
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        // Custom Order Tables / HPOS
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
        // Cart & Checkout Blocks
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'cart_checkout_blocks',
            __FILE__,
            true
        );
    }
});

// ─── Main Plugin Class ────────────────────────────────────────────────────────
class RajaOngkir_Komerce
{

    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Load all required files
     */
    private function includes()
    {
        require_once KOMERCE_PLUGIN_DIR . 'includes/class-komerce-api.php';
        require_once KOMERCE_PLUGIN_DIR . 'includes/class-komerce-order.php';
        require_once KOMERCE_PLUGIN_DIR . 'includes/class-komerce-admin.php';
    }

    /**
     * Register hooks
     */
    private function init_hooks()
    {
        add_action('init', array($this, 'load_textdomain'));
        add_action('plugins_loaded', array($this, 'maybe_load_woocommerce'));
        add_action('wp_ajax_komerce_search_destination', array($this, 'ajax_search_destination'));
        add_action('wp_ajax_nopriv_komerce_search_destination', array($this, 'ajax_search_destination'));
        add_action('wp_ajax_komerce_calculate_shipping', array($this, 'ajax_calculate_shipping'));
        add_action('wp_ajax_nopriv_komerce_calculate_shipping', array($this, 'ajax_calculate_shipping'));
        add_action('wp_ajax_komerce_track_awb', array($this, 'ajax_track_awb'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_scripts'));
        register_activation_hook(KOMERCE_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(KOMERCE_PLUGIN_FILE, array($this, 'deactivate'));
    }

    /**
     * Load WooCommerce integration if WC is active
     */
    public function maybe_load_woocommerce()
    {
        if (class_exists('WooCommerce')) {
            require_once KOMERCE_PLUGIN_DIR . 'includes/class-komerce-woocommerce.php';
            add_filter('woocommerce_shipping_methods', array($this, 'add_shipping_method'));
        }
    }

    public function add_shipping_method($methods)
    {
        $methods['komerce'] = 'Komerce_Shipping_Method';
        return $methods;
    }

    public function load_textdomain()
    {
        load_plugin_textdomain('rajaongkir-komerce', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }

    /**
     * AJAX: Search destination
     */
    public function ajax_search_destination()
    {
        check_ajax_referer('komerce_nonce', 'nonce');
        $keyword = sanitize_text_field($_POST['keyword'] ?? '');
        if (empty($keyword)) {
            wp_send_json_error(array('message' => 'Keyword is required'));
        }
        $api = new Komerce_API();
        $result = $api->search_destination($keyword);
        wp_send_json($result);
    }

    /**
     * AJAX: Calculate shipping
     */
    public function ajax_calculate_shipping()
    {
        check_ajax_referer('komerce_nonce', 'nonce');
        $params = array(
            'shipper_destination_id' => intval($_POST['shipper_destination_id'] ?? 0),
            'receiver_destination_id' => intval($_POST['receiver_destination_id'] ?? 0),
            'weight' => floatval($_POST['weight'] ?? 0),
            'item_value' => intval($_POST['item_value'] ?? 0),
            'cod' => sanitize_text_field($_POST['cod'] ?? 'no'),
        );
        $api = new Komerce_API();
        $result = $api->calculate_shipping($params);
        wp_send_json($result);
    }

    /**
     * AJAX: Track AWB
     */
    public function ajax_track_awb()
    {
        check_ajax_referer('komerce_nonce', 'nonce');
        $shipping = sanitize_text_field(strtoupper($_POST['shipping'] ?? ''));
        $awb = sanitize_text_field($_POST['awb'] ?? '');
        $api = new Komerce_API();
        $result = $api->track_airwaybill($shipping, $awb);
        wp_send_json($result);
    }

    /**
     * Enqueue public scripts
     */
    public function enqueue_public_scripts()
    {
        wp_enqueue_style('komerce-public', KOMERCE_PLUGIN_URL . 'public/css/public.css', array(), KOMERCE_VERSION);
        wp_enqueue_script('komerce-public', KOMERCE_PLUGIN_URL . 'public/js/public.js', array('jquery'), KOMERCE_VERSION, true);
        wp_localize_script('komerce-public', 'KomercePublic', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('komerce_nonce'),
        ));
    }

    /**
     * Plugin activation
     */
    public function activate()
    {
        // Set default options
        if (!get_option('komerce_settings')) {
            update_option('komerce_settings', array(
                'api_key_sandbox' => '',
                'api_key_live' => '',
                'mode' => 'sandbox',
                'shipper_name' => get_bloginfo('name'),
                'shipper_phone' => '',
                'shipper_email' => get_bloginfo('admin_email'),
                'shipper_address' => '',
                'shipper_dest_id' => '',
                'auto_create_order' => 'yes',
            ));
        }
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate()
    {
        flush_rewrite_rules();
    }
}

// ─── Shortcodes ───────────────────────────────────────────────────────────────

/**
 * [komerce_tracking] — Public tracking form for customers
 */
add_shortcode('komerce_tracking', function ($atts) {
    $atts = shortcode_atts(array('title' => 'Lacak Pengiriman'), $atts);
    $couriers = array('NINJA', 'JNE', 'SICEPAT', 'IDE', 'SAP', 'LION', 'JNT', 'ANTERAJA', 'POS');
    ob_start();
?>
    <div class="komerce-tracking-form">
        <h3>🔍 <?php echo esc_html($atts['title']); ?></h3>
        <label for="komerce-public-shipping">Pilih Kurir</label>
        <select id="komerce-public-shipping">
            <?php foreach ($couriers as $c): ?>
                <option value="<?php echo esc_attr($c); ?>"><?php echo esc_html($c); ?></option>
            <?php
    endforeach; ?>
        </select>
        <label for="komerce-public-awb">Nomor Resi</label>
        <input type="text" id="komerce-public-awb" placeholder="Masukkan nomor resi Anda...">
        <button type="button" id="komerce-public-track-btn">🔍 Lacak Sekarang</button>
    </div>
    <div id="komerce-public-result" class="komerce-public-timeline" style="margin-top:20px"></div>
    <?php
    return ob_get_clean();
});

// ─── Boot ─────────────────────────────────────────────────────────────────────
RajaOngkir_Komerce::get_instance();
