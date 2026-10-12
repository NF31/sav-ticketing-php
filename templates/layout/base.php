<?php
/** @var string $content */
/** @var \App\Core\Auth $auth */
/** @var string $cheminCourant */
/** @var int $notificationsNonLues */
$user = $auth->user();

// [href, libelle, icone, badge] ; Notifications en deuxieme : sur mobile, la
// navigation defile horizontalement et les derniers liens sont caches.
$liens = [
    ['/tickets', 'Tickets', 'ticket'],
    ['/notifications', 'Notifications', 'cloche', $notificationsNonLues],
    ['/clients', 'Clients', 'clients'],
    ['/articles', 'Articles', 'articles'],
];
if ($user?->role === \App\Enum\Role::Admin) {
    $liens[] = ['/admin/techniciens', 'Equipe', 'equipe'];
}

$estActif = static fn (string $href): bool => $cheminCourant === $href || str_starts_with($cheminCourant, $href . '/');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $notificationsNonLues > 0 ? '(' . ($notificationsNonLues > 99 ? '99+' : $notificationsNonLues) . ') ' : '' ?>SAV Ticketing</title>
    <link rel="stylesheet" href="/assets/style.css">
    <?php if ($user !== null): ?>
        <script src="/assets/notifications.js" defer></script>
    <?php endif; ?>
</head>
<body>
<?php if ($user === null): ?>
    <main class="auth">
        <div class="auth__panel">
            <a href="/login" class="auth__brand">
                <span class="sidebar__logo"><?= icone('logo') ?></span>
                SAV Ticketing
            </a>
            <?= $content ?>
        </div>
    </main>
<?php else: ?>
    <div class="app">
        <aside class="sidebar">
            <a href="/tickets" class="sidebar__brand">
                <span class="sidebar__logo"><?= icone('logo') ?></span>
                SAV Ticketing
            </a>

            <nav class="sidebar__nav" aria-label="Navigation principale">
                <?php foreach ($liens as $lien): ?>
                    <?php [$href, $libelle, $icone] = $lien; $badge = $lien[3] ?? 0; ?>
                    <a href="<?= e($href) ?>" class="sidebar__link"<?= $estActif($href) ? ' aria-current="page"' : '' ?>>
                        <?= icone($icone) ?>
                        <?= e($libelle) ?>
                        <?php if ($badge > 0): ?>
                            <span class="sidebar__badge"><?= $badge > 99 ? '99+' : $badge ?><span class="visually-hidden"> non lue<?= $badge > 1 ? 's' : '' ?></span></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="sidebar__user">
                <span class="avatar"><?= e(initiales($user->nomComplet())) ?></span>
                <span class="sidebar__user-info">
                    <span class="sidebar__user-name"><?= e($user->nomComplet()) ?></span>
                    <span class="sidebar__user-role"><?= e($user->role->label()) ?></span>
                </span>
                <form action="/logout" method="post">
                    <?= csrf_champ() ?>
                    <button type="submit" class="btn btn--ghost btn--icon" title="Deconnexion">
                        <?= icone('deconnexion') ?>
                        <span class="visually-hidden">Deconnexion</span>
                    </button>
                </form>
            </div>
        </aside>

        <main class="main">
            <div class="main__inner">
                <?= $content ?>
            </div>
        </main>
    </div>
<?php endif; ?>
</body>
</html>
