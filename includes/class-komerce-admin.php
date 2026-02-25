<?php
/**
 * Komerce Admin Pages
 * Manages all WordPress admin pages: Settings, Orders, Pickup, Print Label, Tracking.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Komerce_Admin
{

    public function __construct()
    {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_head', array($this, 'inject_tailwind_cdn'));
        add_action('admin_post_komerce_save_settings', array($this, 'save_settings'));
        add_action('admin_post_komerce_request_pickup', array($this, 'handle_pickup'));
        add_action('admin_post_komerce_cancel_order', array($this, 'handle_cancel_order'));
        add_action('wp_ajax_komerce_admin_track', array($this, 'ajax_admin_track'));
        add_action('wp_ajax_komerce_admin_search_dest', array($this, 'ajax_admin_search_dest'));
        add_action('wp_ajax_komerce_admin_print_label', array($this, 'ajax_print_label'));
    }

    /**
     * Inject Tailwind CDN with prefix config on komerce pages only (avoids WP admin conflicts)
     */
    public function inject_tailwind_cdn()
    {
        $screen = get_current_screen();
        if (!$screen) {
            return;
        }
        $id = $screen->id;
        if (
            strpos($id, 'komerce') === false
            && strpos($id, 'shop_order') === false
            && strpos($id, 'wc-orders') === false
        ) {
            return;
        }
        ?>
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
        tailwind.config = {
            prefix: 'tw-',
            corePlugins: { preflight: false },
            theme: {
                extend: {
                    colors: {
                        komerce: {
                            50:  '#fff8f0',
                            100: '#fff1e0',
                            500: '#e65c00',
                            600: '#c04f00',
                        }
                    }
                }
            }
        }
        </script>
        <?php
    }

    // ─── Menu ─────────────────────────────────────────────────────────────────

    public function register_menu()
    {
        add_menu_page(
            'RajaOngkir Komerce',
            'RajaOngkir',
            'manage_options',
            'komerce-settings',
            array($this, 'page_settings'),
            'dashicons-car',
            56
        );
        add_submenu_page('komerce-settings', 'Pengaturan', 'Pengaturan', 'manage_options', 'komerce-settings', array($this, 'page_settings'));
        add_submenu_page('komerce-settings', 'Manajemen Order', 'Order', 'manage_options', 'komerce-orders', array($this, 'page_orders'));
        add_submenu_page('komerce-settings', 'Request Pickup', 'Pickup', 'manage_options', 'komerce-pickup', array($this, 'page_pickup'));
        add_submenu_page('komerce-settings', 'Cetak Label', 'Cetak Label', 'manage_options', 'komerce-label', array($this, 'page_label'));
        add_submenu_page('komerce-settings', 'Tracking AWB', 'Tracking', 'manage_options', 'komerce-tracking', array($this, 'page_tracking'));
    }

    // ─── Assets ───────────────────────────────────────────────────────────────

    public function enqueue_scripts($hook)
    {
        if (
        strpos($hook, 'komerce') === false
        && strpos($hook, 'shop_order') === false
        && strpos($hook, 'wc-orders') === false
        ) {
            return;
        }
        wp_enqueue_style('komerce-admin', KOMERCE_PLUGIN_URL . 'admin/css/admin.css', array(), KOMERCE_VERSION);
        wp_enqueue_script('komerce-admin', KOMERCE_PLUGIN_URL . 'admin/js/admin.js', array('jquery', 'jquery-ui-datepicker'), KOMERCE_VERSION, true);
        wp_enqueue_style('jquery-ui-datepicker-style', 'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css');
        wp_localize_script('komerce-admin', 'KomerceAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('komerce_admin_nonce'),
            'label_base' => (new Komerce_API())->get_label_base_url(),
        ));
    }

    // ─── Action Handlers ──────────────────────────────────────────────────────

    public function save_settings()
    {
        check_admin_referer('komerce_save_settings');
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $settings = array(
            'api_key_sandbox' => sanitize_text_field($_POST['api_key_sandbox'] ?? ''),
            'api_key_live' => sanitize_text_field($_POST['api_key_live'] ?? ''),
            'mode' => sanitize_text_field($_POST['mode'] ?? 'sandbox'),
            'shipper_name' => sanitize_text_field($_POST['shipper_name'] ?? ''),
            'shipper_phone' => sanitize_text_field($_POST['shipper_phone'] ?? ''),
            'shipper_email' => sanitize_email($_POST['shipper_email'] ?? ''),
            'shipper_address' => sanitize_textarea_field($_POST['shipper_address'] ?? ''),
            'shipper_dest_id' => intval($_POST['shipper_dest_id'] ?? 0),
            'auto_create_order' => sanitize_text_field($_POST['auto_create_order'] ?? 'no'),
        );

        update_option('komerce_settings', $settings);
        wp_safe_redirect(add_query_arg(array('page' => 'komerce-settings', 'saved' => 1), admin_url('admin.php')));
        exit;
    }

    public function handle_pickup()
    {
        check_admin_referer('komerce_request_pickup');
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $orders = array();
        $order_numbers = array_filter(array_map('sanitize_text_field', (array)($_POST['order_numbers'] ?? array())));
        foreach ($order_numbers as $no) {
            $orders[] = array('order_no' => $no);
        }

        $payload = array(
            'pickup_vehicle' => sanitize_text_field($_POST['pickup_vehicle'] ?? 'Motor'),
            'pickup_time' => sanitize_text_field($_POST['pickup_time'] ?? ''),
            'pickup_date' => sanitize_text_field($_POST['pickup_date'] ?? ''),
            'orders' => $orders,
        );

        $api = new Komerce_API();
        $result = $api->request_pickup($payload);

        if (isset($result['meta']['code']) && in_array($result['meta']['code'], array(200, 201))) {
            // Save AWB to WC order meta via WC Order API (HPOS compatible)
            if (!empty($result['data']) && is_array($result['data'])) {
                foreach ($result['data'] as $item) {
                    if (!empty($item['order_no']) && !empty($item['awb'])) {
                        $wc_orders = wc_get_orders(array(
                            'meta_key' => '_komerce_order_no',
                            'meta_value' => $item['order_no'],
                            'limit' => 1,
                        ));
                        if (!empty($wc_orders)) {
                            $wc_order = $wc_orders[0];
                            $wc_order->update_meta_data('_komerce_awb', sanitize_text_field($item['awb']));
                            if (!empty($item['pickup_id'])) {
                                $wc_order->update_meta_data('_komerce_pickup_id', sanitize_text_field($item['pickup_id']));
                            }
                            $wc_order->save_meta_data();
                        }
                    }
                }
            }
            wp_safe_redirect(add_query_arg(array('page' => 'komerce-pickup', 'pickup' => 'success'), admin_url('admin.php')));
        }
        else {
            $err = urlencode($result['meta']['message'] ?? 'Error');
            wp_safe_redirect(add_query_arg(array('page' => 'komerce-pickup', 'pickup' => 'fail', 'msg' => $err), admin_url('admin.php')));
        }
        exit;
    }

    public function handle_cancel_order()
    {
        check_admin_referer('komerce_cancel_order');
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $order_no = sanitize_text_field($_POST['order_no'] ?? '');
        $api = new Komerce_API();
        $result = $api->cancel_order($order_no);

        $status = (isset($result['meta']['code']) && $result['meta']['code'] === 200) ? 'success' : 'fail';
        $msg = urlencode($result['meta']['message'] ?? '');
        wp_safe_redirect(add_query_arg(array('page' => 'komerce-orders', 'cancel' => $status, 'msg' => $msg), admin_url('admin.php')));
        exit;
    }

    // ─── AJAX ─────────────────────────────────────────────────────────────────

    public function ajax_admin_track()
    {
        check_ajax_referer('komerce_admin_nonce', 'nonce');
        $shipping = strtoupper(sanitize_text_field($_POST['shipping'] ?? ''));
        $awb = sanitize_text_field($_POST['awb'] ?? '');
        $api = new Komerce_API();
        wp_send_json($api->track_airwaybill($shipping, $awb));
    }

    public function ajax_admin_search_dest()
    {
        check_ajax_referer('komerce_admin_nonce', 'nonce');
        $keyword = sanitize_text_field($_POST['keyword'] ?? '');
        $api = new Komerce_API();
        wp_send_json($api->search_destination($keyword));
    }

    public function ajax_print_label()
    {
        check_ajax_referer('komerce_admin_nonce', 'nonce');
        $order_no = sanitize_text_field($_POST['order_no'] ?? '');
        $page = sanitize_text_field($_POST['page'] ?? 'page_2');
        $api = new Komerce_API();
        wp_send_json($api->print_label($order_no, $page));
    }

    // ─── Page Renderers ───────────────────────────────────────────────────────

    public function page_settings()
    {
        require_once KOMERCE_PLUGIN_DIR . 'admin/views/settings.php';
    }

    public function page_orders()
    {
        require_once KOMERCE_PLUGIN_DIR . 'admin/views/orders.php';
    }

    public function page_pickup()
    {
        require_once KOMERCE_PLUGIN_DIR . 'admin/views/pickup.php';
    }

    public function page_label()
    {
        require_once KOMERCE_PLUGIN_DIR . 'admin/views/print-label.php';
    }

    public function page_tracking()
    {
        require_once KOMERCE_PLUGIN_DIR . 'admin/views/tracking.php';
    }
}

// Instantiate admin
new Komerce_Admin();
