<?php

declare(strict_types=1);

use App\Controller\AdminController;
use App\Controller\ArticleController;
use App\Controller\AuthController;
use App\Controller\ClientController;
use App\Controller\PasswordResetController;
use App\Controller\TicketController;
use App\Core\ApresReponse;
use App\Core\Auth;
use App\Core\Env;
use App\Core\Request;
use App\Core\Router;
use App\Core\SecurityHeaders;
use App\Core\View;
use App\Core\Session;

require __DIR__ . '/../vendor/autoload.php';

// Toute la sortie est retenue jusqu'a ApresReponse::terminer(), qui peut
// ainsi envoyer la reponse complete avant d'executer les taches differees.
ob_start();

Env::load(__DIR__ . '/../.env');
SecurityHeaders::envoyer();
Session::start();

$router = new Router();

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

$router->get('/admin/techniciens', [AdminController::class, 'techniciens'], auth: true, admin: true);
$router->post('/admin/techniciens', [AdminController::class, 'creerTechnicien'], auth: true, admin: true);

// Une seule instance par requete, partagee par le routeur, les controleurs
// et le layout : l'utilisateur connecte n'est charge qu'une fois.
$auth = new Auth();
View::utiliserAuth($auth);

$router->dispatch(Request::fromGlobals(), $auth);
ApresReponse::terminer();
