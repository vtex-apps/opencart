<?php

class VtexApi extends Vtex
{
    protected $api_endpoint = "https://api.vtex.com/{{vendor}}/";

    public function sendSKUSuggestion($productId)
    {
        $body = $this->getProductData($productId);

        if (count($body['Images'])) {
            $log = new Log('sendSKUSuggestion.log');
            $log->write(json_encode(['productId' => $productId, 'data' => $body]));
            try {
                $this->client->put("suggestions/{$this->config->get('vtex_connector_seller_id')}/{$productId}", [
                    'json' => $body
                ]);
                return true;
            } catch (\Exception $e) {
                return false;
            }
        }
    }
}