<?php
/** @var string $jeton */
/** @var int $longueurMin */
/** @var ?string $error */
?>
<div class="card auth__card">
    <h1 class="auth__title">Nouveau mot de passe</h1>
    <p class="auth__subtitle">Choisissez un mot de passe d'au moins <?= $longueurMin ?> caracteres.</p>

    <?php if ($error): ?>
        <p class="alert alert-error"><?= e($error) ?></p>
    <?php endif; ?>

    <form action="/mot-de-passe/reinitialiser/<?= e($jeton) ?>" method="post" class="form">
        <?= csrf_champ() ?>
        <div class="field">
            <label for="password" class="field__label">Nouveau mot de passe</label>
            <input type="password" id="password" name="password" minlength="<?= $longueurMin ?>" required autofocus autocomplete="new-password">
        </div>

        <div class="field">
            <label for="password_confirmation" class="field__label">Confirmer le mot de passe</label>
            <input type="password" id="password_confirmation" name="password_confirmation" minlength="<?= $longueurMin ?>" required autocomplete="new-password">
        </div>

        <button type="submit" class="btn btn--block">Changer le mot de passe</button>
    </form>
</div>
