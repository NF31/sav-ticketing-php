<?php
/** @var list<\App\Entity\Ticket> $tickets */
/** @var list<\App\Enum\TicketStatut> $statuts */
/** @var ?\App\Enum\TicketStatut $statutActif */
$nombre = count($tickets);
?>
<div class="page-header">
    <div>
        <h1>Tickets SAV</h1>
        <p class="page-header__subtitle">
            <?= $nombre ?> ticket<?= $nombre > 1 ? 's' : '' ?><?= $statutActif !== null ? ' &middot; ' . e($statutActif->label()) : '' ?>
        </p>
    </div>
    <a class="btn" href="/tickets/create"><?= icone('plus') ?> Nouveau ticket</a>
</div>

<nav class="filters" aria-label="Filtrer par statut">
    <a class="chip <?= $statutActif === null ? 'chip-active' : '' ?>" href="/tickets"<?= $statutActif === null ? ' aria-current="page"' : '' ?>>Tous</a>
    <?php foreach ($statuts as $s): ?>
        <a class="chip <?= $statutActif === $s ? 'chip-active' : '' ?>" href="/tickets?statut=<?= e($s->value) ?>"<?= $statutActif === $s ? ' aria-current="page"' : '' ?>><?= e($s->label()) ?></a>
    <?php endforeach; ?>
</nav>

<div class="card">
    <?php if ($tickets === []): ?>
        <div class="empty-state">
            <?= icone('vide') ?>
            <p>Aucun ticket<?= $statutActif !== null ? ' avec ce statut' : '' ?>.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Client</th>
                        <th>Type</th>
                        <th>Statut</th>
                        <th>Technicien</th>
                        <th>Cree le</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $ticket): ?>
                        <tr>
                            <td><a class="table__primary-link" href="/tickets/<?= $ticket->id ?>"><?= e($ticket->code) ?></a></td>
                            <td><?= e($ticket->clientNomComplet) ?></td>
                            <td class="text-muted"><?= e($ticket->type->label()) ?></td>
                            <td><span class="badge <?= e($ticket->statut->badgeClass()) ?>"><?= e($ticket->statut->label()) ?></span></td>
                            <td>
                                <?php if ($ticket->technicienNomComplet !== null): ?>
                                    <span class="person">
                                        <span class="avatar avatar--sm"><?= e(initiales($ticket->technicienNomComplet)) ?></span>
                                        <?= e($ticket->technicienNomComplet) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">Non assigne</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted"><?= e($ticket->createdAt->format('d/m/Y H:i')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
