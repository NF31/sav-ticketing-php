<?php
/** @var \App\Entity\Ticket $ticket */
/** @var list<array> $historique */
/** @var list<\App\Entity\User> $techniciens */
/** @var list<\App\Enum\TicketStatut> $transitions */
/** @var ?string $error */
/** @var ?string $success */
?>
<div class="page-header">
    <h1>Ticket <?= e($ticket->code) ?></h1>
    <span class="badge <?= e($ticket->statut->badgeClass()) ?>"><?= e($ticket->statut->label()) ?></span>
</div>

<?php if ($error): ?>
    <p class="alert alert-error"><?= e($error) ?></p>
<?php endif; ?>
<?php if ($success): ?>
    <p class="alert alert-success"><?= e($success) ?></p>
<?php endif; ?>

<div class="grid-2">
    <div class="card">
        <h2>Details</h2>
        <dl>
            <dt>Client</dt><dd><?= e($ticket->clientNomComplet) ?></dd>
            <dt>Type</dt><dd><?= e($ticket->type->label()) ?></dd>
            <dt>Technicien</dt><dd><?= e($ticket->technicienNomComplet ?? 'Non assigne') ?></dd>
            <dt>Description</dt><dd><?= nl2br(e($ticket->description ?? '—')) ?></dd>
            <dt>Cree le</dt><dd><?= e($ticket->createdAt->format('d/m/Y H:i')) ?></dd>
            <dt>Mis a jour le</dt><dd><?= e($ticket->updatedAt->format('d/m/Y H:i')) ?></dd>
        </dl>

        <?php if ($transitions !== []): ?>
            <form action="/tickets/<?= $ticket->id ?>/statut" method="post" class="inline-form">
                <?= csrf_champ() ?>
                <label for="statut">Changer le statut</label>
                <select name="statut" id="statut">
                    <?php foreach ($transitions as $t): ?>
                        <option value="<?= e($t->value) ?>"><?= e($t->label()) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">Valider</button>
            </form>
        <?php else: ?>
            <p class="hint">Aucune transition possible depuis ce statut.</p>
        <?php endif; ?>

        <?php if ($ticket->statut === \App\Enum\TicketStatut::Termine): ?>
            <p class="hint">Ticket termine : il ne peut plus etre reassigne.</p>
        <?php else: ?>
            <form action="/tickets/<?= $ticket->id ?>/assigner" method="post" class="inline-form">
                <?= csrf_champ() ?>
                <label for="technicien_id">Assigner un technicien</label>
                <select name="technicien_id" id="technicien_id" required>
                    <?php if ($ticket->technicienId === null): ?>
                        <option value="">-- Choisir --</option>
                    <?php endif; ?>
                    <?php foreach ($techniciens as $tech): ?>
                        <option value="<?= $tech->id ?>" <?= $tech->id === $ticket->technicienId ? 'selected' : '' ?>>
                            <?= e($tech->nomComplet()) ?> (<?= e($tech->poste?->label() ?? '') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">Assigner</button>
            </form>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Historique</h2>
        <?php if ($historique === []): ?>
            <p class="hint">Aucun changement de statut enregistre.</p>
        <?php else: ?>
            <ul class="timeline">
                <?php foreach ($historique as $entry): ?>
                    <li>
                        <strong><?= e($entry['nouveau_statut']) ?></strong>
                        <?php if ($entry['ancien_statut']): ?>
                            <span class="hint">(depuis <?= e($entry['ancien_statut']) ?>)</span>
                        <?php endif; ?>
                        <div class="hint"><?= e($entry['user_nom']) ?> — <?= e($entry['created_at']) ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
