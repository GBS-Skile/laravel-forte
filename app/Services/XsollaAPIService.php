<?php

namespace App\Services;

use App\Item;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

const SKU_PREFIX = [
    'baechubotv2' => 'cabv2',
    'sangchuv2' => 'letv2',
    'skilebot' => 'skb',
];

class XsollaAPIService
{
    /**
     * @var Client
     */
    protected $client;
    /**
     * @var
     */
    protected $merchantId;
    /**
     * @var
     */
    protected $projectId;
    /**
     * @var
     */
    protected $projectKey;
    /**
     * @var
     */
    protected $apiKey;
    /**
     * @var
     */
    protected $authKey;
    /**
     * @var
     */
    protected $endpoint;
    /**
     * @var
     */
    protected $command;

    /**
     * XsollaAPIService constructor.
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
        $this->merchantId = config('xsolla.merchantId');
        $this->projectId = config('xsolla.projectId');
        $this->projectKey = config('xsolla.projectKey');
        $this->apiKey = config('xsolla.apiKey');
        $this->authKey = base64_encode($this->merchantId.':'.$this->apiKey);
        $this->endpoint = 'https://api.xsolla.com/merchant/v2/';
    }

    /**
     * @param string $method
     * @param string $uri
     * @param array $datas
     * @return mixed|\Psr\Http\Message\ResponseInterface
     */
    public function requestAPI(string $method, string $uri, array $datas)
    {
        if (strpos($uri, ':projectId') !== false) {
            $uri = str_replace(':projectId', $this->projectId, $uri);
        } else {
            $uri = str_replace(':merchantId', $this->merchantId, $uri);
        }

        try {
            $response = $this->client->request($method, $this->endpoint.$uri, [
                'body' => json_encode($datas),
                'headers' => [
                    'Authorization' => 'Basic '.$this->authKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json; charset=utf-8',
                ],
            ]);

            return $response->getBody();
        } catch (GuzzleException $exception) {
            (new \App\Http\Controllers\DiscordNotificationController)->exception($exception, $datas);

            return $exception->getMessage();
        }
    }

    /**
     * @return mixed
     */
    public function syncItems()
    {
        $this->print('== Xsolla Sync from Forte Items Start ==');

        $count = 0;
        $xsollaItemsSku = [];
        $xsollaItemIds = [];
        $xsollaDuplicateItemsSku = [];

        try {
            $xsollaItems = json_decode($this->requestAPI('GET', 'projects/:projectId/virtual_items/items', []), true);

            foreach ($xsollaItems as $item) {
                if (! Item::where('sku', $item['sku'])->first()) {
                    array_push($xsollaDuplicateItemsSku, $item['sku']);
                }
                array_push($xsollaItemsSku, $item['sku']);
                array_push($xsollaItemIds, $item['id']);
            }

            Item::whereNotIn('sku', $xsollaItemsSku)->delete();

            // 각 아이템 고유 ID에 대해 세부 페이지에 접속해서 동기화시킨다.
            foreach ($xsollaItemIds as $xsollaItemId) {
                $xsollaDetailItem = json_decode($this->requestAPI('GET', 'projects/:projectId/virtual_items/items/'.$xsollaItemId, []), true);
                $count++;

                // Forte DB 에 아이템이 없을 경우 생성
                if (! Item::where('sku', $xsollaDetailItem['sku'])->first()) {
                    $skuParse = explode('_', $xsollaDetailItem['sku']);
                    $convertSku = array_search($skuParse[0], SKU_PREFIX);

                    Item::create([
                        'client_id' => \App\Client::where('name', $convertSku)->value('id'),
                        'sku' => $xsollaDetailItem['sku'],
                        'name' => (! empty($xsollaDetailItem['name']['ko'])) ? $xsollaDetailItem['name']['ko'] : $xsollaDetailItem['name']['en'],
                        'image_url' => $xsollaDetailItem['image_url'],
                        'price' => empty($xsollaDetailItem['virtual_currency_price']) ? 0 : $xsollaDetailItem['virtual_currency_price'],
                        'enabled' => $xsollaDetailItem['enabled'] == true ? 1 : 0,
                        'consumable' => $xsollaDetailItem['permanent'] == true ? 0 : 1,
                        'expiration_time' => empty($xsollaDetailItem['expiration']) ? null : $xsollaDetailItem['expiration'],
                        'purchase_limit' => empty($xsollaDetailItem['purchase_limit']) ? null : $xsollaDetailItem['purchase_limit'],
                    ]);
                } else {
                    Item::where('sku', $xsollaDetailItem['sku'])->update([
                        'name' => (! empty($xsollaDetailItem['name']['ko'])) ? $xsollaDetailItem['name']['ko'] : $xsollaDetailItem['name']['en'],
                        'image_url' => $xsollaDetailItem['image_url'],
                        'price' => empty($xsollaDetailItem['virtual_currency_price']) ? 0 : $xsollaDetailItem['virtual_currency_price'],
                        'enabled' => $xsollaDetailItem['enabled'] == true ? 1 : 0,
                        'consumable' => $xsollaDetailItem['permanent'] == true ? 0 : 1,
                        'expiration_time' => empty($xsollaDetailItem['expiration']) ? null : $xsollaDetailItem['expiration'],
                        'purchase_limit' => empty($xsollaDetailItem['purchase_limit']) ? null : $xsollaDetailItem['purchase_limit'],
                    ]);
                }
            }
        } catch (\Exception $exception) {
            (new \App\Http\Controllers\DiscordNotificationController)->exception($exception, $xsollaItemsSku);

            return $exception->getMessage();
        }

        (new \App\Http\Controllers\DiscordNotificationController)->sync($count, $xsollaDuplicateItemsSku);
        $this->print('== End Xsolla Sync from Forte Items ==');
    }

    /**
     * @param string $message
     */
    private function print(string $message)
    {
        if ($this->command) {
            $this->command->info($message);
        } else {
            dump($message);
        }
    }
}
