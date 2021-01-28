<?php declare(strict_types=1);

namespace App\Controller;

use App\Services\OrderListService;
use App\SwagAppsystem\Client;
use App\SwagAppsystem\Event;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class OrderController extends AbstractController
{
    /**
     * @var OrderListService
     */
    private $orderListService;

    public function __construct(OrderListService $orderListService)
    {
        $this->orderListService = $orderListService;
    }

    /**
     * @Route("/hooks/order/placed", name="hooks.order.placed", methods={"POST"})
     * Generates an order list with an deep link in order to print it.
     */
    public function orderPlacedEvent(Client $client, Event $event): Response
    {
        $eventData = $event->getEventData();
        $order = $eventData['payload']['order'] ?? null;

        // Should not happen, but return if there is no order.
        if (!$order) {
            return new Response();
        }

        $orderId = $order['id'];

        //Gets the configuration including the data for the order list.
        $orderListConfiguration = $this->orderListService->getOrderListConfigurationFromOrder($order);

        //Gets the order list table as plain html.
        $orderListTable = $this->renderView('Order/order-list-table.html.twig', ['orderListConfiguration' => $orderListConfiguration]);

        //Updates the order with the order list table and the deep link to the order.
        $this->orderListService->updateOrder($client, $orderId, ['customFields' => ['order-list' => $orderListTable]]);

        return new Response();
    }

    /**
     * @Route("/iframe/orderlist", name="iframe.orderList", methods={"GET"})
     * Generates an order list out of all open orders in the admin.
     */
    public function iframeOrderList(Client $client): Response
    {
        //Gets the data for the order list.
        $orderListConfiguration = $this->orderListService->getOrderListConfigurationForAllOpenOrders($client);

        //Outputs the order list to the user.
        return $this->render('Order/order-list.html.twig', ['orderListConfiguration' => $orderListConfiguration]);
    }

    /**
     * @Route("/actionbutton/add/orderlist", name="actionButton.add.orderList", methods={"POST"})
     * Adds or update an order list with an deep link to an existing order.
     */
    public function addOrderListToExistingOrder(Client $client, Event $event): Response
    {
        $eventData = $event->getEventData();
        $orderId = $eventData['ids'][0];

        //Gets the order list data.
        $orderListConfiguration = $this->orderListService->getOrderListConfigurationFromOrderId($client, $orderId);

        //Gets the order list table as plain html.
        $orderListTable = $this->renderView('Order/order-list-table.html.twig', ['orderListConfiguration' => $orderListConfiguration]);

        //Updates the existing order with the order list and the deep link to the order.
        $this->orderListService->updateOrder($client, $orderId, ['customFields' => ['order-list' => $orderListTable]]);

        return new Response();
    }
}
