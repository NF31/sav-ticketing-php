<?php /** @var ?string $error */ ?>
<div class="card card-narrow">
    <h1>Connexion</h1>
    <?php if ($error): ?>
        <p class="alert alert-error"><?= e($error) ?></p>
    <?php endif; ?>
    <form action="/login" method="post">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required autofocus>

        <label for="password">Mot de passe</label>
        <input type="password" id="password" name="password" required>

        <button type="submit">Se connecter</button>
    </form>
    <p class="hint">Demo : admin@sav.local / karim.diallo@sav.local / lea.nguyen@sav.local — mot de passe <code>password</code></p>
</div>
