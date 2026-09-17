<?php /** @var ?string $error */ ?>
<div class="card card-narrow">
    <h1>Nouveau client</h1>
    <?php if ($error): ?>
        <p class="alert alert-error"><?= e($error) ?></p>
    <?php endif; ?>
    <form action="/clients" method="post">
        <label for="nom">Nom</label>
        <input type="text" id="nom" name="nom" required>

        <label for="prenom">Prenom</label>
        <input type="text" id="prenom" name="prenom" required>

        <label for="email">Email</label>
        <input type="email" id="email" name="email" required>

        <label for="telephone">Telephone</label>
        <input type="text" id="telephone" name="telephone">

        <label for="adresse_rue">Rue</label>
        <input type="text" id="adresse_rue" name="adresse_rue">

        <label for="adresse_ville">Ville</label>
        <input type="text" id="adresse_ville" name="adresse_ville">

        <label for="adresse_code_postal">Code postal</label>
        <input type="text" id="adresse_code_postal" name="adresse_code_postal">

        <button type="submit">Creer</button>
    </form>
</div>
