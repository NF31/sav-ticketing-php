<?php
/** @var list<\App\Entity\User> $utilisateurs */
/** @var list<\App\Enum\Poste> $postes */
/** @var array<string, string> $erreurs message par champ */
/** @var array<string, string> $anciennes valeurs saisies (jamais le mot de passe) */
/** @var ?string $success */
/** @var int $longueurMaxNom */
/** @var int $longueurMaxEmail */
/** @var int $longueurMinMotDePasse */

$ancienne = static fn (string $champ): string => e($anciennes[$champ] ?? '');
$invalide = static fn (string $champ): string => isset($erreurs[$champ]) ? ' aria-invalid="true" aria-describedby="erreur-' . $champ . '"' : '';
$erreur = static fn (string $champ): string => isset($erreurs[$champ])
    ? '<p class="field-error" id="erreur-' . $champ . '">' . e($erreurs[$champ]) . '</p>'
    : '';
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
    <?php if ($success): ?>
        <p class="alert alert-success"><?= e($success) ?></p>
    <?php endif; ?>
    <?php if ($erreurs !== []): ?>
        <p class="alert alert-error">Le technicien n'a pas ete cree : corrigez les champs signales.</p>
    <?php endif; ?>
    <form action="/admin/techniciens" method="post">
        <?= csrf_champ() ?>
        <label for="nom">Nom</label>
        <input type="text" id="nom" name="nom" value="<?= $ancienne('nom') ?>" required maxlength="<?= $longueurMaxNom ?>" autocomplete="off"<?= $invalide('nom') ?>>
        <?= $erreur('nom') ?>

        <label for="prenom">Prenom</label>
        <input type="text" id="prenom" name="prenom" value="<?= $ancienne('prenom') ?>" required maxlength="<?= $longueurMaxNom ?>" autocomplete="off"<?= $invalide('prenom') ?>>
        <?= $erreur('prenom') ?>

        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= $ancienne('email') ?>" required maxlength="<?= $longueurMaxEmail ?>" autocomplete="off"<?= $invalide('email') ?>>
        <?= $erreur('email') ?>

        <label for="password">Mot de passe (<?= $longueurMinMotDePasse ?> caracteres min.)</label>
        <input type="password" id="password" name="password" required minlength="<?= $longueurMinMotDePasse ?>" autocomplete="new-password"<?= $invalide('password') ?>>
        <?= $erreur('password') ?>

        <label for="password_confirmation">Confirmer le mot de passe</label>
        <input type="password" id="password_confirmation" name="password_confirmation" required minlength="<?= $longueurMinMotDePasse ?>" autocomplete="new-password"<?= $invalide('password_confirmation') ?>>
        <?= $erreur('password_confirmation') ?>

        <label for="poste">Poste</label>
        <select name="poste" id="poste" required<?= $invalide('poste') ?>>
            <option value="">Choisir un poste</option>
            <?php foreach ($postes as $poste): ?>
                <option value="<?= e($poste->value) ?>"<?= ($anciennes['poste'] ?? '') === $poste->value ? ' selected' : '' ?>><?= e($poste->label()) ?></option>
            <?php endforeach; ?>
        </select>
        <?= $erreur('poste') ?>

        <button type="submit">Creer</button>
    </form>
</div>
