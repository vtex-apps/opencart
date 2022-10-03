<?php
class ControllerApiFulfillment extends Controller
{
    public function pvt()
    {
        $this->load->language('api/fulfillment');

        $json = array();

        if (!isset($this->request->get['token']) || !$this->existsToken($this->request->get['token'])) {
            $json['error'] = $this->language->get('error_permission');
        } else {
            $route = $this->request->get['route'];

            switch ($route) {
                case 'api/fulfillment/pvt/orderForms/simulation':
                    if (in_array($this->request->server['REQUEST_METHOD'], array('POST'))) {
                        return $this->simulation();
                    } else {
                        return $this->notAllowed();
                    }
                case 'api/fulfillment/pvt/orders':
                    if (in_array($this->request->server['REQUEST_METHOD'], array('POST'))) {
                        return $this->createOrder();
                    } else {
                        return $this->notAllowed();
                    }
                default:
                    if (in_array($this->request->server['REQUEST_METHOD'], array('POST'))) {
                        if (preg_match('/api\/fulfillment\/pvt\/orders\/(.*)\/fulfill/', $route, $matches)) {
                            return $this->fulfillOrder($matches[1]);
                        } elseif (preg_match('/api\/fulfillment\/pvt\/orders\/(.*)\/cancel/', $route, $matches)) {
                            return $this->cancelOrder($matches[1]);
                        } else {
                            return $this->notAllowed();
                        }
                    } else {
                        return $this->notAllowed();
                    }
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    private function existsToken($key)
    {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "api` WHERE `status` = '1'");
        foreach ($query->rows as $row) {
            if (md5($row['key']) == $key) {
                return true;
            }
        }
        return false;
    }

    private function notAllowed()
    {
        $json['error'] = "405 - This method is not allowed.";
        $this->response->addHeader('Content-Type: application/json');
        return $this->response->setOutput(json_encode($json));
    }

    private function simulation()
    {
        $this->load->library('vtex');
        $data = json_decode(file_get_contents('php://input'), true);

        $output = [
            'country' => $data['country'],
            'postalCode' => str_replace('-', '', $data['postalCode']),
            'geoCoordinates' => $data['geoCoordinates'],
            'pickupPoints' => [],
            'messages' => [],
            'items' => $this->vtex->getRequestProducts($data),
            'logisticsInfo' => $this->vtex->getLogisticsInfo($data)
        ];

        $this->response->addHeader('Content-Type: application/json');
        return $this->response->setOutput(json_encode($output));
    }

    private function createOrder()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        $output = [
            'success' => false,
            'message' => 'Invalid data'
        ];

        if (!isset($data[0])) {
            $output['message'] = 'Wrong data!';
        } else {
            $this->load->library('vtex');
            $order_data = $this->getOrderData($data[0]);

            $this->load->model('checkout/order');
            if ($order_id = $this->model_checkout_order->addOrder($order_data)) {
                $order_status_id = $this->config->get('config_order_status_id');
                $this->model_checkout_order->addOrderHistory($order_id, $order_status_id);

                $output = [
                    [
                        'marketplaceOrderId' => $data[0]['marketplaceOrderId'],
                        'orderId' => $order_id,
                        'followUpEmail' => $data[0]['clientProfileData']['email'],
                        'items' => json_decode(json_encode($data[0]['items']), true),
                        'clientProfileData' => json_decode(json_encode($data[0]['clientProfileData']), true),
                        'shippingData' => json_decode(json_encode($data[0]['shippingData']), true),
                        'paymentData' => null
                    ]
                ];
            }

        }

        $this->response->addHeader('Content-Type: application/json');
        return $this->response->setOutput(json_encode($output));
    }

    private function fulfillOrder($orderId)
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['marketplaceOrderId'])) {
            $output['message'] = 'Wrong data!';
        } else {
            $marketplaceOrderId = $data['marketplaceOrderId'];

            $this->load->model('checkout/order');
            $this->model_checkout_order->addOrderHistory($orderId, 2);

            $output = [
                'date' => date('Y-m-d H:i:s'),
                'marketplaceOrderId' => $marketplaceOrderId,
                'orderId' => $orderId,
                'receipt' => null
            ];
        }

        $this->response->addHeader('Content-Type: application/json');
        return $this->response->setOutput(json_encode($output));
    }

    private function cancelOrder($orderId)
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['marketplaceOrderId'])) {
            $output['message'] = 'Wrong data!';
        } else {
            $marketplaceOrderId = $data['marketplaceOrderId'];

            $this->load->model('checkout/order');
            $this->model_checkout_order->addOrderHistory($orderId, 7);

            $output = [
                'date' => date('Y-m-d H:i:s'),
                'marketplaceOrderId' => $marketplaceOrderId,
                'orderId' => $orderId,
                'receipt' => null
            ];
        }

        $this->response->addHeader('Content-Type: application/json');
        return $this->response->setOutput(json_encode($output));
    }

    private function getOrderData($data)
    {
        $order_data = array();

        // Store Details
        $order_data['invoice_prefix'] = $this->config->get('config_invoice_prefix');
        $order_data['store_id'] = $this->config->get('config_store_id');
        $order_data['store_name'] = $this->config->get('config_name');
        $order_data['store_url'] = $this->config->get('config_url');

        $customerData = $this->getCustomerData($data);

        // Customer Details
        $order_data['customer_id'] = $customerData['customer_id'];
        $order_data['customer_group_id'] = $customerData['customer_group_id'];
        $order_data['firstname'] = $customerData['firstname'];
        $order_data['lastname'] = $customerData['lastname'];
        $order_data['email'] = $customerData['email'];
        $order_data['telephone'] = $customerData['telephone'];
        $order_data['custom_field'] = json_encode(['marketplaceOrderId' => $data['marketplaceOrderId']]);

        // Payment Details
        $order_data['shipping_firstname'] = $order_data['payment_firstname'] = $customerData['firstname'];
        $order_data['shipping_lastname'] =  $order_data['payment_lastname'] = $customerData['lastname'];
        $order_data['shipping_company'] = $order_data['payment_company'] = $data['clientProfileData']['corporateName']?:'';
        $order_data['shipping_address_1'] = $order_data['payment_address_1'] = "{$data['shippingData']['address']['street']} {$data['shippingData']['address']['number']}";
        $order_data['shipping_address_2'] = $order_data['payment_address_2'] = "{$data['shippingData']['address']['complement']} {$data['shippingData']['address']['reference']}";
        $order_data['shipping_city'] = $order_data['payment_city'] = $data['shippingData']['address']['city'];
        $order_data['shipping_postcode'] = $order_data['payment_postcode'] = $data['shippingData']['address']['postalCode'];
        $order_data['shipping_zone'] = $order_data['payment_zone'] = $data['shippingData']['address']['state'];
        $order_data['shipping_zone_id'] = $order_data['payment_zone_id'] = "";
        $order_data['shipping_country'] = $order_data['payment_country'] = $data['shippingData']['address']['country'];
        $order_data['shipping_country_id'] = $order_data['payment_country_id'] = "";
        $order_data['shipping_address_format'] = $order_data['payment_address_format'] = "";
        $order_data['shipping_custom_field'] = $order_data['payment_custom_field'] = "";

        $order_data['payment_method'] = 'Cash On Delivery';
        $order_data['payment_code'] = 'cod';
        $order_data['shipping_method'] = 'Flat Shipping Rate';
        $order_data['shipping_code'] = 'flat.flat';

        // Products
        $this->load->model('catalog/product');
        $order_data['products'] = array();
        foreach ($data['items'] as $item) {
            $product = $this->model_catalog_product->getProduct($item['id']);

            $order_data['products'][] = array(
                'product_id' => $product['product_id'],
                'name'       => $product['name'],
                'model'      => $product['model'],
                'option'     => [],
                'download'   => '',
                'quantity'   => $item['quantity'],
                'subtract'   => 0,
                'price'      => $item['price'] / $this->vtex->vtexPriceMultiplier,
                'total'      => ($item['price'] / $this->vtex->vtexPriceMultiplier) * $item['quantity'],
                'tax'        => 0,
                'reward'     => ''
            );
        }

        // Order Totals
        $this->load->model('setting/extension');

        $shippingCost = array_reduce($data['shippingData']['logisticsInfo'], function(&$res, $item) {
                return $res + $item['price'];
            }, 0) / $this->vtex->vtexPriceMultiplier;

        $totals = array(
            [
                'code' => 'sub_total',
                'title' => 'Sub-Total',
                'value' => $data['marketplacePaymentValue'] / $this->vtex->vtexPriceMultiplier,
                'sort_order' => "1",
            ],
            [
                'code' => 'shipping',
                'title' => 'Flat Shipping Rate',
                'value' => $shippingCost,
                'sort_order' => "3",
            ],
            [
                'code' => 'total',
                'title' => 'Total',
                'value' => $data['marketplacePaymentValue'] / $this->vtex->vtexPriceMultiplier,
                'sort_order' => "9",
            ],
        );
        $taxes = 0;
        $total = $data['marketplacePaymentValue'] / $this->vtex->vtexPriceMultiplier;

        // Because __call can not keep var references so we put them into an array.
        $total_data = array(
            'totals' => &$totals,
            'taxes'  => &$taxes,
            'total'  => &$total
        );

        $sort_order = array();
        foreach ($total_data['totals'] as $key => $value) {
            $sort_order[$key] = $value['sort_order'];
        }

        array_multisort($sort_order, SORT_ASC, $total_data['totals']);

        $order_data = array_merge($order_data, $total_data);

        $order_data['comment'] = '';

        $order_data['affiliate_id'] = 0;
        $order_data['commission'] = 0;
        $order_data['marketing_id'] = 0;
        $order_data['tracking'] = '';

        $currencyCode = $this->config->get('config_currency');

        $order_data['language_id'] = $this->config->get('config_language_id');
        $order_data['currency_id'] = $this->currency->getId($currencyCode);
        $order_data['currency_code'] = $currencyCode;
        $order_data['currency_value'] = $this->currency->getValue($currencyCode);
        $order_data['ip'] = $this->request->server['REMOTE_ADDR'];

        $order_data['forwarded_ip'] = '';
        $order_data['user_agent'] = '';
        $order_data['accept_language'] = '';

        return $order_data;
    }

    private function getCustomerData($data)
    {
        // Customer
        $this->load->model('account/customer');
        if (!$customer_info = $this->model_account_customer->getCustomerByEmail($data['clientProfileData']['email'])) {
            $customer_data = [
                'firstname' => $data['clientProfileData']['firstName'],
                'lastname' => $data['clientProfileData']['lastName'],
                'email' => $data['clientProfileData']['email'],
                'telephone' => $data['clientProfileData']['phone'],
                'password' => $data['clientProfileData']['email'],
            ];
            if ($customer_id = $this->model_account_customer->addCustomer($customer_data)) {
                $customer_info = $this->model_account_customer->getCustomer($customer_id);
            }
        }

        return $customer_info;
    }
}
