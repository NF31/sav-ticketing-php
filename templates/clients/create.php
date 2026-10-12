<?php
/** @var array<string, string> $erreurs message par champ */
/** @var array<string, string> $anciennes valeurs saisies */
/** @var int $longueurMaxNom */
/** @var int $longueurMaxEmail */
/** @var int $longueurMaxRue */
/** @var int $longueurMaxVille */

$ancienne = static fn (string $champ): string => e($anciennes[$champ] ?? '');
?>
<a href="/clients" class="back-link"><?= icone('retour') ?> Tous les clients</a>

<div class="page-header">
    <h1>Nouveau client</h1>
</div>

<div class="card narrow">
    <div class="card__body">
        <?php if ($erreurs !== []): ?>
            <p class="alert alert-error">Le client n'a pas ete cree : corrigez les champs signales.</p>
        <?php endif; ?>
        <form action="/clients" method="post" class="form">
            <?= csrf_champ() ?>
            <p class="form__legend">Identite</p>
            <div class="form__row">
                <div class="field">
                    <label for="prenom" class="field__label">Prenom</label>
                    <input type="text" id="prenom" name="prenom" value="<?= $ancienne('prenom') ?>" required maxlength="<?= $longueurMaxNom ?>"<?= champ_invalide($erreurs, 'prenom') ?>>
                    <?= champ_erreur($erreurs, 'prenom') ?>
                </div>
                <div class="field">
                    <label for="nom" class="field__label">Nom</label>
                    <input type="text" id="nom" name="nom" value="<?= $ancienne('nom') ?>" required maxlength="<?= $longueurMaxNom ?>"<?= champ_invalide($erreurs, 'nom') ?>>
                    <?= champ_erreur($erreurs, 'nom') ?>
                </div>
            </div>

            <div class="form__row">
                <div class="field">
                    <label for="email" class="field__label">Email</label>
                    <input type="email" id="email" name="email" value="<?= $ancienne('email') ?>" required maxlength="<?= $longueurMaxEmail ?>"<?= champ_invalide($erreurs, 'email') ?>>
                    <?= champ_erreur($erreurs, 'email') ?>
                </div>
                <div class="field">
                    <label for="telephone" class="field__label">Telephone <span class="field__hint">(facultatif)</span></label>
                    <input type="tel" id="telephone" name="telephone" value="<?= $ancienne('telephone') ?>" placeholder="06 01 02 03 04" maxlength="20" autocomplete="off"<?= champ_invalide($erreurs, 'telephone') ?>>
                    <?= champ_erreur($erreurs, 'telephone') ?>
                </div>
            </div>

            <div>
                <p class="form__legend">Adresse</p>
                <p class="hint">Facultative, mais complete si elle est saisie (necessaire pour les expeditions).</p>
            </div>

            <div class="field">
                <label for="adresse_rue" class="field__label">Rue</label>
                <input type="text" id="adresse_rue" name="adresse_rue" value="<?= $ancienne('adresse_rue') ?>" maxlength="<?= $longueurMaxRue ?>"<?= champ_invalide($erreurs, 'adresse_rue') ?>>
                <?= champ_erreur($erreurs, 'adresse_rue') ?>
            </div>

            <div class="form__row">
                <div class="field">
                    <label for="adresse_code_postal" class="field__label">Code postal</label>
                    <input type="text" id="adresse_code_postal" name="adresse_code_postal" value="<?= $ancienne('adresse_code_postal') ?>" inputmode="numeric" pattern="\d{5}" maxlength="5" placeholder="75001"<?= champ_invalide($erreurs, 'adresse_code_postal') ?>>
                    <?= champ_erreur($erreurs, 'adresse_code_postal') ?>
                </div>
                <div class="field">
                    <label for="adresse_ville" class="field__label">Ville</label>
                    <input type="text" id="adresse_ville" name="adresse_ville" value="<?= $ancienne('adresse_ville') ?>" maxlength="<?= $longueurMaxVille ?>"<?= champ_invalide($erreurs, 'adresse_ville') ?>>
                    <?= champ_erreur($erreurs, 'adresse_ville') ?>
                </div>
            </div>

            <div class="form__actions">
                <button type="submit" class="btn">Creer le client</button>
                <a href="/clients" class="btn btn--secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>
