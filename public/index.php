<?php

declare(strict_types=1);

use App\Controller\AdminController;
use App\Controller\ArticleController;
use App\Controller\AuthController;
use App\Controller\ClientController;
use App\Controller\NotificationController;
use App\Controller\PasswordResetController;
use App\Controller\TicketController;
use App\Core\ApresReponse;
use App\Core\Auth;
use App\Core\Conteneur;
use App\Core\Env;
use App\Core\Request;
use App\Core\Router;
use App\Core\SecurityHeaders;
use App\Core\Session;
use App\Core\View;
use App\Repository\NotificationRepository;

require __DIR__ . '/../vendor/autoload.php';

// Toute la sortie est retenue jusqu'a ApresReponse::terminer(), qui peut
// ainsi envoyer la reponse complete avant d'executer les taches differees.
ob_start();

Env::load(__DIR__ . '/../.env');

$conteneur = new Conteneur();
(require __DIR__ . '/../config/services.php')($conteneur);

SecurityHeaders::envoyer();
Session::start();

$router = new Router($conteneur);

$router->get('/', [AuthController::class, 'showLogin']);
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->get('/mot-de-passe-oublie', [PasswordResetController::class, 'showDemande']);
$router->post('/mot-de-passe-oublie', [PasswordResetController::class, 'envoyerLien']);
$router->get('/mot-de-passe/reinitialiser/{token}', [PasswordResetController::class, 'showReinitialisation']);
$router->post('/mot-de-passe/reinitialiser/{token}', [PasswordResetController::class, 'reinitialiser']);

$router->get('/tickets', [TicketController::class, 'index'], auth: true);
$router->get('/tickets/create', [TicketController::class, 'create'], auth: true);
$router->post('/tickets', [TicketController::class, 'store'], auth: true);
$router->get('/tickets/{id}', [TicketController::class, 'show'], auth: true);
$router->post('/tickets/{id}/statut', [TicketController::class, 'changerStatut'], auth: true);
$router->post('/tickets/{id}/assigner', [TicketController::class, 'assigner'], auth: true);

$router->get('/clients', [ClientController::class, 'index'], auth: true);
$router->get('/clients/create', [ClientController::class, 'create'], auth: true);
$router->post('/clients', [ClientController::class, 'store'], auth: true);

$router->get('/articles', [ArticleController::class, 'index'], auth: true);

$router->get('/notifications', [NotificationController::class, 'index'], auth: true);
$router->post('/notifications/tout-lire', [NotificationController::class, 'toutMarquerLu'], auth: true);

$router->get('/admin/techniciens', [AdminController::class, 'techniciens'], auth: true, admin: true);
$router->post('/admin/techniciens', [AdminController::class, 'creerTechnicien'], auth: true, admin: true);

// Le layout reutilise l'instance d'Auth de la requete (deja chargee par le
// routeur pour le controle d'acces), et affiche le nombre de notifications
// non lues.
View::utiliserAuth($conteneur->get(Auth::class));
View::utiliserNotifications($conteneur->get(NotificationRepository::class));

$router->dispatch(Request::fromGlobals());
ApresReponse::terminer();
