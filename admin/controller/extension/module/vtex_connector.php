<?php
class ControllerExtensionModuleVtexConnector extends Controller
{
    private $error = array();

    public function index()
    {
        $this->load->language('extension/module/vtex_connector');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('vtex_connector', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
        }

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->error['vendor_name'])) {
            $data['error_vendor_name'] = $this->error['vendor_name'];
        } else {
            $data['error_vendor_name'] = '';
        }

        if (isset($this->error['app_key'])) {
            $data['error_app_key'] = $this->error['app_key'];
        } else {
            $data['error_app_key'] = '';
        }

        if (isset($this->error['app_token'])) {
            $data['error_app_token'] = $this->error['app_token'];
        } else {
            $data['error_app_token'] = '';
        }

        if (isset($this->error['seller_id'])) {
            $data['error_seller_id'] = $this->error['seller_id'];
        } else {
            $data['error_seller_id'] = '';
        }

        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/vtex_connector', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['action'] = $this->url->link('extension/module/vtex_connector', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

        $settings = $this->model_setting_setting->getSetting('vtex_connector');

        if (isset($this->request->post['vtex_connector_vendor_name'])) {
            $data['vtex_connector_vendor_name'] = $this->request->post['vtex_connector_vendor_name'];
        } elseif (!empty($settings) && isset($settings['vtex_connector_vendor_name'])) {
            $data['vtex_connector_vendor_name'] = $settings['vtex_connector_vendor_name'];
        } else {
            $data['vtex_connector_vendor_name'] = '';
        }

        if (isset($this->request->post['vtex_connector_app_key'])) {
            $data['vtex_connector_app_key'] = $this->request->post['vtex_connector_app_key'];
        } elseif (!empty($settings) && isset($settings['vtex_connector_app_key'])) {
            $data['vtex_connector_app_key'] = $settings['vtex_connector_app_key'];
        } else {
            $data['vtex_connector_app_key'] = '';
        }

        if (isset($this->request->post['vtex_connector_app_token'])) {
            $data['vtex_connector_app_token'] = $this->request->post['vtex_connector_app_token'];
        } elseif (!empty($settings) && isset($settings['vtex_connector_app_token'])) {
            $data['vtex_connector_app_token'] = $settings['vtex_connector_app_token'];
        } else {
            $data['vtex_connector_app_token'] = '';
        }

        if (isset($this->request->post['vtex_connector_seller_id'])) {
            $data['vtex_connector_seller_id'] = $this->request->post['vtex_connector_seller_id'];
        } elseif (!empty($settings) && isset($settings['vtex_connector_seller_id'])) {
            $data['vtex_connector_seller_id'] = $settings['vtex_connector_seller_id'];
        } else {
            $data['vtex_connector_seller_id'] = '';
        }

        $this->load->model('localisation/language');
        $data['languages'] = $this->model_localisation_language->getLanguages();

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view('extension/module/vtex_connector', $data));
    }

    protected function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/module/vtex_connector')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (empty($this->request->post['vtex_connector_vendor_name'])) {
            $this->error['vendor_name'] = $this->language->get('error_name');
        }

        if (empty($this->request->post['vtex_connector_app_key'])) {
            $this->error['app_key'] = $this->language->get('error_name');
        }
//
        if (empty($this->request->post['vtex_connector_app_token'])) {
            $this->error['app_token'] = $this->language->get('error_name');
        }
//
        if (empty($this->request->post['vtex_connector_seller_id'])) {
            $this->error['seller_id'] = $this->language->get('error_name');
        }

        return !$this->error;
    }

    public function install()
    {
        $this->load->model('setting/setting');
        $this->addEvents();
    }

    public function uninstall()
    {
        $this->load->model('setting/setting');
        $this->model_setting_setting->deleteSetting('vtex_connector');
        $this->deleteEvents();
    }

    public function addEvents() {
        $this->load->model('setting/event');
        $this->model_setting_event->addEvent('vtex_product_after_add', 'admin/model/catalog/product/addProduct/after', 'extension/module/vtex_connector/eventProductSave');
        $this->model_setting_event->addEvent('vtex_product_after_edit', 'admin/model/catalog/product/editProduct/after', 'extension/module/vtex_connector/eventProductSave');
        $this->model_setting_event->addEvent('vtex_order_after_change_state', 'admin/model/sale/order/getTotalOrderHistories/after','extension/module/vtex_connector/eventOrderChangeStatus');
        $this->model_setting_event->addEvent('vtex_invoice_after_generate', 'admin/model/sale/order/createInvoiceNo/after','extension/module/vtex_connector/eventInvoiceGenerate');
    }

    public function deleteEvents() {
        $this->load->model('setting/event');
        $this->model_setting_event->deleteEventByCode('vtex_invoice_after_generate');
        $this->model_setting_event->deleteEventByCode('vtex_order_after_change_state');
        $this->model_setting_event->deleteEventByCode('vtex_product_after_edit');
        $this->model_setting_event->deleteEventByCode('vtex_product_after_add');
    }

    public function eventProductSave(&$route, &$args, &$output)
    {
        if ($route == "catalog/product/addProduct") {
            $product = $this->model_catalog_product->getProduct($output);
        } else {
            $product = $this->model_catalog_product->getProduct($args[0]);
        }

        try {
            $this->load->library('vtex');
            $this->vtex->changeNotification($product['product_id']);
        } catch (\Exception $e) {
            $this->load->library('vtexapi');
            $this->vtexapi->sendSKUSuggestion($product['product_id']);
        }
    }

    public function eventOrderChangeStatus(&$route, &$args, &$output)
    {
        $order_info = $this->model_sale_order->getOrder($args[0]);
        if ($order_info['order_status_id'] == 7 && ($marketplaceData = json_decode($order_info['custom_field'], true))) {
            if (isset($marketplaceData['marketplaceOrderId'])) {
                $this->load->library('vtex');
                $this->vtex->changeOrderState('cancel', $marketplaceData['marketplaceOrderId']);
            }
        }
    }

    public function eventInvoiceGenerate(&$route, &$args, &$output)
    {
        $order_info = $this->model_sale_order->getOrder($args[0]);
        if ($order_info['invoice_no'] && ($marketplaceData = json_decode($order_info['custom_field'], true)) && isset($marketplaceData['marketplaceOrderId'])) {
            $invoiceNumber = $order_info['invoice_prefix'] . $order_info['invoice_no'];
            $this->load->library('vtex');
            $items = [];
            foreach ($this->model_sale_order->getOrderProducts($args[0]) as $item) {
                $items[] = [
                    'id' => $item['product_id'],
                    'price' => (int)($item['price'] * $this->vtex->vtexPriceMultiplier),
                    'quantity' => $item['quantity']
                ];
            }

            $body =  [
                'type' => 'Output',
                'invoiceNumber' => $invoiceNumber,
                'invoiceValue' => (int)($order_info['total'] * $this->vtex->vtexPriceMultiplier),
                'issuanceDate' => date(DATE_ISO8601),
                'invoiceUrl' => str_replace('admin/', '', HTTPS_SERVER) . "api/invoice/get/{$order_info['order_id']}?k=" . md5($order_info['custom_field']),
                'items' => $items,
                'courier' => "",
                'trackingNumber' => '',
                'trackingUrl' => ''
            ];

            $this->vtex->invoice($marketplaceData['marketplaceOrderId'], $body);
        }
    }
}
