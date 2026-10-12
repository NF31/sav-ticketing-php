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
?>
<div class="page-header">
    <div>
        <h1>Equipe</h1>
        <p class="page-header__subtitle">Administrateurs et techniciens ayant acces a l'application.</p>
    </div>
</div>

<div class="stack">
    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Nom</th><th>Email</th><th>Role</th><th>Poste</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($utilisateurs as $u): ?>
                        <tr>
                            <td>
                                <span class="person">
                                    <span class="avatar avatar--sm"><?= e(initiales($u->nomComplet())) ?></span>
                                    <?= e($u->nomComplet()) ?>
                                </span>
                            </td>
                            <td class="text-muted"><?= e($u->email) ?></td>
                            <td><span class="badge<?= $u->role === \App\Enum\Role::Admin ? ' badge--primary' : '' ?>"><?= e($u->role->label()) ?></span></td>
                            <td class="text-muted"><?= e($u->poste?->label() ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <section class="card narrow">
        <div class="card__header"><h2>Ajouter un technicien</h2></div>
        <div class="card__body">
            <?php if ($success): ?>
                <p class="alert alert-success"><?= e($success) ?></p>
            <?php endif; ?>
            <?php if ($erreurs !== []): ?>
                <p class="alert alert-error">Le technicien n'a pas ete cree : corrigez les champs signales.</p>
            <?php endif; ?>
            <form action="/admin/techniciens" method="post" class="form">
                <?= csrf_champ() ?>
                <div class="form__row">
                    <div class="field">
                        <label for="prenom" class="field__label">Prenom</label>
                        <input type="text" id="prenom" name="prenom" value="<?= $ancienne('prenom') ?>" required maxlength="<?= $longueurMaxNom ?>" autocomplete="off"<?= champ_invalide($erreurs, 'prenom') ?>>
                        <?= champ_erreur($erreurs, 'prenom') ?>
                    </div>
                    <div class="field">
                        <label for="nom" class="field__label">Nom</label>
                        <input type="text" id="nom" name="nom" value="<?= $ancienne('nom') ?>" required maxlength="<?= $longueurMaxNom ?>" autocomplete="off"<?= champ_invalide($erreurs, 'nom') ?>>
                        <?= champ_erreur($erreurs, 'nom') ?>
                    </div>
                </div>

                <div class="field">
                    <label for="email" class="field__label">Email</label>
                    <input type="email" id="email" name="email" value="<?= $ancienne('email') ?>" required maxlength="<?= $longueurMaxEmail ?>" autocomplete="off"<?= champ_invalide($erreurs, 'email') ?>>
                    <?= champ_erreur($erreurs, 'email') ?>
                </div>

                <div class="field">
                    <label for="password" class="field__label">Mot de passe <span class="field__hint">(<?= $longueurMinMotDePasse ?> caracteres min.)</span></label>
                    <input type="password" id="password" name="password" required minlength="<?= $longueurMinMotDePasse ?>" autocomplete="new-password"<?= champ_invalide($erreurs, 'password') ?>>
                    <?= champ_erreur($erreurs, 'password') ?>
                </div>

                <div class="field">
                    <label for="password_confirmation" class="field__label">Confirmer le mot de passe</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required minlength="<?= $longueurMinMotDePasse ?>" autocomplete="new-password"<?= champ_invalide($erreurs, 'password_confirmation') ?>>
                    <?= champ_erreur($erreurs, 'password_confirmation') ?>
                </div>

                <div class="field">
                    <label for="poste" class="field__label">Poste</label>
                    <select name="poste" id="poste" required<?= champ_invalide($erreurs, 'poste') ?>>
                        <option value="">Choisir un poste</option>
                        <?php foreach ($postes as $poste): ?>
                            <option value="<?= e($poste->value) ?>"<?= ($anciennes['poste'] ?? '') === $poste->value ? ' selected' : '' ?>><?= e($poste->label()) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= champ_erreur($erreurs, 'poste') ?>
                </div>

                <div class="form__actions">
                    <button type="submit" class="btn">Creer le technicien</button>
                </div>
            </form>
        </div>
    </section>
</div>
