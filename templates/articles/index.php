<?php /** @var list<\App\Entity\Article> $articles */ ?>
<div class="page-header">
    <div>
        <h1>Articles</h1>
        <p class="page-header__subtitle">Catalogue et niveau de stock (alerte a 3 unites ou moins).</p>
    </div>
</div>

<div class="card">
    <?php if ($articles === []): ?>
        <div class="empty-state">
            <?= icone('vide') ?>
            <p>Aucun article.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Reference</th><th>Designation</th><th class="num">Prix</th><th class="num">Stock</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($articles as $article): ?>
                        <tr>
                            <td class="mono text-muted"><?= e($article->reference) ?></td>
                            <td><?= e($article->designation) ?></td>
                            <td class="num"><?= number_format($article->prix, 2, ',', ' ') ?> €</td>
                            <td class="num">
                                <?php if ($article->stockQuantite <= 3): ?>
                                    <span class="badge badge--danger"><?= $article->stockQuantite ?> &middot; stock faible</span>
                                <?php else: ?>
                                    <?= $article->stockQuantite ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
