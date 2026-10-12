<?php

declare(strict_types=1);

namespace App\Integration\Kbeauty;

use JsonException;
use JsonSchema\Validator;

/**
 * Verifie un message return_request.submitted contre le contrat partage avec
 * kbeauty-shop (contracts/rabbitmq/return-request-submitted.schema.json),
 * avant tout traitement : un message recu est une donnee non fiable, au meme
 * titre qu'un formulaire envoye par un inconnu.
 */
final class ContratRetourSoumis
{
    public const string SCHEMA = __DIR__ . '/../../../contracts/rabbitmq/return-request-submitted.schema.json';

    private readonly object $schema;

    public function __construct(string $cheminSchema = self::SCHEMA)
    {
        $this->schema = json_decode((string) file_get_contents($cheminSchema), flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @return object le message decode, conforme au contrat
     * @throws MessageHorsContrat
     */
    public function valider(string $corps): object
    {
        try {
            $message = json_decode($corps, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new MessageHorsContrat(['JSON invalide : ' . $e->getMessage()]);
        }

        // Les "format" (email, date-time) sont verifies par defaut, pas
        // seulement documentes (ContratRetourSoumisTest le confirme).
        $validateur = new Validator();
        $validateur->validate($message, $this->schema);

        if (!$validateur->isValid() || !is_object($message)) {
            throw new MessageHorsContrat(array_map(
                static fn (array $erreur): string => ($erreur['property'] !== '' ? $erreur['property'] : 'message') . ' : ' . $erreur['message'],
                $validateur->getErrors(),
            ) ?: ['le message doit etre un objet JSON']);
        }

        return $message;
    }
}
