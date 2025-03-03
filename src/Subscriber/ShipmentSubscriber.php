<?php

namespace MultiPackageShipping\Subscriber;

use Pickware\PickwareDhl\Api\DhlAdapter;
use Pickware\PickwareDhl\Api\Shipment;
use Shopware\Core\Framework\Context;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;

class ShipmentSubscriber implements EventSubscriberInterface
{
    private EntityRepository $shipmentRepository;
    private DhlAdapter $dhlAdapter;
    private LoggerInterface $logger;

    public function __construct(
        EntityRepository $shipmentRepository,
        DhlAdapter $dhlAdapter,
        LoggerInterface $logger
    ) {
        $this->shipmentRepository = $shipmentRepository;
        $this->dhlAdapter = $dhlAdapter;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            OrderEvents::ORDER_DELIVERED => 'onOrderDelivered',
        ];
    }

    public function onOrderDelivered(EntityWrittenEvent $event): void
    {
        foreach ($event->getIds() as $orderId) {
            $this->logger->info("Versandstatus aktualisiert für Bestellung: " . $orderId);

            $order = $this->shipmentRepository->search(
                (new \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria([$orderId])),
                Context::createDefaultContext()
            )->first();

            if (!$order) {
                $this->logger->error("Bestellung nicht gefunden: " . $orderId);
                continue;
            }

            try {
                $shipmentData = [
                    'shipmentId' => $order->getCustomFields()['shipment_id'] ?? null,
                    'status' => 'delivered',
                ];

                $this->dhlAdapter->updateShipment(new Shipment($shipmentData));

                $this->logger->info("DHL-Versandstatus für Bestellung " . $orderId . " aktualisiert.");
            } catch (\Exception $e) {
                $this->logger->error("Fehler beim Aktualisieren des DHL-Versandstatus: " . $e->getMessage());
            }
        }
    }
}
