<?php
/** @var list<\App\Entity\Client> $clients */
/** @var ?string $success */
$nombre = count($clients);
?>
<div class="page-header">
    <div>
        <h1>Clients</h1>
        <p class="page-header__subtitle"><?= $nombre ?> client<?= $nombre > 1 ? 's' : '' ?></p>
    </div>
    <a class="btn" href="/clients/create"><?= icone('plus') ?> Nouveau client</a>
</div>

<?php if ($success): ?>
    <p class="alert alert-success"><?= e($success) ?></p>
<?php endif; ?>

<div class="card">
    <?php if ($clients === []): ?>
        <div class="empty-state">
            <?= icone('vide') ?>
            <p>Aucun client.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Nom</th><th>Email</th><th>Telephone</th><th>Ville</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($clients as $client): ?>
                        <tr>
                            <td>
                                <span class="person">
                                    <span class="avatar avatar--sm"><?= e(initiales($client->nomComplet())) ?></span>
                                    <?= e($client->nomComplet()) ?>
                                </span>
                            </td>
                            <td><a href="mailto:<?= e($client->email) ?>"><?= e($client->email) ?></a></td>
                            <td class="mono"><?= e($client->telephone ?? '—') ?></td>
                            <td class="text-muted"><?= e($client->adresseVille ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
