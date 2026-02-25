<?php
/**
 * Komerce API Client
 * Handles all HTTP requests to the Komerce API.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Komerce_API
{

    private $api_key;
    private $base_url;

    public function __construct()
    {
        $settings = get_option('komerce_settings', array());
        $mode = $settings['mode'] ?? 'sandbox';
        $this->base_url = ($mode === 'live') ? KOMERCE_API_LIVE : KOMERCE_API_SANDBOX;
        $this->api_key = ($mode === 'live')
            ? ($settings['api_key_live'] ?? '')
            : ($settings['api_key_sandbox'] ?? '');
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    private function get($path, $query = array())
    {
        $url = $this->base_url . $path;
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }
        $response = wp_remote_get($url, array(
            'headers' => array(
                'x-api-key' => $this->api_key,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ),
            'timeout' => 30,
        ));
        return $this->parse_response($response);
    }

    private function post($path, $body = array())
    {
        $url = $this->base_url . $path;
        $response = wp_remote_post($url, array(
            'headers' => array(
                'x-api-key' => $this->api_key,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode($body),
            'timeout' => 30,
        ));
        return $this->parse_response($response);
    }

    private function put($path, $body = array())
    {
        $url = $this->base_url . $path;
        $response = wp_remote_request($url, array(
            'method' => 'PUT',
            'headers' => array(
                'x-api-key' => $this->api_key,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode($body),
            'timeout' => 30,
        ));
        return $this->parse_response($response);
    }

    private function parse_response($response)
    {
        if (is_wp_error($response)) {
            return array(
                'meta' => array(
                    'message' => $response->get_error_message(),
                    'code' => 500,
                    'status' => 'error',
                ),
                'data' => null,
            );
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'meta' => array(
                    'message' => 'Invalid JSON response from API',
                    'code' => $code,
                    'status' => 'error',
                ),
                'data' => null,
            );
        }

        return $data;
    }

    // ─── Public API Methods ───────────────────────────────────────────────────

    /**
     * Search destination by keyword (postal code, village, subdistrict, district)
     *
     * @param string $keyword
     * @return array
     */
    public function search_destination($keyword)
    {
        return $this->get('/tariff/api/v1/destination/search', array(
            'keyword' => $keyword,
        ));
    }

    /**
     * Calculate shipping cost
     *
     * @param array $params {
     *   @type int    $shipper_destination_id
     *   @type int    $receiver_destination_id
     *   @type float  $weight   (in kg)
     *   @type int    $item_value
     *   @type string $cod      'yes' | 'no'
     * }
     * @return array
     */
    public function calculate_shipping($params)
    {
        return $this->get('/tariff/api/v1/calculate', array(
            'shipper_destination_id' => $params['shipper_destination_id'] ?? '',
            'receiver_destination_id' => $params['receiver_destination_id'] ?? '',
            'weight' => $params['weight'] ?? '',
            'item_value' => $params['item_value'] ?? '',
            'cod' => $params['cod'] ?? 'no',
        ));
    }

    /**
     * Create a new order
     *
     * @param array $data Full order payload as per API docs
     * @return array
     */
    public function create_order($data)
    {
        return $this->post('/order/api/v1/orders/store', $data);
    }

    /**
     * Get order detail
     *
     * @param string $order_no
     * @return array
     */
    public function get_order_detail($order_no)
    {
        return $this->get('/order/api/v1/orders/detail', array(
            'order_no' => $order_no,
        ));
    }

    /**
     * Cancel an order
     *
     * @param string $order_no
     * @return array
     */
    public function cancel_order($order_no)
    {
        return $this->put('/order/api/v1/orders/cancel', array(
            'order_no' => $order_no,
        ));
    }

    /**
     * Request pickup
     *
     * @param array $data {
     *   @type string $pickup_vehicle  'Motor' | 'Mobil'
     *   @type string $pickup_time     'HH:MM'
     *   @type string $pickup_date     'YYYY-MM-DD'
     *   @type array  $orders          array of ['order_no' => '...']
     * }
     * @return array
     */
    public function request_pickup($data)
    {
        return $this->post('/order/api/v1/pickup/request', $data);
    }

    /**
     * Generate print label
     *
     * @param string $order_no
     * @param string $page     'page_2' | 'page_3' | 'page_4'
     * @return array
     */
    public function print_label($order_no, $page = 'page_2')
    {
        $url = $this->base_url . '/order/api/v1/orders/print-label?'
            . http_build_query(array('order_no' => $order_no, 'page' => $page));
        $response = wp_remote_post($url, array(
            'headers' => array(
                'x-api-key' => $this->api_key,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ),
            'body' => '{}',
            'timeout' => 60,
        ));
        return $this->parse_response($response);
    }

    /**
     * Track airway bill
     *
     * @param string $shipping  Courier code e.g. 'NINJA', 'JNE', 'SICEPAT'
     * @param string $awb       Airway bill number
     * @return array
     */
    public function track_airwaybill($shipping, $awb)
    {
        return $this->get('/order/api/v1/orders/history-airway-bill', array(
            'shipping' => $shipping,
            'airway_bill' => $awb,
        ));
    }

    // ─── Utility ──────────────────────────────────────────────────────────────

    /**
     * Check if API key is configured
     */
    public function is_configured()
    {
        return !empty($this->api_key);
    }

    /**
     * Get the base URL for accessing label PDF
     * Usage: $api->get_label_base_url() . $path
     */
    public function get_label_base_url()
    {
        return $this->base_url . '/order';
    }
}
