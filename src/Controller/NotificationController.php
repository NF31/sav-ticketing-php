<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Core\View;
use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;

final class NotificationController
{
    /** Au-dela, les notifications arrivees d'un coup restent dans le badge et la page. */
    private const int NOUVELLES_MAX = 5;

    public function __construct(
        private readonly Auth $auth,
        private readonly NotificationRepository $notifications,
    ) {
    }

    public function index(Request $request): void
    {
        View::render('notifications/index', [
            'notifications' => $this->notifications->recentes($this->utilisateur()->id),
            'limite' => NotificationRepository::LIMITE_LISTE,
        ]);
    }

    /**
     * Etat interroge par public/assets/notifications.js toutes les 15 s :
     * nombre de non lues (badge) et notifications arrivees depuis ?depuis=
     * (affichees en direct). Sans ?depuis= (premier appel de la page), seul
     * le point de depart est renvoye : rien d'ancien n'est affiche.
     */
    public function etat(Request $request): void
    {
        $userId = $this->utilisateur()->id;
        $depuis = $request->query('depuis');

        $nouvelles = $depuis !== null && ctype_digit($depuis)
            ? $this->notifications->nonLuesDepuis($userId, (int) $depuis, self::NOUVELLES_MAX)
            : [];

        header('Content-Type: application/json; charset=utf-8');
        // Reponse propre a l'utilisateur et toujours fraiche : ni cache
        // navigateur ni cache partage.
        header('Cache-Control: no-store, private');

        echo json_encode([
            'nonLues' => $this->notifications->compterNonLues($userId),
            'derniereId' => $this->notifications->derniereId($userId),
            'nouvelles' => array_map(static fn (Notification $n): array => [
                'id' => $n->id,
                'message' => $n->message,
                'url' => '/tickets/' . $n->ticketId,
            ], $nouvelles),
        ], JSON_THROW_ON_ERROR);
    }

    public function toutMarquerLu(Request $request): void
    {
        $this->notifications->marquerToutesLues($this->utilisateur()->id);
        header('Location: /notifications');
    }

    /** Routes protegees (auth: true) : le routeur a deja verifie la connexion. */
    private function utilisateur(): User
    {
        return $this->auth->user() ?? throw new \LogicException('Utilisateur connecte attendu sur une route protegee.');
    }
}
