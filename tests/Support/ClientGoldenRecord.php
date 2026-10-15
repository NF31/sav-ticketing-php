<?php

declare(strict_types=1);

namespace Tests\Support;

/** Message customer.golden_record.updated tel que le publie kbeauty-customer-mdm. */
final class ClientGoldenRecord
{
    /** @param array<string, mixed> $remplacements champs de premier niveau a remplacer */
    public static function json(array $remplacements = []): string
    {
        return json_encode(array_merge([
            'eventType' => 'customer.golden_record.updated',
            'sourceUserId' => 7,
            'name' => 'Jeanne Martin',
            'email' => 'jeanne.martin@exemple.test',
            'updatedAt' => '2026-10-15T16:45:00Z',
        ], $remplacements), JSON_THROW_ON_ERROR);
    }
}
