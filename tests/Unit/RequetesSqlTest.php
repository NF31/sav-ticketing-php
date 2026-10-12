<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Garde-fou contre l'injection SQL, sans ORM : le texte SQL passe a
 * prepare(), query() et exec() ne peut contenir que du texte ecrit dans le
 * code (chaines litterales, constantes de classe, concatenation). Une
 * variable ou un appel de fonction (sprintf, implode...) dans ce texte fait
 * echouer le test : les valeurs doivent passer par des parametres (:id).
 *
 * Analyse des jetons PHP (token_get_all), pas une simple recherche de
 * texte : commentaires et espaces sont ignores, les chaines ne sont pas
 * confondues avec du code.
 */
final class RequetesSqlTest extends TestCase
{
    /**
     * Variables autorisees, verifiees a la main : elles ne contiennent que du
     * texte fixe du code.
     *
     * @var array<string, list<string>> fichier (relatif a src/) => variables
     */
    private const array EXCEPTIONS = [
        // Clause WHERE choisie parmi deux textes fixes par filtreStatut().
        'Repository/TicketRepository.php' => ['$where'],
    ];

    public function testLeSqlNEstConstruitQuAvecDuTexteFixe(): void
    {
        $appels = 0;
        $problemes = [];

        foreach ($this->fichiersSource() as $fichier => $code) {
            foreach ($this->argumentsDesAppelsSql($code) as [$ligne, $jetons]) {
                $appels++;
                foreach ($jetons as $jeton) {
                    if (!$this->estAutorise($jeton, self::EXCEPTIONS[$fichier] ?? [])) {
                        $texte = is_array($jeton) ? $jeton[1] : $jeton;
                        $problemes[] = "src/{$fichier}:{$ligne} : \"{$texte}\" dans le texte SQL";
                    }
                }
            }
        }

        // Garde-fou du garde-fou : si l'analyse ne trouvait plus aucun appel
        // (methodes renommees...), le test passerait sans rien verifier.
        self::assertGreaterThan(40, $appels, 'Appels SQL introuvables : analyse a revoir.');
        self::assertSame([], $problemes, "Valeur a passer en parametre (:nom), pas dans le SQL :\n" . implode("\n", $problemes));
    }

    public function testDetecteUneVariableDansLeSql(): void
    {
        $code = <<<'PHP'
            <?php
            $this->pdo->prepare('SELECT 1');
            $this->pdo->query("SELECT * FROM users WHERE email = '{$email}'");
            $this->pdo->exec('DELETE FROM tickets WHERE id = ' . $id);
            $this->pdo->prepare(sprintf('SELECT %s', $colonne));
            PHP;

        $resultats = array_map(
            fn (array $appel): bool => array_all($appel[1], fn ($jeton): bool => $this->estAutorise($jeton, [])),
            $this->argumentsDesAppelsSql($code),
        );

        self::assertSame([true, false, false, false], $resultats);
    }

    /** @return iterable<string, string> chemin relatif a src/ => code */
    private function fichiersSource(): iterable
    {
        $src = dirname(__DIR__, 2) . '/src/';
        $iterateur = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterateur as $fichier) {
            if ($fichier->getExtension() === 'php') {
                yield substr($fichier->getPathname(), strlen($src)) => (string) file_get_contents($fichier->getPathname());
            }
        }
    }

    /**
     * Jetons de l'argument de chaque appel ->prepare(), ->query(), ->exec().
     *
     * @return list<array{int, list<array{int, string, int}|string>}> [ligne, jetons]
     */
    private function argumentsDesAppelsSql(string $code): array
    {
        $jetons = array_values(array_filter(
            token_get_all($code),
            static fn ($j): bool => !is_array($j) || !in_array($j[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true),
        ));
        $appels = [];

        foreach ($jetons as $i => $jeton) {
            $estAppelSql = is_array($jeton) && $jeton[0] === T_STRING
                && in_array($jeton[1], ['prepare', 'query', 'exec'], true)
                && ($jetons[$i - 1][0] ?? null) === T_OBJECT_OPERATOR
                && ($jetons[$i + 1] ?? null) === '(';
            if (!$estAppelSql) {
                continue;
            }

            // Jusqu'a la parenthese fermante correspondante ; une parenthese
            // ouvrante a l'interieur (appel de fonction) est gardee comme
            // jeton, donc refusee par estAutorise().
            $argument = [];
            $profondeur = 1;
            for ($k = $i + 2; $profondeur > 0 && isset($jetons[$k]); $k++) {
                $profondeur += match ($jetons[$k]) { '(' => 1, ')' => -1, default => 0 };
                if ($profondeur > 0) {
                    $argument[] = $jetons[$k];
                }
            }
            $appels[] = [$jeton[2], $argument];
        }

        return $appels;
    }

    /**
     * @param array{int, string, int}|string $jeton
     * @param list<string> $variablesAutorisees
     */
    private function estAutorise(array|string $jeton, array $variablesAutorisees): bool
    {
        if ($jeton === '.') {
            return true;
        }
        if (!is_array($jeton)) {
            return false;
        }

        return match ($jeton[0]) {
            // 'texte', heredoc/nowdoc sans variable, self::CONSTANTE
            T_CONSTANT_ENCAPSED_STRING, T_START_HEREDOC, T_END_HEREDOC, T_ENCAPSED_AND_WHITESPACE,
            T_STRING, T_DOUBLE_COLON => true,
            T_VARIABLE => in_array($jeton[1], $variablesAutorisees, true),
            default => false,
        };
    }
}
