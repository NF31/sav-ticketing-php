<?php
/** @var list<\App\Entity\Ticket> $tickets */
/** @var list<\App\Enum\TicketStatut> $statuts */
/** @var ?\App\Enum\TicketStatut $statutActif */
?>
<div class="page-header">
    <h1>Tickets SAV</h1>
    <a class="btn" href="/tickets/create">+ Nouveau ticket</a>
</div>

<div class="filters">
    <a class="chip <?= $statutActif === null ? 'chip-active' : '' ?>" href="/tickets">Tous</a>
    <?php foreach ($statuts as $s): ?>
        <a class="chip <?= $statutActif === $s ? 'chip-active' : '' ?>" href="/tickets?statut=<?= e($s->value) ?>"><?= e($s->label()) ?></a>
    <?php endforeach; ?>
</div>

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
                <td><a href="/tickets/<?= $ticket->id ?>"><?= e($ticket->code) ?></a></td>
                <td><?= e($ticket->clientNomComplet) ?></td>
                <td><?= e($ticket->type->label()) ?></td>
                <td><span class="badge <?= e($ticket->statut->badgeClass()) ?>"><?= e($ticket->statut->label()) ?></span></td>
                <td><?= e($ticket->technicienNomComplet ?? 'Non assigne') ?></td>
                <td><?= e($ticket->createdAt->format('d/m/Y H:i')) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($tickets === []): ?>
            <tr><td colspan="6" class="empty">Aucun ticket.</td></tr>
        <?php endif; ?>
    </tbody>
</table>
