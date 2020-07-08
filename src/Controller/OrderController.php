<?php declare(strict_types=1);

namespace App\Controller;

use App\Repository\ShopRepository;
use App\Services\OrderListService;
use App\SwagAppsystem\Client;
use App\SwagAppsystem\Event;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class OrderController extends AbstractController
{
    /**
     * @Route("/hooks/order/placed", name="oderPlacedEvent", methods={"POST"})
     * Generates an order list with an deep link in order to print it.
     */
    public function orderPlacedEvent(Client $client, ShopRepository $shopRepository, Event $event): Response
    {
        $eventData = $event->getEventData();

        $orderId = $eventData['payload']['order']['id'];
        $orderNumber = $eventData['payload']['order']['orderNumber'];
        $lineItems = $eventData['payload']['order']['lineItems'];
        $url = $this->generateUrl('orderList', [], UrlGeneratorInterface::ABSOLUTE_URL);

        //Gets the configuration including the data for the order list.
        $orderListConfiguration = OrderListService::getOrderListConfigurationFromLineItems($lineItems, $orderNumber);

        //Gets the deep link to the order list.
        $orderListLink = OrderListService::generateOrderListLink($shopRepository, $event, $orderId, $url);

        //Gets the order list table as plain html.
        $orderListTable = $this->renderView('Order/order-list-table.html.twig', ['orderListConfiguration' => $orderListConfiguration]);

        //Updates the order with the order list table and the deep link to the order.
        OrderListService::updateOrder($client, $orderId, ['customFields' => ['order-list' => $orderListTable, 'order-list-link' => $orderListLink]]);

        return new Response();
    }

    /**
     * @Route("/iframe/orderlist", name="oderList__iframe", methods={"GET"})
     * Generates an order list out of all open orders in the admin.
     */
    public function iframeOrderList(Client $client): Response
    {
        //Gets the configuration including the data for the order list.
        $orderListConfiguration = OrderListService::getOrderListConfigurationForAllOpenOrders($client);

        //Outputs the order list to the user.
        return $this->render('Order/order-list.html.twig', ['orderListConfiguration' => $orderListConfiguration]);
    }

    /**
     * @Route("/orderlist", name="orderList", methods={"GET"})
     * Generates an order list out of the given order.
     */
    public function orderList(Request $request, Client $client, ShopRepository $shopRepository): Response
    {
        $requestQuery = $request->query;

        //Authenticates the request.
        if (!OrderListService::authenticateOrderListLink($shopRepository, $requestQuery)) {
            return new Response(null, 401);
        }

        $orderId = $requestQuery->get('order-id');

        //Gets the configuration including the data for the order list.
        $orderListConfiguration = OrderListService::getOrderListConfigurationFromOrderId($client, $orderId);

        //Outputs the order list to the user.
        return $this->render('Order/order-list.html.twig', ['orderListConfiguration' => $orderListConfiguration]);
    }

    /**
     * @Route("/actionbutton/add/orderlist", name="addOrderList")
     * Adds or update an order list with an deep link to an existing order.
     */
    public function addOrderListToExistingOrder(Client $client, Event $event, ShopRepository $shopRepository): Response
    {
        $url = $this->generateUrl('orderList', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $eventData = $event->getEventData();
        $orderId = $eventData['ids'][0];

        //Gets the configuration including the data for the order list.
        $orderListConfiguration = OrderListService::getOrderListConfigurationFromOrderId($client, $orderId);

        //Gets the order list table as plain html.
        $orderListTable = $this->renderView('Order/order-list-table.html.twig', ['orderListConfiguration' => $orderListConfiguration]);

        //Generates an deep link to the order.
        $orderListLink = OrderListService::generateOrderListLink($shopRepository, $event, $orderId, $url);

        //Updates the existing order with the order list and the deep link to the order.
        OrderListService::updateOrder($client, $orderId, ['customFields' => ['order-list' => $orderListTable, 'order-list-link' => $orderListLink]]);

        return new Response();
    }
}
