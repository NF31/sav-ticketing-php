<?php
/** @var list<\App\Entity\User> $utilisateurs */
/** @var ?string $error */
?>
<div class="page-header">
    <h1>Equipe</h1>
</div>

<table class="table">
    <thead>
        <tr><th>Nom</th><th>Email</th><th>Role</th><th>Poste</th></tr>
    </thead>
    <tbody>
        <?php foreach ($utilisateurs as $u): ?>
            <tr>
                <td><?= e($u->nomComplet()) ?></td>
                <td><?= e($u->email) ?></td>
                <td><?= e($u->role->label()) ?></td>
                <td><?= e($u->poste?->label() ?? '—') ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="card card-narrow">
    <h2>Ajouter un technicien</h2>
    <?php if ($error): ?>
        <p class="alert alert-error"><?= e($error) ?></p>
    <?php endif; ?>
    <form action="/admin/techniciens" method="post">
        <?= csrf_champ() ?>
        <label for="nom">Nom</label>
        <input type="text" id="nom" name="nom" required>

        <label for="prenom">Prenom</label>
        <input type="text" id="prenom" name="prenom" required>

        <label for="email">Email</label>
        <input type="email" id="email" name="email" required>

        <label for="password">Mot de passe (8 caracteres min.)</label>
        <input type="password" id="password" name="password" required minlength="8">

        <label for="poste">Poste</label>
        <select name="poste" id="poste">
            <option value="sav">SAV terrain</option>
            <option value="hotline">Hotline</option>
        </select>

        <button type="submit">Creer</button>
    </form>
</div>
