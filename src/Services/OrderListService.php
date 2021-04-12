<?php declare(strict_types=1);

namespace App\Services;

use App\Repository\ShopRepository;
use App\SwagAppsystem\Client;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class OrderListService extends AbstractController
{
    /**
     * @var ShopRepository
     */
    private $shopRepository;

    public function __construct(ShopRepository $shopRepository)
    {
        $this->shopRepository = $shopRepository;
    }

    //Updates an existing order.
    //These steps are necessary because you need to create a new version for each order which you want to edit.
    //A new version will be created and then merged if you finished editing.
    public function updateOrder(Client $client, string $orderId, array $data): void
    {
        $httpClient = $client->getHttpClient();

        //Creates a new version of the order.
        $versionResponse = $httpClient->post('/api/_action/version/order/' . $orderId);
        $versionId = json_decode($versionResponse->getBody()->getContents(), true)['versionId'];

        //Updates the order.
        $client->updateEntity('order', $orderId, $data);

        //Merges the changes into the new version of the order.
        $httpClient->post('/api/_action/version/merge/order/' . $versionId, ['headers' => ['sw-version-id' => $versionId]]);
    }

    //Generates the order list table data from an order.
    public function getOrderListConfigurationFromOrder(array $order): array
    {
        //Get the formatted line items.
        $lineItems = $this->mapLineItemsForConfigurationOrderListTable($order);

        return $this->setConfigurationOrderListTable($lineItems);
    }

    //Generates the order list table data from an order id.
    public function getOrderListConfigurationFromOrderId(Client $client, string $orderId): array
    {
        //Get the order with the line items.
        $orderSearchResult = $client->search('order', [
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

        $order = $orderSearchResult['data'][0] ?? null;

        //Get the formatted line items.
        $lineItems = $this->mapLineItemsForConfigurationOrderListTable($order);

        return $this->setConfigurationOrderListTable($lineItems);
    }

    //Generates the order list table data from all open orders.
    public function getOrderListConfigurationForAllOpenOrders(Client $client): array
    {
        //Get all open orders with their line items.
        $ordersSearchResult = $client->search('order', [
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

        //Format the line items
        foreach ($ordersSearchResult['data'] as $order) {
            $lineItems = array_merge(
                $lineItems,
                $this->mapLineItemsForConfigurationOrderListTable($order)
            );
        }

        return $this->setConfigurationOrderListTable($lineItems);
    }

    private function mapLineItemsForConfigurationOrderListTable(array $order): array
    {
        foreach ($order['lineItems'] as $lineItem) {
            $lineItems[] = [
                'orderNumber' => $order['orderNumber'],
                'productNumber' => $lineItem['payload']['productNumber'],
                'label' => $lineItem['label'],
                'quantity' => $lineItem['quantity'],
                'checked' => '',
            ];
        }

        return $lineItems;
    }

    //Set the configuration for the order-list-table template with the line items.
    private function setConfigurationOrderListTable(array $lineItems): array
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

    //Generates the order signature.
    private function generateOrderSignature(string $orderId, string $secret): string
    {
        return hash_hmac('sha256', $orderId, $secret);
    }
}
