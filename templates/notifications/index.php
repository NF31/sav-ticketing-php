<?php
/** @var list<\App\Entity\Notification> $notifications */
/** @var int $limite */
$nonLues = count(array_filter($notifications, static fn ($n): bool => !$n->estLue()));
?>
<div class="page-header">
    <div>
        <h1>Notifications</h1>
        <p class="page-header__subtitle">
            <?= $nonLues === 0 ? 'Tout est lu' : $nonLues . ' non lue' . ($nonLues > 1 ? 's' : '') ?>
            <?php if (count($notifications) === $limite): ?>
                &middot; <?= $limite ?> plus recentes
            <?php endif; ?>
        </p>
    </div>
    <?php if ($nonLues > 0): ?>
        <form action="/notifications/tout-lire" method="post">
            <?= csrf_champ() ?>
            <button type="submit" class="btn btn--secondary">Tout marquer comme lu</button>
        </form>
    <?php endif; ?>
</div>

<div class="card">
    <?php if ($notifications === []): ?>
        <div class="empty-state">
            <?= icone('cloche') ?>
            <p>Aucune notification pour l'instant.</p>
        </div>
    <?php else: ?>
        <ul class="notifications">
            <?php foreach ($notifications as $notification): ?>
                <li class="notification<?= $notification->estLue() ? '' : ' notification--non-lue' ?>">
                    <a class="notification__lien" href="/tickets/<?= $notification->ticketId ?>">
                        <span class="notification__message">
                            <?php if (!$notification->estLue()): ?>
                                <span class="visually-hidden">Non lue : </span>
                            <?php endif; ?>
                            <?= e($notification->message) ?>
                        </span>
                        <time class="notification__date" datetime="<?= e($notification->createdAt->format(DATE_ATOM)) ?>" title="<?= e($notification->createdAt->format('d/m/Y H:i')) ?>">
                            <?= e(il_y_a($notification->createdAt)) ?>
                        </time>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
