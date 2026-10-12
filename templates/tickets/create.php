<?php
/** @var list<array{id:int, date_commande:string, client_nom_complet:string}> $commandes */
/** @var list<\App\Enum\TicketType> $types */
/** @var array<string, string> $erreurs message par champ */
/** @var array<string, string> $anciennes valeurs saisies */
/** @var int $longueurMaxDescription */

$ancienne = static fn (string $champ): string => $anciennes[$champ] ?? '';
?>
<a href="/tickets" class="back-link"><?= icone('retour') ?> Tous les tickets</a>

<div class="page-header">
    <div>
        <h1>Nouveau ticket</h1>
        <p class="page-header__subtitle">Le ticket est cree au statut "Nouveau", sans technicien assigne.</p>
    </div>
</div>

<div class="card narrow">
    <div class="card__body">
        <?php if ($erreurs !== []): ?>
            <p class="alert alert-error">Le ticket n'a pas ete cree : corrigez les champs signales.</p>
        <?php endif; ?>
        <form action="/tickets" method="post" class="form">
            <?= csrf_champ() ?>
            <div class="field">
                <label for="commande_id" class="field__label">Commande concernee</label>
                <select name="commande_id" id="commande_id" required<?= champ_invalide($erreurs, 'commande_id') ?>>
                    <option value="">-- Choisir --</option>
                    <?php foreach ($commandes as $commande): ?>
                        <option value="<?= $commande['id'] ?>"<?= $ancienne('commande_id') === (string) $commande['id'] ? ' selected' : '' ?>>
                            #<?= $commande['id'] ?> — <?= e($commande['client_nom_complet']) ?> (<?= e($commande['date_commande']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= champ_erreur($erreurs, 'commande_id') ?>
            </div>

            <div class="field">
                <label for="type" class="field__label">Type de ticket</label>
                <select name="type" id="type" required<?= champ_invalide($erreurs, 'type') ?>>
                    <option value="">-- Choisir --</option>
                    <?php foreach ($types as $type): ?>
                        <option value="<?= e($type->value) ?>"<?= $ancienne('type') === $type->value ? ' selected' : '' ?>><?= e($type->label()) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= champ_erreur($erreurs, 'type') ?>
            </div>

            <div class="field">
                <label for="description" class="field__label">Description <span class="field__hint">(facultative, <?= $longueurMaxDescription ?> caracteres max.)</span></label>
                <textarea name="description" id="description" rows="5" maxlength="<?= $longueurMaxDescription ?>"<?= champ_invalide($erreurs, 'description') ?>><?= e($ancienne('description')) ?></textarea>
                <?= champ_erreur($erreurs, 'description') ?>
            </div>

            <div class="form__actions">
                <button type="submit" class="btn">Creer le ticket</button>
                <a href="/tickets" class="btn btn--secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>
