<?php
/** @var list<array{id:int, date_commande:string, client_nom_complet:string}> $commandes */
/** @var list<\App\Enum\TicketType> $types */
/** @var array<string, string> $erreurs message par champ */
/** @var array<string, string> $anciennes valeurs saisies */
/** @var int $longueurMaxDescription */

$ancienne = static fn (string $champ): string => $anciennes[$champ] ?? '';
?>
<div class="card card-narrow">
    <h1>Nouveau ticket</h1>
    <?php if ($erreurs !== []): ?>
        <p class="alert alert-error">Le ticket n'a pas ete cree : corrigez les champs signales.</p>
    <?php endif; ?>
    <form action="/tickets" method="post">
        <?= csrf_champ() ?>
        <label for="commande_id">Commande concernee</label>
        <select name="commande_id" id="commande_id" required<?= champ_invalide($erreurs, 'commande_id') ?>>
            <option value="">-- Choisir --</option>
            <?php foreach ($commandes as $commande): ?>
                <option value="<?= $commande['id'] ?>"<?= $ancienne('commande_id') === (string) $commande['id'] ? ' selected' : '' ?>>
                    #<?= $commande['id'] ?> — <?= e($commande['client_nom_complet']) ?> (<?= e($commande['date_commande']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <?= champ_erreur($erreurs, 'commande_id') ?>

        <label for="type">Type de ticket</label>
        <select name="type" id="type" required<?= champ_invalide($erreurs, 'type') ?>>
            <option value="">-- Choisir --</option>
            <?php foreach ($types as $type): ?>
                <option value="<?= e($type->value) ?>"<?= $ancienne('type') === $type->value ? ' selected' : '' ?>><?= e($type->label()) ?></option>
            <?php endforeach; ?>
        </select>
        <?= champ_erreur($erreurs, 'type') ?>

        <label for="description">Description (facultative, <?= $longueurMaxDescription ?> caracteres max.)</label>
        <textarea name="description" id="description" rows="4" maxlength="<?= $longueurMaxDescription ?>"<?= champ_invalide($erreurs, 'description') ?>><?= e($ancienne('description')) ?></textarea>
        <?= champ_erreur($erreurs, 'description') ?>

        <button type="submit">Creer le ticket</button>
    </form>
</div>
