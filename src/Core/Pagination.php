<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Decoupage d'une liste en pages : numero de page courant, nombre de pages,
 * decalage SQL (OFFSET). Ne connait ni la base ni HTTP : le controleur lui
 * donne le parametre ?page= brut et le nombre total d'elements.
 */
final readonly class Pagination
{
    public const int PAR_PAGE = 20;

    public int $page;
    public int $nombrePages;

    private function __construct(public int $total, int $page, public int $parPage)
    {
        $this->nombrePages = max(1, (int) ceil($total / $parPage));
        // Page hors limites (?page=999 apres suppression de tickets) :
        // ramenee a la derniere page plutot qu'une liste vide.
        $this->page = min(max(1, $page), $this->nombrePages);
    }

    /**
     * @param ?string $pageDemandee valeur brute de ?page= ; absente, vide ou
     *                              non numerique (?page=abc, ?page=-2) : page 1
     */
    public static function depuis(?string $pageDemandee, int $total, int $parPage = self::PAR_PAGE): self
    {
        $page = $pageDemandee !== null && ctype_digit($pageDemandee) ? (int) $pageDemandee : 1;

        return new self($total, $page, $parPage);
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->parPage;
    }

    public function aPrecedente(): bool
    {
        return $this->page > 1;
    }

    public function aSuivante(): bool
    {
        return $this->page < $this->nombrePages;
    }

    /** Rang (a partir de 1) du premier element affiche ; 0 si la liste est vide. */
    public function premier(): int
    {
        return $this->total === 0 ? 0 : $this->offset() + 1;
    }

    public function dernier(): int
    {
        return min($this->offset() + $this->parPage, $this->total);
    }
}
