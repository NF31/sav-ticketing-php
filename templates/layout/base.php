<?php
/** @var string $content */
/** @var \App\Core\Auth $auth */
$user = $auth->user();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SAV Ticketing</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <header class="topbar">
        <a href="/tickets" class="brand">SAV Ticketing</a>
        <?php if ($user !== null): ?>
            <nav>
                <a href="/tickets">Tickets</a>
                <a href="/clients">Clients</a>
                <a href="/articles">Articles</a>
                <?php if ($user->role === \App\Enum\Role::Admin): ?>
                    <a href="/admin/techniciens">Techniciens</a>
                <?php endif; ?>
            </nav>
            <div class="user-box">
                <span><?= e($user->nomComplet()) ?> (<?= e($user->role->label()) ?>)</span>
                <form action="/logout" method="post">
                    <?= csrf_champ() ?>
                    <button type="submit">Deconnexion</button>
                </form>
            </div>
        <?php endif; ?>
    </header>
    <main class="container">
        <?= $content ?>
    </main>
</body>
</html>
