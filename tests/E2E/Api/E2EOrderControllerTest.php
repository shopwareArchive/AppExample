<?php declare(strict_types=1);

namespace App\Tests\E2E\Api;

use App\Services\OrderListService;
use App\Tests\E2E\Traits\E2ETestTrait;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class E2EOrderControllerTest extends TestCase
{
    use E2ETestTrait;

    public function testOrderList(): void
    {
        $client = $this->getClient();
        $httpClient = $client->getHttpClient();
        $randomId = bin2hex(random_bytes(16));

        //fetch ids which are needed during the order process
        $currencyId = $client->searchIds('currency', [])['data'][0];
        $taxId = $client->searchIds('tax', [])['data'][0];
        $paymentMethodId = $client->searchIds('payment-method', [])['data'][0];
        $shippingMethodId = $client->searchIds('shipping-method', [])['data'][0];
        $countryId = $client->searchIds('country', [])['data'][0];
        $customerGroupId = $client->searchIds('customer-group', [])['data'][0];
        $languageId = $client->searchIds('language', [])['data'][0];
        $salutationId = $client->searchIds('salutation', [])['data'][0];

        $salesChannel = $client->search('sales-channel', [])['data'][0];
        $salesChannelId = $salesChannel['id'];

        //create product which will be bought
        $productId = $this->createDemoProduct($salesChannelId, $currencyId, $taxId);

        //create customer which will perform the purchase
        $customer = $this->createDemoCustomer($randomId, $customerGroupId, $paymentMethodId, $salesChannelId, $salutationId, $countryId);
        $customerId = $customer['data'][0]['id'];
        $defaultShippingAddressId = $customer['data'][0]['defaultShippingAddressId'];
        $defaultBillingAddressId = $customer['data'][0]['defaultBillingAddressId'];

        //create new cart
        $response = $httpClient->post('api/_proxy/store-api/' . $salesChannelId . '/checkout/cart');
        $contextToken = json_decode($response->getBody()->getContents(), true)['token'];

        //switch to the created customer
        $this->switchCustomer($salesChannelId, $contextToken, $customerId);

        //set current context (sales channel, country, currency, language etc)
        $this->setContext($salesChannelId, $contextToken, $languageId, $currencyId, $shippingMethodId, $paymentMethodId, $defaultBillingAddressId, $defaultShippingAddressId, $countryId);

        //add created product to cart
        $this->addLineItem($salesChannelId, $contextToken, $productId);

        //perform the purchase which will save the order
        //this will also trigger the webhook which will create the order list
        $orderId = $this->saveOrder($salesChannelId, $contextToken);

        //fetch the order with the created order list
        $order = $client->search('order', [
            'filter' => [
                'id' => $orderId,
            ],
            'associations' => [
                'lineItems' => [
                    'total-count-mode' => 1,
                ],
            ],
        ]);

        //render the expected order list
        $arrayLoader = new FilesystemLoader(['templates']);
        $environment = new Environment($arrayLoader);
        $orderListService = new OrderListService($this->getShopRepository());
        $orderListConfiguration = $orderListService->getOrderListConfigurationFromOrder($order['data'][0]);
        $orderList = $environment->render('Order/order-list-table.html.twig', ['orderListConfiguration' => $orderListConfiguration]);

        static::assertEquals($orderList, $order['data'][0]['customFields']['order-list']);
    }

    private function switchCustomer(string $salesChannelId, string $contextToken, string $customerId): void
    {
        $this->getClient()->getHttpClient()->patch('/api/_proxy/switch-customer', [
            'body' => json_encode([
                'customerId' => $customerId,
                'salesChannelId' => $salesChannelId,
            ]),
            'headers' => [
                'sw-context-token' => $contextToken,
            ],
        ]);
    }

    private function setContext(string $salesChannelId, string $contextToken, string $languageId, string $currencyId, string $shippingMethodId, string $paymentMethodId, string $defaultBillingAddressId, string $defaultShippingAddressId, string $countryId): void
    {
        $this->getClient()->getHttpClient()->patch('api/_proxy/store-api/' . $salesChannelId . '/context',
            [
                'body' => json_encode([
                    'languageId' => $languageId,
                    'currencyId' => $currencyId,
                    'shippingMethodId' => $shippingMethodId,
                    'paymentMethodId' => $paymentMethodId,
                    'billingAddressId' => $defaultBillingAddressId,
                    'shippingAddressId' => $defaultShippingAddressId,
                    'countryId' => $countryId,
                ]),
                'headers' => [
                    'sw-context-token' => $contextToken,
                ],
            ]
        );
    }

    private function addLineItem(string $salesChannelId, string $contextToken, string $productId): void
    {
        $this->getClient()->getHttpClient()->post('api/_proxy/store-api/' . $salesChannelId . '/checkout/cart/line-item', [
            'body' => json_encode(['items' => [
                [
                    'id' => $productId,
                    'referencedId' => $productId,
                    'type' => 'product',
                ], ]]),
            'headers' => [
                'sw-context-token' => $contextToken,
            ],
        ]);
    }

    private function saveOrder(string $salesChannelId, string $contextToken): string
    {
        $response = $this->getClient()->getHttpClient()->post('api/_proxy-order/' . $salesChannelId, [
            'headers' => [
                'sw-context-token' => $contextToken,
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true)['id'];
    }

    private function createDemoProduct(string $salesChannelId, string $currencyId, string $taxId): string
    {
        $productData = $this->getRandomProductData($salesChannelId, $currencyId, $taxId);
        $this->getClient()->createEntity('product', $productData);

        return $productData['id'];
    }

    private function getRandomProductData(string $salesChannelId, string $currencyId, string $taxId): array
    {
        $randomString = bin2hex(random_bytes(16));

        return [
            'id' => $randomString,
            'name' => $randomString,
            'salesChannelId' => $salesChannelId,
            'taxId' => $taxId,
            'price' => [
                [
                    'currencyId' => $currencyId,
                    'gross' => '100',
                    'linked' => true,
                    'net' => '90',
                ],
            ],
            'productNumber' => $randomString,
            'stock' => 100,
            'visibilities' => [
                [
                    'id' => $randomString,
                    'salesChannelId' => $salesChannelId,
                    'visibility' => 10,
                ],
            ],
        ];
    }

    private function createDemoCustomer(string $customerNumber, string $customerGroupId, string $defaultPaymentMethodId, string $salesChannelId, string $salutationId, string $countryId): array
    {
        $client = $this->getClient();
        $customerData = $this->getRandomCustomerData($customerNumber, $customerGroupId, $defaultPaymentMethodId, $salesChannelId, $salutationId, $countryId);

        $client->createEntity('customer', $customerData);

        return $client->search('customer', [
            'ids' => $customerData['id'],
            'associations' => [
                'salesChannel' => [
                    'total-count-mode' => 1,
                ],
            ],
        ]);
    }

    private function getRandomCustomerData(string $customerNumber, string $customerGroupId, string $defaultPaymentMethodId, string $salesChannelId, string $salutationId, string $countryId): array
    {
        $randomId = bin2hex(random_bytes(16));

        return [
            'id' => $randomId,
            'groupId' => $customerGroupId,
            'defaultPaymentMethodId' => $defaultPaymentMethodId,
            'salesChannelId' => $salesChannelId,
            'defaultBillingAddressId' => $randomId,
            'defaultShippingAddressId' => $randomId,
            'salutationId' => $salutationId,
            'customerNumber' => $customerNumber,
            'firstName' => $randomId,
            'lastName' => $randomId,
            'email' => 'foo@bar.com',
            'addresses' => [
                [
                    'city' => $randomId,
                    'company' => $randomId,
                    'countryId' => $countryId,
                    'firstName' => $randomId,
                    'id' => $randomId,
                    'lastName' => $randomId,
                    'salutationId' => $salutationId,
                    'street' => $randomId,
                    'zipcode' => '12345',
                ],
            ],
        ];
    }
}
