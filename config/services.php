<?php

declare(strict_types=1);

/*
 * Racine de composition : le seul endroit ou les objets de l'application
 * sont crees et relies entre eux. Les classes, elles, se contentent de
 * declarer ce dont elles ont besoin dans leur constructeur.
 *
 * Remplacer une implementation (ex. un autre Mailer, une autre base) se
 * fait ici, sans toucher aux classes qui l'utilisent.
 */

use App\Controller\AdminController;
use App\Controller\ArticleController;
use App\Controller\AuthController;
use App\Controller\ClientController;
use App\Controller\NotificationController;
use App\Controller\PasswordResetController;
use App\Controller\TicketController;
use App\Core\Auth;
use App\Core\Conteneur;
use App\Core\Database;
use App\Core\Env;
use App\Core\Mail\Mailer;
use App\Core\Mail\MailerFactory;
use App\Core\RateLimiter;
use App\Repository\ArticleRepository;
use App\Repository\ClientRepository;
use App\Repository\CommandeRepository;
use App\Repository\NotificationRepository;
use App\Repository\PasswordResetRepository;
use App\Repository\TicketRepository;
use App\Repository\UserRepository;
use App\Service\Notifieur;
use App\Service\TicketService;

return static function (Conteneur $c): void {
    // Infrastructure
    $c->set(PDO::class, static fn (): PDO => Database::connection());
    $c->set(Mailer::class, static fn (): Mailer => MailerFactory::depuisEnv());
    $c->set(RateLimiter::class, static fn (Conteneur $c): RateLimiter => new RateLimiter($c->get(PDO::class)));

    // Acces aux donnees
    foreach ([
        ArticleRepository::class,
        ClientRepository::class,
        CommandeRepository::class,
        NotificationRepository::class,
        PasswordResetRepository::class,
        TicketRepository::class,
        UserRepository::class,
    ] as $repository) {
        $c->set($repository, static fn (Conteneur $c): object => new $repository($c->get(PDO::class)));
    }

    // Authentification : une instance par requete, partagee par le routeur,
    // les controleurs et le layout (l'utilisateur n'est charge qu'une fois).
    $c->set(Auth::class, static fn (Conteneur $c): Auth => new Auth($c->get(UserRepository::class)));

    // Services metier
    $c->set(Notifieur::class, static fn (Conteneur $c): Notifieur => new Notifieur(
        $c->get(NotificationRepository::class),
        $c->get(UserRepository::class),
        $c->get(Mailer::class),
        // Base des liens dans les emails : lue dans la config, jamais dans
        // l'en-tete Host de la requete (falsifiable).
        (string) Env::get('APP_URL', 'http://localhost:8090'),
    ));
    $c->set(TicketService::class, static fn (Conteneur $c): TicketService => new TicketService(
        $c->get(TicketRepository::class),
        $c->get(CommandeRepository::class),
        $c->get(UserRepository::class),
        $c->get(Notifieur::class),
    ));

    // Controleurs
    $c->set(AuthController::class, static fn (Conteneur $c): AuthController => new AuthController(
        $c->get(Auth::class),
        $c->get(RateLimiter::class),
    ));
    $c->set(PasswordResetController::class, static fn (Conteneur $c): PasswordResetController => new PasswordResetController(
        $c->get(UserRepository::class),
        $c->get(PasswordResetRepository::class),
        $c->get(Mailer::class),
        $c->get(RateLimiter::class),
    ));
    $c->set(TicketController::class, static fn (Conteneur $c): TicketController => new TicketController(
        $c->get(Auth::class),
        $c->get(TicketService::class),
        $c->get(TicketRepository::class),
        $c->get(CommandeRepository::class),
        $c->get(UserRepository::class),
        $c->get(NotificationRepository::class),
    ));
    $c->set(NotificationController::class, static fn (Conteneur $c): NotificationController => new NotificationController(
        $c->get(Auth::class),
        $c->get(NotificationRepository::class),
    ));
    $c->set(ClientController::class, static fn (Conteneur $c): ClientController => new ClientController($c->get(ClientRepository::class)));
    $c->set(ArticleController::class, static fn (Conteneur $c): ArticleController => new ArticleController($c->get(ArticleRepository::class)));
    $c->set(AdminController::class, static fn (Conteneur $c): AdminController => new AdminController($c->get(UserRepository::class)));
};
