<?php

declare(strict_types=1);

namespace App\Service\Exception;

use DomainException;

/**
 * Action refusee par une regle metier (ex. reassigner un ticket termine).
 * Le message est destine a l'utilisateur : le controleur l'affiche tel quel.
 */
final class RegleMetierViolee extends DomainException
{
}
