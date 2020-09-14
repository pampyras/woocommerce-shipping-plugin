<?php

class PostiWarehouseApi {

    private $username = null;
    private $password = null;
    private $token = null;
    private $test = false;
    private $business_id = false;

    public function __construct($business_id, $test = false) {
        $this->business_id = $business_id;
        if ($test) {
            $this->test = true;
        }
        $options = get_option('posti_wh_options');
        $this->username = $options['posti_wh_field_username'];
        $this->password = $options['posti_wh_field_password'];
    }

    private function getApiUrl() {
        if ($this->test) {
            return "https://argon.api.posti.fi/ecommerce/v3/";
        }
        return "https://api.posti.fi/";
    }
    
    public function getBusinessId() {
        return $this->business_id;
    }

    private function getAuthUrl() {
        if ($this->test) {
            return "https://oauth2.barium.posti.com/";
        }
        return "https://oauth2.posti.com/";
    }

    public function getToken() {
        $curl = curl_init();
        $accesstoken = base64_encode($this->username . ":" . $this->password);
        $header = array();
        $header[] = 'Accept: application/json';
        $header[] = 'Authorization: Basic ' . $accesstoken;

        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_POST, 1);
        //curl_setopt($curl, CURLOPT_POSTFIELDS, $auth_data);
        curl_setopt($curl, CURLOPT_URL, $this->getAuthUrl() . 'oauth/token?grant_type=client_credentials');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        $result = curl_exec($curl);
        if (!$result) {
            return false;
        }
        curl_close($curl);
        $data = json_decode($result);
        $token = $data->access_token;
        $expires = time() + $data->expires_in;
        update_option('posti_wh_api_auth', array($token, $expires));
        $this->token = $token;
        return $token;
    }

    private function ApiCall($url, $data = '', $action = 'GET') {
        if (!$this->token) {
            $token_data = get_option('posti_wh_api_auth');
            if ($token_data['expires'] < time()) {
                $this->getToken();
            } else {
                $this->token = $token_data['token'];
            }
        }
        $curl = curl_init();
        $header = array();
        //$header[] = 'Accept: application/json';
        $header[] = 'Authorization: Bearer ' . $this->token;


        if ($action == "POST" || $action == "PUT") {
            $payload = json_encode($data);
            //var_dump($payload);// exit;
            $header[] = 'Content-Type: application/json';
            $header[] = 'Content-Length: ' . strlen($payload);
            if ($action == "POST") {
                curl_setopt($curl, CURLOPT_POST, 1);
            } else {
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $action);
            }
            curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        //echo $this->getApiUrl() . $url;
        curl_setopt($curl, CURLOPT_URL, $this->getApiUrl() . $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        //curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        $result = curl_exec($curl);
        $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        //var_dump($http_status);
        //var_dump($result); exit;
        
        if (!$result) {

            echo curl_error($curl);
            return false;
        }

        if ($http_status != 200) {
            return false;
        }
        return json_decode($result, true);
    }

    public function getWarehouses() {
        $warehouses_data = get_option('posti_wh_api_warehouses');
        if (!$warehouses_data || $warehouses_data['last_sync'] < time() - 1800) {
            $warehouses = $this->ApiCall('catalogs?role=RETAILER', '', 'GET');
            if (is_array($warehouses) && isset($warehouses['content'])) {
                update_option('posti_wh_api_warehouses', array(
                    'warehouses' => $warehouses['content'],
                    'last_sync' => time(),
                ));
            } else {
                $warehouses = array();
            }
        } else {
            $warehouses = $warehouses_data['warehouses'];
        }
        return $warehouses;
    }

    public function getProduct($id) {
        $product = $this->ApiCall('inventory/' . $id, '', 'GET');
        //var_dump($product);exit;
        return $product;
    }

    public function getProductsByWarehouse($id) {
        $products = $this->ApiCall('products/all/' . $id, '', 'GET');

        return $products;
    }

    public function addProduct($product, $business_id = false) {
        $status = $this->ApiCall('inventory', $product, 'PUT');
        return $status;
    }

    
    public function addOrder($order, $business_id = false) {
        $status = $this->ApiCall('orders', $order, 'POST');
        return $status;
    }
    
    public function getOrder($order_id, $business_id = false) {
        $status = $this->ApiCall('orders/'.$order_id, '', 'GET');
        return $status;
    }
}
