<?php
/** @var list<\App\Entity\Ticket> $tickets */
/** @var list<\App\Enum\TicketStatut> $statuts */
/** @var ?\App\Enum\TicketStatut $statutActif */
/** @var \App\Core\Pagination $pagination */
$nombre = $pagination->total;

// Lien vers une page en gardant le filtre de statut ; http_build_query
// ignore les valeurs null (pas de ?statut= sans filtre, pas de page=1).
$lienPage = static function (int $page) use ($statutActif): string {
    $query = http_build_query(['statut' => $statutActif?->value, 'page' => $page > 1 ? $page : null]);

    return '/tickets' . ($query !== '' ? '?' . $query : '');
};
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

<?php if ($pagination->nombrePages > 1): ?>
    <nav class="pagination" aria-label="Pagination">
        <?php if ($pagination->aPrecedente()): ?>
            <a class="btn btn--secondary" href="<?= e($lienPage($pagination->page - 1)) ?>" rel="prev"><?= icone('retour') ?> Precedent</a>
        <?php else: ?>
            <span class="btn btn--secondary pagination__inactif" aria-disabled="true"><?= icone('retour') ?> Precedent</span>
        <?php endif; ?>

        <p class="pagination__info">
            Page <?= $pagination->page ?> sur <?= $pagination->nombrePages ?>
            <span class="text-muted">&middot; tickets <?= $pagination->premier() ?>&ndash;<?= $pagination->dernier() ?></span>
        </p>

        <?php if ($pagination->aSuivante()): ?>
            <a class="btn btn--secondary" href="<?= e($lienPage($pagination->page + 1)) ?>" rel="next">Suivant <?= icone('suivant') ?></a>
        <?php else: ?>
            <span class="btn btn--secondary pagination__inactif" aria-disabled="true">Suivant <?= icone('suivant') ?></span>
        <?php endif; ?>
    </nav>
<?php endif; ?>
