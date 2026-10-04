# Conventions backend (PHP / Laravel)

Règles à suivre pour tout changement PHP (`app/`, `tests/`, `database/`,
`routes/`, `config/`, `bootstrap/`). Le pendant frontend est dans
[frontend-conventions.md](frontend-conventions.md).

## 1. Porte de qualité (CI)

Les mêmes vérifications tournent en local et en CI (`.github/workflows/tests.yaml`,
réutilisé par les workflows de déploiement : un échec bloque le déploiement).

| Commande                | Rôle                                                        |
| ----------------------- | ----------------------------------------------------------- |
| `php artisan test`      | Tests PHPUnit (comportement)                                |
| `composer analyse`      | PHPStan / Larastan, niveau défini dans `phpstan.neon` (types) |
| `composer format:check` | Pint en lecture seule (mise en forme)                       |

Avant d'ouvrir une PR : `composer format` (corrige), puis les trois commandes
ci-dessus, puis la porte frontend (voir `frontend-conventions.md` §5).

## 2. Mise en forme avec Pint

[Laravel Pint](https://laravel.com/docs/pint) formate le PHP. Il ne vérifie ni
les bugs ni les types, seulement la mise en forme.

- `composer format` : **corrige** les fichiers en place (travailler sur un
  arbre git propre pour pouvoir relire le diff).
- `composer format:check` : vérifie sans rien modifier (code de sortie non nul
  s'il y a des écarts). C'est cette commande que lance la CI.
- Sur quelques fichiers : `vendor/bin/pint chemin/Fichier.php`.
- Pour comprendre une correction : `vendor/bin/pint --test -v`.

La configuration est dans `pint.json` : preset `laravel`, avec quelques
règles réglées sur le **style maison**, c'est-à-dire sur ce que la grande
majorité du code existant faisait déjà (mesuré avant adoption, en comptant les
lignes qui auraient changé dans chaque sens) :

| Règle ajustée                               | Style retenu              | Pourquoi                                                                                  |
| ------------------------------------------- | ------------------------- | ----------------------------------------------------------------------------------------- |
| `concat_space`                              | `'a' . $b` (espacé)       | 8 lignes à changer dans ce sens, contre 178 pour le style Laravel                         |
| `not_operator_with_successor_space`         | `!$x` (désactivée)        | 17 lignes à changer, contre 115 ; c'est aussi le style PSR-12                             |
| `function_declaration` (`closure_fn_spacing`) | `fn($x) => …`           | 4 lignes à changer, contre 134                                                            |
| `new_with_parentheses` (`named_class`)      | `new Classe()`            | 20 lignes à changer, contre 39. Les classes anonymes restent `new class extends …` (stub de migration Laravel) |
| `blank_line_after_opening_tag`              | désactivée                | Convention maison : l'en-tête `// chemin/du/fichier.php` suit directement `<?php`         |
| `binary_operator_spaces`                    | désactivée                | Les tableaux alignés (`=>`) sont un choix de lisibilité assumé ; Pint ne sait pas aligner de façon sélective |
| `phpdoc_align`                              | désactivée                | Sur les `@return` à description multi-ligne, la règle décale les lignes de suite à des colonnes absurdes |

Tout le reste suit le preset `laravel` (imports triés et sans import inutile,
saut de ligne final, `{}` pour les corps vides, `use` de traits triés, etc.).

Principe : comme pour ESLint, on **corrige le code** plutôt que d'ajouter des
exceptions. Modifier `pint.json` demande une justification dans la PR (et une
mise à jour du tableau ci-dessus).

Pint ne formate pas les vues Blade ni les `.md`.

## 3. Commits de reformatage

Un reformatage de masse (nouvelle règle Pint, changement de preset) se fait
dans **un commit dédié, sans aucun changement fonctionnel**, séparé du reste.
Ensuite on ajoute son SHA à `.git-blame-ignore-revs` pour que `git blame`
l'ignore :

```bash
# une seule fois par clone
git config blame.ignoreRevsFile .git-blame-ignore-revs

# après avoir (ré)appliqué le commit, ajouter son SHA définitif
{ echo "# style(php): appliquer Pint"; git rev-parse <sha>; } >> .git-blame-ignore-revs
```

Les SHA changent après un `git am` ou un rebase : toujours renseigner le SHA
final, pas celui d'une branche de travail.

## 4. PHPStan

- Le niveau est défini dans `phpstan.neon` et se relève un niveau à la fois
  (voir les commentaires de ce fichier).
- `phpstan-baseline.neon` n'est régénéré (`composer analyse -- --generate-baseline`)
  qu'en relevant volontairement le niveau, jamais pour faire taire de nouvelles
  erreurs : celles-ci se corrigent dans le code.
