<?php
/** @var list<array{id:int, date_commande:string, client_nom_complet:string}> $commandes */
/** @var list<\App\Enum\TicketType> $types */
/** @var ?string $error */
?>
<div class="card card-narrow">
    <h1>Nouveau ticket</h1>
    <?php if ($error): ?>
        <p class="alert alert-error"><?= e($error) ?></p>
    <?php endif; ?>
    <form action="/tickets" method="post">
        <label for="commande_id">Commande concernee</label>
        <select name="commande_id" id="commande_id" required>
            <option value="">-- Choisir --</option>
            <?php foreach ($commandes as $commande): ?>
                <option value="<?= $commande['id'] ?>">
                    #<?= $commande['id'] ?> — <?= e($commande['client_nom_complet']) ?> (<?= e($commande['date_commande']) ?>)
                </option>
            <?php endforeach; ?>
        </select>

        <label for="type">Type de ticket</label>
        <select name="type" id="type" required>
            <?php foreach ($types as $type): ?>
                <option value="<?= e($type->value) ?>"><?= e($type->label()) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="description">Description</label>
        <textarea name="description" id="description" rows="4"></textarea>

        <button type="submit">Creer le ticket</button>
    </form>
</div>
