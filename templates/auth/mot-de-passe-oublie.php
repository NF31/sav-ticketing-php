<?php /** @var ?string $error */ ?>
<div class="card auth__card">
    <h1 class="auth__title">Mot de passe oublie</h1>
    <p class="auth__subtitle">Saisissez l'email de votre compte : vous recevrez un lien pour choisir un nouveau mot de passe.</p>

    <?php if ($error): ?>
        <p class="alert alert-error"><?= e($error) ?></p>
    <?php endif; ?>

    <form action="/mot-de-passe-oublie" method="post" class="form">
        <?= csrf_champ() ?>
        <div class="field">
            <label for="email" class="field__label">Email</label>
            <input type="email" id="email" name="email" required autofocus autocomplete="username">
        </div>

        <button type="submit" class="btn btn--block">Envoyer le lien</button>
    </form>
</div>
<p class="auth__footer">
    <a href="/login" class="back-link"><?= icone('retour') ?> Retour a la connexion</a>
</p>
