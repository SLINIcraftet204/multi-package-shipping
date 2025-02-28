<?php

namespace MultiPackageShipping\Subscriber;

use Pickware\PickwareDhl\Api\DhlAdapter;
use Pickware\PickwareDhl\Api\Shipment;
use Pickware\ShippingBundle\Shipment\Address;
use Pickware\ShippingBundle\Parcel\Parcel;
use Shopware\Core\Framework\Context;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Psr\Log\LoggerInterface;
use MultiPackageShipping\Service\ConfigService;
use Shopware\Core\Checkout\Order\Event\CheckoutOrderPlacedEvent;

class OrderSubscriber implements EventSubscriberInterface
{
    private EntityRepository $orderRepository;
    private DhlAdapter $dhlAdapter;
    private LoggerInterface $logger;
    private ConfigService $configService;

    public function __construct(
        ConfigService $configService,
        EntityRepository $orderRepository,
        DhlAdapter $dhlAdapter,
        LoggerInterface $logger
    ) {
        $this->configService = $configService;
        $this->orderRepository = $orderRepository;
        $this->dhlAdapter = $dhlAdapter;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutOrderPlacedEvent::class => 'onOrderPlaced',
        ];
    }

    public function onOrderPlaced(CheckoutOrderPlacedEvent $event)
    {
        $context = $event->getContext();
        $orderId = $event->getOrderId();

        $order = $this->orderRepository->search(
            (new \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria([$orderId])),
            $context
        )->first();

        if (!$order) {
            $this->logger->error("Bestellung nicht gefunden: " . $orderId);
            return;
        }

        $this->logger->info("Erstelle DHL-Labels für Bestellung: " . $order->getOrderNumber());

        // Pakete berechnen
        $packages = $this->splitOrderIntoPackages($order);
        foreach ($packages as $index => $packageWeight) {
            try {
                $shipment = new Shipment(
                    new Address(
                        $order->getBillingAddress()->getFirstName(),
                        $order->getBillingAddress()->getLastName(),
                        $order->getBillingAddress()->getStreet(),
                        $order->getBillingAddress()->getZipcode(),
                        $order->getBillingAddress()->getCity(),
                        $order->getBillingAddress()->getCountry()->getIso()
                    ),
                    new Parcel($packageWeight)
                );

                $this->dhlAdapter->createShipment($shipment);

                $this->logger->info("DHL-Label für Paket " . ($index + 1) . " erstellt.");
            } catch (\Exception $e) {
                $this->logger->error("Fehler beim Erstellen des DHL-Labels: " . $e->getMessage());
            }
        }
    }

    private function splitOrderIntoPackages($order)
    {
        $maxWeight = $this->configService->getMaxPackageWeight();
        $currentWeight = 0;
        $packages = [];
        $lineItems = $order->getLineItems();

        foreach ($lineItems as $item) {
            $payload = $item->getPayload();
            $weight = isset($payload['weight']) ? (float) $payload['weight'] : 0;
            $quantity = $item->getQuantity();

            for ($i = 0; $i < $quantity; $i++) {
                if ($currentWeight + $weight > $maxWeight) {
                    $packages[] = $currentWeight;
                    $currentWeight = 0;
                }
                $currentWeight += $weight;
            }
        }

        if ($currentWeight > 0) {
            $packages[] = $currentWeight;
        }

        return $packages;
    }
}
