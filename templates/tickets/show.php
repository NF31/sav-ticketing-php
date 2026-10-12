<?php
/** @var \App\Entity\Ticket $ticket */
/** @var list<array{ancien_statut: ?string, nouveau_statut: string, user_nom: string, created_at: string}> $historique */
/** @var list<\App\Entity\User> $techniciens */
/** @var list<\App\Enum\TicketStatut> $transitions */
/** @var ?string $error */
/** @var ?string $success */

use App\Enum\TicketStatut;

// L'historique stocke les valeurs brutes ("en_cours") : affichees avec leur libelle.
$libelleStatut = static fn (?string $valeur): string => $valeur !== null ? (TicketStatut::tryFrom($valeur)?->label() ?? $valeur) : '';
?>
<a href="/tickets" class="back-link"><?= icone('retour') ?> Tous les tickets</a>

<div class="page-header">
    <div>
        <div class="page-header__title">
            <h1 class="mono">Ticket <?= e($ticket->code) ?></h1>
            <span class="badge <?= e($ticket->statut->badgeClass()) ?>"><?= e($ticket->statut->label()) ?></span>
        </div>
        <p class="page-header__subtitle"><?= e($ticket->type->label()) ?> &middot; <?= e($ticket->clientNomComplet) ?></p>
    </div>
</div>

<?php if ($error): ?>
    <p class="alert alert-error"><?= e($error) ?></p>
<?php endif; ?>
<?php if ($success): ?>
    <p class="alert alert-success"><?= e($success) ?></p>
<?php endif; ?>

<div class="grid grid--aside">
    <div class="stack">
        <section class="card">
            <div class="card__header"><h2>Details</h2></div>
            <div class="card__body stack">
                <dl class="details">
                    <dt>Client</dt><dd><?= e($ticket->clientNomComplet) ?></dd>
                    <dt>Type</dt><dd><?= e($ticket->type->label()) ?></dd>
                    <dt>Technicien</dt>
                    <dd>
                        <?php if ($ticket->technicienNomComplet !== null): ?>
                            <span class="person">
                                <span class="avatar avatar--sm"><?= e(initiales($ticket->technicienNomComplet)) ?></span>
                                <?= e($ticket->technicienNomComplet) ?>
                            </span>
                        <?php else: ?>
                            <span class="text-muted">Non assigne</span>
                        <?php endif; ?>
                    </dd>
                    <dt>Cree le</dt><dd><?= e($ticket->createdAt->format('d/m/Y H:i')) ?></dd>
                    <dt>Mis a jour le</dt><dd><?= e($ticket->updatedAt->format('d/m/Y H:i')) ?></dd>
                </dl>
                <div>
                    <p class="form__legend">Description</p>
                    <?php if ($ticket->description !== null): ?>
                        <p class="description"><?= e($ticket->description) ?></p>
                    <?php else: ?>
                        <p class="text-muted">Aucune description.</p>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card__header"><h2>Actions</h2></div>
            <div class="card__section card__body">
                <?php if ($transitions !== []): ?>
                    <form action="/tickets/<?= $ticket->id ?>/statut" method="post" class="inline-form">
                        <?= csrf_champ() ?>
                        <div class="field">
                            <label for="statut" class="field__label">Changer le statut</label>
                            <select name="statut" id="statut">
                                <?php foreach ($transitions as $t): ?>
                                    <option value="<?= e($t->value) ?>"><?= e($t->label()) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn">Valider</button>
                    </form>
                <?php else: ?>
                    <p class="hint">Aucune transition possible depuis ce statut.</p>
                <?php endif; ?>
            </div>
            <div class="card__section card__body">
                <?php if ($ticket->statut === TicketStatut::Termine): ?>
                    <p class="hint">Ticket termine : il ne peut plus etre reassigne.</p>
                <?php else: ?>
                    <form action="/tickets/<?= $ticket->id ?>/assigner" method="post" class="inline-form">
                        <?= csrf_champ() ?>
                        <div class="field">
                            <label for="technicien_id" class="field__label">Assigner un technicien</label>
                            <select name="technicien_id" id="technicien_id" required>
                                <?php if ($ticket->technicienId === null): ?>
                                    <option value="">-- Choisir --</option>
                                <?php endif; ?>
                                <?php foreach ($techniciens as $tech): ?>
                                    <option value="<?= $tech->id ?>"<?= $tech->id === $ticket->technicienId ? ' selected' : '' ?>>
                                        <?= e($tech->nomComplet()) ?> (<?= e($tech->poste?->label() ?? '') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn--secondary">Assigner</button>
                    </form>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <section class="card">
        <div class="card__header"><h2>Historique</h2></div>
        <div class="card__body">
            <?php if ($historique === []): ?>
                <p class="hint">Aucun changement de statut enregistre.</p>
            <?php else: ?>
                <ol class="timeline">
                    <?php foreach ($historique as $entree): ?>
                        <li class="timeline__item">
                            <span class="timeline__dot"></span>
                            <strong><?= e($libelleStatut($entree['nouveau_statut'])) ?></strong>
                            <?php if ($entree['ancien_statut']): ?>
                                <span class="text-muted">depuis <?= e($libelleStatut($entree['ancien_statut'])) ?></span>
                            <?php endif; ?>
                            <p class="timeline__meta"><?= e($entree['user_nom']) ?> &middot; <?= e((new DateTimeImmutable($entree['created_at']))->format('d/m/Y H:i')) ?></p>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </div>
    </section>
</div>
