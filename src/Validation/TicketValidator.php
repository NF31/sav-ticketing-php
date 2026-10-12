<?php

declare(strict_types=1);

namespace App\Validation;

use App\Enum\TicketType;
use Closure;

/**
 * Validation du formulaire de creation d'un ticket. Sans acces a la base :
 * l'existence de la commande est fournie par l'appelant.
 */
final class TicketValidator
{
    // Limite metier, bien en dessous du maximum de la colonne TEXT (65 535
    // octets, soit au moins 16 383 caracteres en UTF-8) : une description
    // de ticket n'a pas a depasser quelques paragraphes.
    public const int LONGUEUR_MAX_DESCRIPTION = 5000;

    /**
     * @param array<string, mixed> $saisie typiquement $_POST
     * @return array{commande_id: string, type: string, description: string}
     */
    public static function normaliser(array $saisie): array
    {
        return [
            'commande_id' => trim(Regles::texte($saisie, 'commande_id')),
            'type' => trim(Regles::texte($saisie, 'type')),
            // Retours a la ligne conserves (affiches avec nl2br), seuls les
            // blancs de debut et de fin sont retires.
            'description' => trim(str_replace("\r\n", "\n", Regles::texte($saisie, 'description'))),
        ];
    }

    /**
     * @param array{commande_id: string, type: string, description: string} $donnees issues de normaliser()
     * @param Closure(int): bool $commandeExiste
     * @return array<string, string> message d'erreur par champ, vide si tout est valide
     */
    public static function valider(array $donnees, Closure $commandeExiste): array
    {
        return array_filter([
            'commande_id' => match (true) {
                $donnees['commande_id'] === '' => 'La commande est obligatoire.',
                !ctype_digit($donnees['commande_id']) || !$commandeExiste((int) $donnees['commande_id']) => "Cette commande n'existe pas.",
                default => null,
            },
            'type' => match (true) {
                $donnees['type'] === '' => 'Le type de ticket est obligatoire.',
                !in_array(TicketType::tryFrom($donnees['type']), TicketType::saisissables(), true) => 'Type de ticket inconnu.',
                default => null,
            },
            'description' => mb_strlen($donnees['description']) > self::LONGUEUR_MAX_DESCRIPTION
                ? 'La description ne doit pas depasser ' . self::LONGUEUR_MAX_DESCRIPTION . ' caracteres.'
                : null,
        ]);
    }
}
