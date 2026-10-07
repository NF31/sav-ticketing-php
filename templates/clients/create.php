<?php
/** @var array<string, string> $erreurs message par champ */
/** @var array<string, string> $anciennes valeurs saisies */
/** @var int $longueurMaxNom */
/** @var int $longueurMaxEmail */
/** @var int $longueurMaxRue */
/** @var int $longueurMaxVille */

$ancienne = static fn (string $champ): string => e($anciennes[$champ] ?? '');
?>
<div class="card card-narrow">
    <h1>Nouveau client</h1>
    <?php if ($erreurs !== []): ?>
        <p class="alert alert-error">Le client n'a pas ete cree : corrigez les champs signales.</p>
    <?php endif; ?>
    <form action="/clients" method="post">
        <?= csrf_champ() ?>
        <label for="nom">Nom</label>
        <input type="text" id="nom" name="nom" value="<?= $ancienne('nom') ?>" required maxlength="<?= $longueurMaxNom ?>"<?= champ_invalide($erreurs, 'nom') ?>>
        <?= champ_erreur($erreurs, 'nom') ?>

        <label for="prenom">Prenom</label>
        <input type="text" id="prenom" name="prenom" value="<?= $ancienne('prenom') ?>" required maxlength="<?= $longueurMaxNom ?>"<?= champ_invalide($erreurs, 'prenom') ?>>
        <?= champ_erreur($erreurs, 'prenom') ?>

        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= $ancienne('email') ?>" required maxlength="<?= $longueurMaxEmail ?>"<?= champ_invalide($erreurs, 'email') ?>>
        <?= champ_erreur($erreurs, 'email') ?>

        <label for="telephone">Telephone (facultatif)</label>
        <input type="tel" id="telephone" name="telephone" value="<?= $ancienne('telephone') ?>" placeholder="06 01 02 03 04" maxlength="20" autocomplete="off"<?= champ_invalide($erreurs, 'telephone') ?>>
        <?= champ_erreur($erreurs, 'telephone') ?>

        <p class="hint">Adresse facultative, mais complete si elle est saisie (necessaire pour les expeditions).</p>

        <label for="adresse_rue">Rue</label>
        <input type="text" id="adresse_rue" name="adresse_rue" value="<?= $ancienne('adresse_rue') ?>" maxlength="<?= $longueurMaxRue ?>"<?= champ_invalide($erreurs, 'adresse_rue') ?>>
        <?= champ_erreur($erreurs, 'adresse_rue') ?>

        <label for="adresse_ville">Ville</label>
        <input type="text" id="adresse_ville" name="adresse_ville" value="<?= $ancienne('adresse_ville') ?>" maxlength="<?= $longueurMaxVille ?>"<?= champ_invalide($erreurs, 'adresse_ville') ?>>
        <?= champ_erreur($erreurs, 'adresse_ville') ?>

        <label for="adresse_code_postal">Code postal</label>
        <input type="text" id="adresse_code_postal" name="adresse_code_postal" value="<?= $ancienne('adresse_code_postal') ?>" inputmode="numeric" pattern="\d{5}" maxlength="5" placeholder="75001"<?= champ_invalide($erreurs, 'adresse_code_postal') ?>>
        <?= champ_erreur($erreurs, 'adresse_code_postal') ?>

        <button type="submit">Creer</button>
    </form>
</div>
