<?php
defined('ABSPATH') || exit;

class PostiOrder {

    private $orderStatus = false;

    public function __construct(PostiWarehouseApi $api) {
        $this->api = $api;
    }

    public function getOrderStatus($order_id) {
        $order_data = $this->getOrder($order_id);
        if (!$order_data) {
            return "Order not placed";
        }
        $this->orderStatus = $order_data['status']['value'];
        return $order_data['status']['value'];
    }

    public function getOrderActionButton() {
        if (!$this->orderStatus) {
            ?>
            <button type = "button" class="button button-posti" id = "posti-order-btn" name="posti_order_action"  onclick="posti_order_change(this);" value="place_order"><?php _e('Place Order', 'woo-pakettikauppa'); ?></button>
            <?php
        } elseif ($this->orderStatus != "Delivered") {
            /*
              ?>
              <button type = "button" class="button button-posti" id = "posti-order-btn" name="posti_order_action"  onclick="posti_order_change(this);" value="complete"><?php _e('Complete Order', 'woo-pakettikauppa');?></button>
              <?php
             */
        }
    }

    public function hasPostiProducts($order) {
        if (!is_object($order)) {
            $order = wc_get_order($order);
        }
        if (!$order){
            return false;
        }
        $items = $order->get_items();
        foreach ($items as $item_id => $item) {
            $type = get_post_meta($item['product_id'], '_posti_wh_stock_type', true);
            $product_warehouse = get_post_meta($item['product_id'], '_posti_wh_warehouse', true);
            if ($type == "Posti" && $product_warehouse) {
                return true;
            }
        }

        return false;
    }

    public function getOrder($order_id) {
        return $this->api->getOrder($this->api->getBusinessId() . '-' . $order_id);
    }

    public function addOrder($order) {
        if (!is_object($order)) {
            $order = wc_get_order($order);
        }
        return $this->api->addOrder($this->prepare_posti_order($order));
    }

    public function updatePostiOrders() {
        $options = get_option('posti_wh_options');
        if (!isset($options['posti_wh_field_autocomplete'])) {
            return false;
        }
        $args = array(
            'post_type' => 'shop_order',
            'post_status' => 'wc-processing',
            'meta_query' => array(
                array(
                    'key' => '_posti_wh_order',
                    'value' => '1',
                ),
            ),
        );
        $orders = get_posts($args);
        if (is_array($orders)) {
            foreach ($orders as $order_id) {
                $status = $this->getOrderStatus($order_id);
                if ($status == 'Delivered'){
                    $order = wc_get_order( $order_id );
                    if($order){
                       $order->update_status( 'completed', '', true );
                    }
                }
            }
        }
    }

    private function prepare_posti_order($_order) {
        $business_id = $this->api->getBusinessId();
        $order_items = array();
        $total_price = 0;
        $total_tax = 0;
        $items = $_order->get_items();
        foreach ($items as $item_id => $item) {
            $type = get_post_meta($item['product_id'], '_posti_wh_stock_type', true);
            $product_warehouse = get_post_meta($item['product_id'], '_posti_wh_warehouse', true);
            if ($type == "Posti" && $product_warehouse) {
                $total_price += $item->$total_price;
                $total_tax += $item->get_total_tax();
                $order_items[] = [
                    "externalId" => $business_id . '-' . $item['product_id'],
                    "externalProductId" => $business_id . '-' . $item['product_id'],
                    //"productEANCode" => $item['product_id'],
                    "externalWarehouseId" => $product_warehouse,
                    //"weight" => 0,
                    //"volume" => 0,
                    "quantity" => $item['qty'],
                        //"deliveredQuantity" => 0,
                        /*
                          "comments" => [
                          [
                          "name" => "string",
                          "value" => "string",
                          "type" => "string"
                          ]
                          ]
                         */
                ];
            }
        }

        $order = array(
            "externalId" => $business_id . "-" . $_order->get_id(),
            "clientId" => (string) $business_id,
            "orderDate" => date('Y-m-d\TH:i:s.vO', strtotime($_order->get_date_created()->__toString())),
            "sender" => [
                //"externalId" => "string",
                "name" => get_option("blogname"),
                "streetAddress" => get_option('woocommerce_store_address'),
                "postalCode" => get_option('woocommerce_store_postcode'),
                //"postOffice" => "string",
                "country" => get_option('woocommerce_default_country'),
                //"telephone" => "string",
                "email" => get_option("admin_email")
            ],
            "client" => [
                "externalId" => $business_id . "-" . $_order->get_customer_id(),
                "name" => $_order->get_billing_first_name() . ' ' . $_order->get_billing_last_name(),
                "streetAddress" => $_order->get_billing_address_1(),
                "postalCode" => $_order->get_billing_postcode(),
                //"postOffice" => "string",
                "country" => $_order->get_billing_country(),
                "telephone" => $_order->get_billing_phone(),
                "email" => $_order->get_billing_email()
            ],
            "recipient" => [
                "externalId" => $business_id . "-" . $_order->get_customer_id(),
                "name" => $_order->get_billing_first_name() . ' ' . $_order->get_billing_last_name(),
                "streetAddress" => $_order->get_billing_address_1(),
                "postalCode" => $_order->get_billing_postcode(),
                //"postOffice" => "string",
                "country" => $_order->get_billing_country(),
                "telephone" => $_order->get_billing_phone(),
                "email" => $_order->get_billing_email()
            ],
            "deliveryAddress" => [
                "externalId" => $business_id . "-" . $_order->get_customer_id(),
                "name" => $_order->get_shipping_first_name() . ' ' . $_order->get_shipping_last_name(),
                "streetAddress" => $_order->get_shipping_address_1(),
                "postalCode" => $_order->get_shipping_postcode(),
                //"postOffice" => "string",
                "country" => $_order->get_shipping_country(),
                "telephone" => $_order->get_billing_phone(),
                "email" => $_order->get_billing_email()
            ],
            "currency" => $_order->get_currency(),
            /*
              "additionalServices" => [
              [
              "serviceCode" => "string",
              "telephone" => "string",
              "email" => "string",
              "attributes" => [
              [
              "name" => "string",
              "value" => "string"
              ]
              ]
              ]
              ], */
            //"serviceCode" => "string",
            //"routingServiceCode" => "string",
            "totalPrice" => $total_price,
            "totalTax" => $total_tax,
            //"totalWeight" => 0,
            "totalWholeSalePrice" => $total_price + $total_tax,
            "deliveryOperator" => "Posti",
            /*
              "trackingCodes" => [
              "string"
              ],
             */
            /*
              "comments" => [
              [
              "name" => "string",
              "value" => "string",
              "type" => "string"
              ]
              ],
             * */
            /*
              "status" => [
              "value" => "string",
              "timestamp" => "string"
              ],
             */
            "rows" => $order_items
        );

        return $order;
    }

}
