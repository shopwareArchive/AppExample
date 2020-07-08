<?php declare(strict_types=1);

namespace App\Services;

use App\Repository\ShopRepository;
use App\SwagAppsystem\Client;
use App\SwagAppsystem\Event;
use Symfony\Component\HttpFoundation\ParameterBag;

class OrderListService
{
    //Authenticates the order-id and the order-signature
    public static function authenticateOrderListLink(ShopRepository $shopRepository, ParameterBag $requestQuery): bool
    {
        $secret = $shopRepository->getSecretByShopId($requestQuery->get('shop-id'));
        $orderId = $requestQuery->get('order-id');
        $orderSignature = $requestQuery->get('order-signature');
        $hmac = hash_hmac('sha256', $orderId, $secret);

        return hash_equals($orderSignature, $hmac);
    }

    //Updates an existing order.
    //These steps are necessary because you need to create a new version for each order which you want to edit.
    //A new version will be created and then merged if you finished editing.
    public static function updateOrder(Client $client, $orderId, $data): void
    {
        $httpClient = $client->getHttpClient();

        //Creates a new version of the order.
        $versionResponse = $httpClient->post('/api/v2/_action/version/order/' . $orderId);
        $versionId = json_decode($versionResponse->getBody()->getContents(), true)['versionId'];

        //Updates the order.
        $client->updateEntity('order', $orderId, $data);

        //Merges the changes into the new version of the order.
        $httpClient->post('/api/v2/_action/version/merge/order/' . $versionId, ['headers' => ['sw-version-id' => $versionId]]);
    }

    //Generates a deep link to the order.
    public static function generateOrderListLink(ShopRepository $shopRepository, Event $event, string $orderId, string $url): string
    {
        $secret = $shopRepository->getSecretByShopId($event->getShopId());
        $date = new \DateTime();

        //Generates the order-signature.
        //This is needed to authenticate the request later.
        $orderSignature = hash_hmac('sha256', $orderId, $secret);

        $queryString = sprintf(
            'shop-id=%s&shop-url=%s&timestamp=%s',
            $event->getShopId(),
            urlencode($event->getShopUrl()),
            $date->getTimestamp()
        );

        //Creates the default signature for a GET request.
        //This is needed to authenticate the request and inject the client.
        $signature = hash_hmac('sha256', $queryString, $secret);

        return sprintf(
            '%s?%s&shopware-shop-signature=%s&order-id=%s&order-signature=%s',
            $url,
            $queryString,
            $signature,
            $orderId,
            $orderSignature
        );
    }

    //Returns the configuration for the order list table with the line items.
    //Uses only the orderId to build the order list.
    public static function getOrderListConfigurationFromOrderId(Client $client, string $orderId): array
    {
        //Get the order with the line items.
        $order = $client->search('order', [
            'associations' => [
                'lineItems' => [
                    'total-count-mode' => 1,
                ],
            ], 'filter' => [
                'id' => $orderId,
            ], 'includes' => [
                'order' => [
                    'orderNumber',
                    'lineItems',
                ],
                'order_line_item' => [
                    'quantity',
                    'payload',
                    'label',
                ],
            ],
        ]);

        $orderNumber = $order['data'][0]['orderNumber'];

        $lineItems = [];
        $counter = 0;

        //Format the line items.
        foreach ($order['data'][0]['lineItems'] as $lineItem) {
            $lineItems[$counter]['orderNumber'] = $orderNumber;
            $lineItems[$counter]['productNumber'] = $lineItem['payload']['productNumber'];
            $lineItems[$counter]['label'] = $lineItem['label'];
            $lineItems[$counter]['quantity'] = $lineItem['quantity'];
            $lineItems[$counter]['checked'] = '';
            ++$counter;
        }

        return self::setConfigurationOrderListTable($lineItems);
    }

    //Returns the configuration for the order list table with the line items
    //Uses the line items and the order number to build the order list.
    public static function getOrderListConfigurationFromLineItems(array $lineItems, string $orderNumber): array
    {
        //Format the line items.
        for ($i = 0; $i < count($lineItems); ++$i) {
            $lineItems[$i]['orderNumber'] = $orderNumber;
            $lineItems[$i]['productNumber'] = $lineItems[$i]['payload']['productNumber'];
            $lineItems[$i]['checked'] = '';
        }

        return self::setConfigurationOrderListTable($lineItems);
    }

    //Returns the configuration for the order list table with the line items
    //Fetches all open orders with their line items to build the order list.
    public static function getOrderListConfigurationForAllOpenOrders(Client $client): array
    {
        //Get all open orders with their line items.
        $orders = $client->search('order', [
            'associations' => [
                'lineItems' => [
                    'total-count-mode' => 1,
                ],
            ],
            'sort' => [
                [
                    'field' => 'orderDateTime',
                    'order' => 'ASC',
                ],
            ],
            'filter' => [
                'stateMachineState.name' => 'open',
            ],
            'includes' => [
                'order' => [
                    'orderNumber',
                    'lineItems',
                ],
                'order_line_item' => [
                    'quantity',
                    'payload',
                    'label',
                ],
            ],
        ]);

        $lineItems = [];
        $counter = 0;

        //Format the line items
        foreach ($orders['data'] as $order) {
            foreach ($order['lineItems'] as $lineItem) {
                $lineItems[$counter]['orderNumber'] = $order['orderNumber'];
                $lineItems[$counter]['productNumber'] = $lineItem['payload']['productNumber'];
                $lineItems[$counter]['label'] = $lineItem['label'];
                $lineItems[$counter]['quantity'] = $lineItem['quantity'];
                $lineItems[$counter]['checked'] = '';
                ++$counter;
            }
        }

        return self::setConfigurationOrderListTable($lineItems);
    }

    //Set the configuration for the order-list-table template with the line items.
    //The key of each header defines the header of the table.
    //The corresponding value of each key from the header will be the key for the line items,
    private static function setConfigurationOrderListTable(array $lineItems): array
    {
        return [
            'header' => [
                'Order number' => 'orderNumber',
                'Product number' => 'productNumber',
                'label' => 'label',
                'quantity' => 'quantity',
                'checked' => 'checked',
            ],
            'lineItems' => $lineItems,
        ];
    }
}
