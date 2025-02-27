<?php

namespace MultiPackageShipping\Subscriber;

use Shopware\Core\Checkout\Order\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderSubscriber implements EventSubscriberInterface
{
    private EntityRepository $orderRepository;

    public function __construct(EntityRepository $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutOrderPlacedEvent::class => 'onOrderPlaced',
        ];
    }

    public function onOrderPlaced(CheckoutOrderPlacedEvent $event)
    {
        $order = $event->getOrder();
        $lineItems = $order->getLineItems();
        $maxWeight = 31.5;
        $currentPackageWeight = 0;
        $packages = [];
        $currentPackage = [];

        foreach ($lineItems as $item) {
            $weight = $item->getPayload()['weight'] ?? 0;
            $quantity = $item->getQuantity();

            for ($i = 0; $i < $quantity; $i++) {
                if ($currentPackageWeight + $weight > $maxWeight) {
                    $packages[] = $currentPackage;
                    $currentPackage = [];
                    $currentPackageWeight = 0;
                }

                $currentPackage[] = $item;
                $currentPackageWeight += $weight;
            }
        }

        if (!empty($currentPackage)) {
            $packages[] = $currentPackage;
        }

        // Speichere die Paketinformationen (zum Debuggen)
        foreach ($packages as $index => $package) {
            error_log("Paket " . ($index + 1) . " mit " . count($package) . " Artikeln.");
        }
    }
}
