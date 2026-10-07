<?php
/** @var string $jeton */
/** @var int $longueurMin */
/** @var ?string $error */
?>
<div class="card card-narrow">
    <h1>Nouveau mot de passe</h1>
    <?php if ($error): ?>
        <p class="alert alert-error"><?= e($error) ?></p>
    <?php endif; ?>
    <form action="/mot-de-passe/reinitialiser/<?= e($jeton) ?>" method="post">
        <label for="password">Nouveau mot de passe (<?= $longueurMin ?> caracteres minimum)</label>
        <input type="password" id="password" name="password" minlength="<?= $longueurMin ?>" required autofocus autocomplete="new-password">

        <label for="password_confirmation">Confirmer le mot de passe</label>
        <input type="password" id="password_confirmation" name="password_confirmation" minlength="<?= $longueurMin ?>" required autocomplete="new-password">

        <button type="submit">Changer le mot de passe</button>
    </form>
</div>
