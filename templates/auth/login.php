<?php
/** @var ?string $error */
/** @var ?string $success */
?>
<div class="card auth__card">
    <h1 class="auth__title">Connexion</h1>
    <p class="auth__subtitle">Accedez a l'espace de suivi des tickets SAV.</p>

    <?php if ($error): ?>
        <p class="alert alert-error"><?= e($error) ?></p>
    <?php endif; ?>
    <?php if ($success): ?>
        <p class="alert alert-success"><?= e($success) ?></p>
    <?php endif; ?>

    <form action="/login" method="post" class="form">
        <?= csrf_champ() ?>
        <div class="field">
            <label for="email" class="field__label">Email</label>
            <input type="email" id="email" name="email" required autofocus autocomplete="username" placeholder="prenom.nom@sav.local">
        </div>

        <div class="field">
            <label for="password" class="field__label">Mot de passe</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
            <a href="/mot-de-passe-oublie" class="hint">Mot de passe oublie ?</a>
        </div>

        <button type="submit" class="btn btn--block">Se connecter</button>
    </form>
</div>
<p class="hint auth__footer">
    Demo : <code>admin@sav.local</code>, <code>karim.diallo@sav.local</code>, <code>lea.nguyen@sav.local</code><br>
    mot de passe <code>password</code>
</p>
