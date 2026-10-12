<?php

declare(strict_types=1);

namespace App\Service\Exception;

use DomainException;

/**
 * Formulaire refuse par la validation : porte les erreurs par champ et les
 * valeurs normalisees, que le controleur renvoie au formulaire.
 */
final class SaisieInvalide extends DomainException
{
    /**
     * @param array<string, string> $erreurs
     * @param array<string, string> $donnees
     */
    public function __construct(
        public readonly array $erreurs,
        public readonly array $donnees,
    ) {
        parent::__construct('Saisie invalide : ' . implode(', ', array_keys($erreurs)) . '.');
    }
}
