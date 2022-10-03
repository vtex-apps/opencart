<?php
class ControllerApiInvoice extends Controller
{
    public function get()
    {
        $this->load->language('api/fulfillment');

        $json = array();

        if (!isset($this->request->get['k'])) {
            $json['error'] = $this->language->get('error_permission');
        } else {
            $route = $this->request->get['route'];

            switch ($route) {
                default:
                    if (in_array($this->request->server['REQUEST_METHOD'], array('GET'))) {
                        if (preg_match('/api\/invoice\/get\/(.*)/', $route, $matches)) {
                            $this->load->model('extension/module/vtex_connector');
                            $order_info = $this->model_extension_module_vtex_connector->getOrder($matches[1]);
                            if ($this->request->get['k'] == md5($order_info['custom_field'])) {
                                try {
                                    echo $this->model_extension_module_vtex_connector->getInvoice(array($matches[1]));
                                    exit(0);
                                } catch (Exception $e) {
                                    return $this->notAllowed();
                                }
                            } else {
                                return $this->notAllowed();
                            }

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

    private function notAllowed()
    {
        $json['error'] = "405 - This method is not allowed.";
        $this->response->addHeader('Content-Type: application/json');
        return $this->response->setOutput(json_encode($json));
    }
}
