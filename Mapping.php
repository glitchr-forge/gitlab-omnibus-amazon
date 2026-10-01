<?php

namespace Omnibus\Amazon;

use Omnibus\Model\Address;
use Omnibus\Model\Parcel;
use Omnibus\Model\Shipment;
use Omnibus\Model\TrackingStatus;

/** Shipping v2's shapes for ours. */
final class Mapping
{
    public static function address(Address $a): array
    {
        return array_filter([
            'name' => mb_substr($a->name, 0, 50),
            'companyName' => $a->company,
            'addressLine1' => mb_substr($a->line(0), 0, 60),
            'addressLine2' => mb_substr($a->line(1), 0, 60) ?: null,
            'city' => $a->city,
            'postalCode' => $a->postcode,
            'countryCode' => strtoupper($a->country),
            'email' => $a->email,
            'phoneNumber' => $a->phone,
        ]);
    }

    public static function package(Parcel $p, Shipment $s, int $i): array
    {
        return [
            'dimensions' => ['length' => $p->length ?? 10, 'width' => $p->width ?? 10, 'height' => $p->height ?? 10, 'unit' => 'CENTIMETER'],
            'weight' => ['value' => round(max(0.01, $p->weight / 1000), 3), 'unit' => 'KILOGRAM'],
            'insuredValue' => ['value' => ($p->value ?? 0) / 100, 'unit' => $p->currency],
            'packageClientReferenceId' => ($s->reference ?? 'omnibus').'-'.($i + 1),
            'items' => [['quantity' => 1, 'itemValue' => ['value' => ($p->value ?? 0) / 100, 'unit' => $p->currency], 'description' => (string) $s->option('description', 'Merchandise'), 'weight' => ['value' => round(max(0.01, $p->weight / 1000), 3), 'unit' => 'KILOGRAM']]],
        ];
    }

    public static function status(?string $code, ?string $description = null): TrackingStatus
    {
        $d = strtolower((string) $description);

        return match (strtoupper((string) $code)) {
            'DELIVERED' => TrackingStatus::DELIVERED,
            'OUT_FOR_DELIVERY' => TrackingStatus::OUT_FOR_DELIVERY,
            'AVAILABLE_FOR_PICKUP', 'READY_FOR_PICKUP' => TrackingStatus::AVAILABLE_FOR_PICKUP,
            'RETURNING', 'RETURNED', 'RETURN_TO_SENDER' => TrackingStatus::RETURNED,
            'UNDELIVERABLE', 'DELIVERY_ATTEMPTED', 'LOST', 'DAMAGED', 'EXCEPTION' => TrackingStatus::EXCEPTION,
            'PRE_TRANSIT', 'CREATED', 'LABEL_CREATED', 'READY_FOR_RECEIVE' => TrackingStatus::PENDING,
            'IN_TRANSIT', 'RECEIVED', 'PICKED_UP', 'ARRIVED', 'DEPARTED' => TrackingStatus::IN_TRANSIT,
            default => str_contains($d, 'delivered') ? TrackingStatus::DELIVERED : ('' === (string) $code ? TrackingStatus::UNKNOWN : TrackingStatus::IN_TRANSIT),
        };
    }
}
