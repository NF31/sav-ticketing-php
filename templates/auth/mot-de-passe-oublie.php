<?php /** @var ?string $error */ ?>
<div class="card card-narrow">
    <h1>Mot de passe oublie</h1>
    <?php if ($error): ?>
        <p class="alert alert-error"><?= e($error) ?></p>
    <?php endif; ?>
    <p>Saisissez l'email de votre compte : vous recevrez un lien pour choisir un nouveau mot de passe.</p>
    <form action="/mot-de-passe-oublie" method="post">
        <?= csrf_champ() ?>
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required autofocus>

        <button type="submit">Envoyer le lien</button>
    </form>
    <p class="hint"><a href="/login">Retour a la connexion</a></p>
    <p class="hint">En dev, aucun email n'est reellement envoye : le lien est ecrit dans <code>var/mail.log</code>.</p>
</div>
