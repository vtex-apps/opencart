<?php

use GuzzleHttp\Client;

class Vtex
{
    protected $api_endpoint = "https://{{vendor}}.myvtex.com/";

    protected $product;
    protected $category;
    protected $manufacturer;
    protected $config;
    protected $db;
    protected $currency;
    protected $languageId = 1;

    public $vtexPriceMultiplier = 100;
    protected $client;

    public function __construct($registry)
    {
        $this->config = $registry->get('config');
        $this->db = $registry->get('db');

        $registry->get('load')->model('catalog/product');
        $registry->get('load')->model('catalog/category');
        $registry->get('load')->model('catalog/manufacturer');
        $registry->get('load')->model('localisation/currency');

        $this->product = $registry->get('model_catalog_product');
        $this->category = $registry->get('model_catalog_category');
        $this->manufacturer = $registry->get('model_catalog_manufacturer');
        $this->currency = $registry->get('model_localisation_currency');

        $this->client = new Client([
            'base_url' => str_replace('{{vendor}}', $this->config->get('vtex_connector_vendor_name'), $this->api_endpoint),
            'defaults' => [
                'headers' => [
                    'Content-Type' => 'application/json; charset=utf-8',
                    'Accept' => 'application/json',
                    'X-VTEX-API-AppKey' => $this->config->get('vtex_connector_app_key'),
                    'X-VTEX-API-AppToken' => $this->config->get('vtex_connector_app_token'),
                ]
            ]
        ]);
        $this->client->setDefaultOption('verify', false);
    }

    public function getAttributeDescriptions($attribute_id) {
        $attribute_data = array();

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "attribute_description WHERE attribute_id = '" . (int)$attribute_id . "'");

        foreach ($query->rows as $result) {
            $attribute_data[$result['language_id']] = array('name' => $result['name']);
        }

        return $attribute_data;
    }

    public function getRequestProducts($request)
    {
        $response = [];

        $currency = $this->currency->getCurrencyByCode($this->config->get('config_currency'));

        foreach ($request['items'] as $k => $item) {
            $product = $this->product->getProduct($item['id']);

            $price = $product['price'] * $currency['value'] * $this->vtexPriceMultiplier;

            $response[] = [
                'id' => $product['product_id'],
                'requestIndex' => $k,
                'quantity' => $item['quantity'],
                'price' => (int)$price,
                'listPrice' => (int)$price,
                'sellingPrice' => (int)$price,
                'measurementUnit' => 'un',
                'merchantName' => null,
                'priceValidUntil' => null,
                'seller' => $this->config->get('vtex_connector_seller_id'),
                'unitMultiplier' => $product['minimum'],
                'attachmentOfferings' => [],
                'offerings' => [],
                'priceTags' => [],
                'availability' => ($product['quantity'] > 0) ? 'available' : 'unavailable',
            ];
        }
        return $response;
    }

    public function getLogisticsInfo($request)
    {
        $deliveryPrice = $this->config->get('shipping_flat_cost') * $this->vtexPriceMultiplier;

        $response = [];
        foreach ($request['items'] as $k => $item) {
            $product = $this->product->getProduct($item['id']);

            $response[] = [
                'itemIndex' => $k,
                'quantity' => $item['quantity'],
                'stockBalance' => (int)$product['quantity'] ?: 0,
                'shipsTo' => [
                    isset($request['country']) ? $request['country'] : null
                ],
                'slas' => [
                    [
                        'id' => 'Normal',
                        'deliveryChannel' => 'delivery',
                        'name' => 'Normal',
                        'shippingEstimate' => '1bd',
                        'price' => $deliveryPrice / count($request['items']),
                    ]
                ],
                'deliveryChannels' => [
                    [
                        'id' => 'delivery',
                        'stockBalance' => (int)$product['quantity'] ?: 0
                    ]
                ]
            ];
        }
        return $response;
    }

    public function changeNotification($skuId)
    {
        try {
            $this->client->post("api/catalog_system/pvt/skuseller/changenotification/{$this->config->get('vtex_connector_seller_id')}/{$skuId}");
            return true;
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function getProductData($productId)
    {
        $product = $this->product->getProduct($productId);
        $sellerId = $this->config->get('vtex_connector_seller_id');

        $categoryName = 'NONAME';
        if ($categories = $this->product->getProductCategories($productId)) {
            $categoryName = $this->category->getCategory($categories[0])['name'];
        }

        $brand = 'NONAME';
        if ($manufacturer = $this->manufacturer->getManufacturer($product['manufacturer_id'])) {
            $brand = $manufacturer['name'];
        }

        //        $sku = "{$sellerId}-{$productId}";
        $sku = empty($product['sku']) ? $product['model'] : $product['sku'];
        $currency = $this->currency->getCurrencyByCode($this->config->get('config_currency'));
        $name = strlen($product['name']) > 127 ? substr($product['name'], 0, 127) : $product['name'];

        return [
            'ProductName' => $name,
            'ProductId' => $product['product_id'],
            'ProductDescription' => $product['description'],
            'BrandName' => $brand,
            'SkuName' => $name,
            'SellerId' => $sellerId,
            'Height' => 1,
            'Width' => 1,
            'Length' => 1,
            'WeightKg' => $product['weight'] > 0 ? $product['weight'] : 1,
            'RefId' => $sku,
            'SellerStockKeepingUnitId' => $product['product_id'],
            'CategoryFullPath' => $categoryName,
            'SkuSpecifications' => $this->getSpecifications($product['product_id']),
            'ProductSpecifications' => $this->getSpecifications($product['product_id']),
            'Images' => $this->getImages($product),
            'MeasurementUnit' => 'un',
            'UnitMultiplier' => $product['minimum'],
            'AvailableQuantity' => $product['quantity'] ?: 0,
            'Pricing' => [
                'Currency' => $currency['code'],
                'SalePrice' => $product['price'] * $currency['value'],
                'CurrencySymbol' => $currency['symbol_left'],
            ],
        ];
    }

    public function getImages($product)
    {
        $response = [];

        if ($product['image']) {
            $response[] = [
                'imageName' => "Image{$product['product_id']}",
                'imageUrl' => str_replace('admin/', '', HTTPS_SERVER) . "image/{$product['image']}"
            ];
        }

        if ($images = $this->product->getProductImages($product['product_id'])) {
            foreach ($images as $image) {
                $response[] = [
                    'imageName' => "Image{$image['product_image_id']}",
                    'imageUrl' => str_replace('admin/', '', HTTPS_SERVER) . "image/{$image['image']}"
                ];
            }
        }

        return $response;
    }

    public function getSpecifications($productId, $detailed = false)
    {
        $response = [];
        $features = $this->product->getProductAttributes($productId);

        foreach ($features as $feature) {
            $attributeDescription = $this->getAttributeDescriptions($feature['attribute_id']);
            $type = 'Combo';
            $value = $feature['product_attribute_description'][$this->languageId]['text'];

            $fields = [
                'FieldName' => $attributeDescription[$this->languageId]['name'],
                'FieldValues' => [$value],
            ];

            if ($detailed) {
                $fields['Type'] = $type;
            }

            $response[] = $fields;
        }

        return $response;
    }

    public function changeOrderState($newState ,$vtexOrderId)
    {
        if ($newState == 'cancel') {
            try {
                $this->client->post("api/oms/pvt/orders/{$vtexOrderId}/{$newState}");
            } catch (\Exception $e) {}
        } else {
            try {
                $this->client->post("api/oms/pvt/orders/{$vtexOrderId}/changestate/{$newState}");
            } catch (\Exception $e) {}
        }
    }

    public function invoice($vtexOrderId, $body)
    {
        try {
            $this->client->post("api/oms/pvt/orders/{$vtexOrderId}/invoice", [
                'json' => $body
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}