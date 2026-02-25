<?php
/**
 * Komerce WooCommerce Shipping Method
 * Extends WC_Shipping_Method to show courier options at checkout
 * and handle destination ID selection.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Komerce_Shipping_Method')):
    class Komerce_Shipping_Method extends WC_Shipping_Method
    {

        // Supported couriers
        const COURIERS = array('NINJA', 'JNE', 'SICEPAT', 'IDE', 'SAP', 'LION', 'JNT');

        public function __construct($instance_id = 0)
        {
            $this->id = 'komerce';
            $this->instance_id = absint($instance_id);
            $this->method_title = __('RajaOngkir Komerce', 'rajaongkir-komerce');
            $this->method_description = __('Tampilkan ongkos kirim dari Komerce (NINJA, JNE, SiCepat, dll.)', 'rajaongkir-komerce');
            $this->supports = array('shipping-zones', 'instance-settings');
            $this->init();
        }

        public function init()
        {
            $this->init_form_fields();
            $this->init_settings();
            $this->title = $this->get_option('title', 'Komerce Shipping');
            $this->enabled = $this->get_option('enabled');
            add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_checkout_process', array($this, 'validate_destination_id'));
            add_action('woocommerce_checkout_order_processed', array($this, 'save_destination_id'), 10, 3);
            add_filter('woocommerce_checkout_fields', array($this, 'add_destination_hidden_field'));
            add_action('woocommerce_review_order_before_shipping', array($this, 'render_destination_search'));
        }

        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'rajaongkir-komerce'),
                    'type' => 'checkbox',
                    'label' => __('Enable Komerce Shipping', 'rajaongkir-komerce'),
                    'default' => 'yes',
                ),
                'title' => array(
                    'title' => __('Judul', 'rajaongkir-komerce'),
                    'type' => 'text',
                    'default' => 'Komerce Shipping',
                ),
            );
        }

        /**
         * Calculate shipping rates from Komerce API
         */
        public function calculate_shipping($package = array())
        {
            $settings = get_option('komerce_settings', array());
            $shipper_id = intval($settings['shipper_dest_id'] ?? 0);
            $receiver_id = intval(WC()->session->get('komerce_receiver_destination_id') ?? 0);

            if (!$shipper_id || !$receiver_id) {
                return;
            }

            $total_weight = 0;
            $item_value = 0;
            foreach ($package['contents'] as $item) {
                $product = $item['data'];
                $weight_kg = wc_get_weight($product->get_weight() ?: 0.5, 'kg');
                $total_weight += $weight_kg * $item['quantity'];
                $item_value += floatval($product->get_price()) * $item['quantity'];
            }
            $total_weight = max(1, round($total_weight));

            $api = new Komerce_API();
            $result = $api->calculate_shipping(array(
                'shipper_destination_id' => $shipper_id,
                'receiver_destination_id' => $receiver_id,
                'weight' => $total_weight,
                'item_value' => (int)$item_value,
                'cod' => 'no',
            ));

            if (empty($result['data']) || !is_array($result['data'])) {
                return;
            }

            foreach ($result['data'] as $courier_key => $courier_data) {
                if (empty($courier_data['rates'])) {
                    continue;
                }
                foreach ($courier_data['rates'] as $service) {
                    $rate_id = 'komerce_' . strtolower($courier_key) . '_' . strtolower($service['service_type'] ?? '');
                    $rate_cost = $service['final_price'] ?? $service['price'] ?? 0;
                    $rate_name = sprintf(
                        '%s %s (est. %s)',
                        strtoupper($courier_key),
                        $service['service_type'] ?? '',
                        $service['etd'] ?? '-'
                    );

                    $this->add_rate(array(
                        'id' => $rate_id,
                        'label' => $rate_name,
                        'cost' => $rate_cost,
                        'meta_data' => array(
                            'komerce_courier' => strtoupper($courier_key),
                            'komerce_service' => $service['service_type'] ?? '',
                        ),
                    ));
                }
            }
        }

        /**
         * Render destination search inside checkout shipping section
         */
        public function render_destination_search()
        {
            echo '<tr class="komerce-destination-row"><td colspan="2">';
            echo '<div id="komerce-destination-wrapper" style="margin-bottom:16px">';
            echo '<label><strong>' . esc_html__('Cari Kelurahan / Kecamatan / Kota Tujuan', 'rajaongkir-komerce') . '</strong></label>';
            echo '<input type="text" id="komerce-dest-search" class="input-text" placeholder="Ketik nama kelurahan/kecamatan..." autocomplete="off" style="width:100%;margin-top:6px">';
            echo '<ul id="komerce-dest-results" style="list-style:none;padding:0;margin:0;border:1px solid #ddd;display:none;background:#fff;position:absolute;z-index:999;min-width:300px;max-width:480px;max-height:200px;overflow-y:auto"></ul>';
            echo '<input type="hidden" id="komerce_receiver_destination_id" name="komerce_receiver_destination_id" value="' . esc_attr(WC()->session->get('komerce_receiver_destination_id') ?? '') . '">';
            echo '<p id="komerce-dest-selected" style="margin:6px 0 0;font-size:13px;color:#2271b1"></p>';
            echo '</div>';
            echo '</td></tr>';
        }

        /**
         * Add hidden field to checkout for destination ID persistence
         */
        public function add_destination_hidden_field($fields)
        {
            $fields['shipping']['komerce_receiver_destination_id'] = array(
                'type' => 'hidden',
                'default' => WC()->session->get('komerce_receiver_destination_id') ?? '',
                'required' => false,
            );
            return $fields;
        }

        /**
         * Validate destination ID is set on checkout submit
         */
        public function validate_destination_id()
        {
            $dest_id = intval($_POST['komerce_receiver_destination_id'] ?? 0);
            if (!$dest_id) {
                // Store in session from POST
                if (!empty($_POST['komerce_receiver_destination_id'])) {
                    WC()->session->set('komerce_receiver_destination_id', intval($_POST['komerce_receiver_destination_id']));
                }
            // Don't block checkout, just note it's missing
            }
            else {
                WC()->session->set('komerce_receiver_destination_id', $dest_id);
            }
        }

        /**
         * Save destination ID to order meta (HPOS-compatible)
         */
        public function save_destination_id($order_id, $posted_data, $order)
        {
            $dest_id = WC()->session->get('komerce_receiver_destination_id');
            if ($dest_id) {
                // Use WC Order API (HPOS-compatible), not update_post_meta()
                if (!($order instanceof WC_Order)) {
                    $order = wc_get_order($order_id);
                }
                if ($order) {
                    $order->update_meta_data('_komerce_receiver_destination_id', intval($dest_id));
                    $order->save();
                }
                WC()->session->set('komerce_receiver_destination_id', null);
            }
        }    }

endif; // class_exists
