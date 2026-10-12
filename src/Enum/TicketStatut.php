<?php

declare(strict_types=1);

namespace App\Enum;

enum TicketStatut: string
{
    case Nouveau = 'nouveau';
    case EnCours = 'en_cours';
    case EnAttentePiece = 'en_attente_piece';
    case Expedie = 'expedie';
    case Termine = 'termine';

    public function label(): string
    {
        return match ($this) {
            self::Nouveau => 'Nouveau',
            self::EnCours => 'En cours',
            self::EnAttentePiece => 'En attente de piece',
            self::Expedie => 'Expedie',
            self::Termine => 'Termine',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Nouveau => 'badge-nouveau',
            self::EnCours => 'badge-en-cours',
            self::EnAttentePiece => 'badge-attente',
            self::Expedie => 'badge-expedie',
            self::Termine => 'badge-termine',
        };
    }

    /**
     * Transitions autorisees depuis ce statut : evite qu'un ticket
     * "termine" reparte a "nouveau" par erreur depuis le formulaire.
     *
     * @return list<self>
     */
    public function transitionsAutorisees(): array
    {
        return match ($this) {
            self::Nouveau => [self::EnCours, self::EnAttentePiece],
            self::EnCours => [self::EnAttentePiece, self::Expedie, self::Termine],
            self::EnAttentePiece => [self::EnCours],
            self::Expedie => [self::Termine],
            self::Termine => [],
        };
    }

    public function peutPasserA(self $cible): bool
    {
        return in_array($cible, $this->transitionsAutorisees(), true);
    }

    /** Un ticket termine est clos : on ne change plus son technicien. */
    public function estReassignable(): bool
    {
        return $this !== self::Termine;
    }
}
