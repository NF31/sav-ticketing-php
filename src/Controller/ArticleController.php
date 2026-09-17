<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Request;
use App\Core\View;
use App\Repository\ArticleRepository;

final class ArticleController
{
    private ArticleRepository $articles;

    public function __construct()
    {
        $this->articles = new ArticleRepository();
    }

    public function index(Request $request): void
    {
        View::render('articles/index', ['articles' => $this->articles->findAll()]);
    }
}
