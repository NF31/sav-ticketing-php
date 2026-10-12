<?php

declare(strict_types=1);

namespace Tests\Support;

/** Message return_request.submitted tel que le publie kbeauty-shop. */
final class RetourKbeauty
{
    /** @param array<string, mixed> $remplacements champs de premier niveau a remplacer */
    public static function json(array $remplacements = []): string
    {
        return json_encode(array_merge([
            'eventType' => 'return_request.submitted',
            'returnRequestId' => 42,
            'occurredAt' => '2026-10-12T15:30:00Z',
            'order' => ['number' => 'KB-2026-00042', 'placedAt' => '2026-10-01T09:12:00Z', 'currency' => 'EUR'],
            'customer' => ['name' => 'Jeanne Martin', 'email' => 'jeanne.martin@exemple.test'],
            'reason' => 'Flacon arrive fele.',
            'items' => [
                ['productName' => 'Serum Centella', 'variantLabel' => '50 ml', 'quantity' => 2, 'amountCents' => 4900],
                ['productName' => 'Creme Snail', 'variantLabel' => null, 'quantity' => 1, 'amountCents' => 1890],
            ],
        ], $remplacements), JSON_THROW_ON_ERROR);
    }
}
