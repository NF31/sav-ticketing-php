<?php /** @var list<\App\Entity\Client> $clients */ ?>
<div class="page-header">
    <h1>Clients</h1>
    <a class="btn" href="/clients/create">+ Nouveau client</a>
</div>
<table class="table">
    <thead>
        <tr><th>Nom</th><th>Email</th><th>Telephone</th><th>Ville</th></tr>
    </thead>
    <tbody>
        <?php foreach ($clients as $client): ?>
            <tr>
                <td><?= e($client->nomComplet()) ?></td>
                <td><?= e($client->email) ?></td>
                <td><?= e($client->telephone ?? '—') ?></td>
                <td><?= e($client->adresseVille ?? '—') ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
