<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Core\View;
use App\Entity\User;
use App\Repository\NotificationRepository;

final class NotificationController
{
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
