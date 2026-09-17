<?php /** @var list<\App\Entity\Article> $articles */ ?>
<div class="page-header">
    <h1>Articles</h1>
</div>
<table class="table">
    <thead>
        <tr><th>Reference</th><th>Designation</th><th>Prix</th><th>Stock</th></tr>
    </thead>
    <tbody>
        <?php foreach ($articles as $article): ?>
            <tr>
                <td><?= e($article->reference) ?></td>
                <td><?= e($article->designation) ?></td>
                <td><?= number_format($article->prix, 2, ',', ' ') ?> €</td>
                <td>
                    <span class="<?= $article->stockQuantite <= 3 ? 'stock-low' : '' ?>"><?= $article->stockQuantite ?></span>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
