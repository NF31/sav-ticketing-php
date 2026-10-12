# SAV Ticketing (PHP natif)

[![CI](https://github.com/NF31/sav-ticketing-php/actions/workflows/ci.yml/badge.svg)](https://github.com/NF31/sav-ticketing-php/actions/workflows/ci.yml)

Application de gestion de tickets SAV (service apres-vente) : suivi de
tickets rattaches a des commandes clients, assignation a des techniciens,
workflow de statuts avec historique.

## Pourquoi ce projet

Reconstruction **solo**, en PHP 8.5 natif sans framework, d'un projet
academique fait en groupe (`Filrouge2_sav`). Objectifs :

- avoir un projet dont je suis seul auteur (le projet de groupe original
  avait une repartition de commits tres inegale entre les membres) ;
- corriger les manques identifies dans l'original en le relisant : pas de
  requetes preparees filtrees (`SELECT * FROM techniciens` sans `WHERE`,
  filtrage fait en PHP), pas de hash de mot de passe, aucun echappement
  HTML a l'affichage, aucune tracabilite des changements de statut ;
- montrer une architecture MVC ecrite a la main (routeur, front
  controller, couche repository) plutot que celle fournie par un
  framework comme Laravel — comprendre ce qu'un framework fait *en
  dessous*.

## Ce qui a change par rapport a l'original

| Original (`Filrouge2_sav`) | Ici |
|---|---|
| `SELECT * FROM techniciens` puis comparaison en PHP | Requetes preparees avec `WHERE` cible |
| Mots de passe stockes en clair | `password_hash()` / `password_verify()` (bcrypt) |
| Affichage direct des valeurs en base dans le HTML | Echappement systematique via `e()` (`htmlspecialchars`) |
| Un fichier PHP par action, accessible directement | Front controller unique (`public/index.php`) + routeur |
| Tables `administrateur` et `techniciens` dupliquees | Table `users` unique avec `role` (enum natif PHP) |
| Aucun suivi des changements de statut | Table `ticket_historique`, alimentee dans une transaction |
| Statut modifiable vers n'importe quelle valeur | Machine a etats : transitions autorisees definies par statut |
| Tentatives de connexion illimitees | 5 echecs / 15 min par compte et IP, 20 par IP (429 au-dela) ; temps de reponse identique que l'email existe ou non |
| Aucun en-tete de securite, session par defaut | CSP stricte, anti-iframe (clickjacking), `Referrer-Policy`, HSTS en HTTPS ; session en `use_strict_mode`, cookie `Secure` en HTTPS |
| Formulaires technicien et client valides par une seule condition, email en double ou champ trop long = erreur 500 | Validation par champ (format des noms, longueurs alignees sur la base, email unique, mot de passe 8 caracteres a 72 octets avec confirmation ; telephone, code postal et adresse complete pour les clients), message sous chaque champ, saisie conservee |
| Creation de ticket impossible (code genere de 13 caracteres pour une colonne de 10 : erreur 500 a chaque fois), commande ou technicien inexistant = erreur 500, admin assignable | Code `TCK-0042` derive de l'id ; commande, type et description verifies ; assignation limitee aux comptes technicien et interdite sur un ticket termine ; 404 sur un ticket inexistant |
| Aucune protection CSRF | Jeton CSRF par session sur tous les formulaires, verifie par le routeur sur toute requete POST (419 sinon) |
| Aucune recuperation de mot de passe | "Mot de passe oublie" : lien a usage unique, valable 1 h, jeton stocke hashe (SHA-256) ; changer de mot de passe deconnecte les sessions deja ouvertes |

## Stack

- PHP 8.5, sans framework (routeur, conteneur d'injection de dependances,
  acces aux donnees tous ecrits a la main)
- PDO / MySQL (MariaDB en dev), uniquement des requetes preparees
- `enum` natifs pour les statuts/roles/types (pas de "magic strings")
- Proprietes `readonly` pour les entites (DTO immutables)
- Nouveautes PHP 8.5 dans `Request` : extension URI native
  (`Uri\Rfc3986\Uri`, conforme RFC 3986, a la place de `parse_url()`),
  operateur pipe `|>`, `clone()` avec proprietes modifiees, et
  `#[\NoDiscard]` sur `withRouteParams()`
- Envoi d'emails via l'API HTTP de Resend avec l'extension curl, sans
  SDK (meme requete que `resend/resend-php`, sans Guzzle)
- SCSS compile avec Dart Sass (theme clair/sombre, aucune dependance CSS
  ou police externe)
- PHPUnit (tests unitaires sur la machine a etats + tests d'integration
  sur les repositories, contre une vraie base)

## Architecture

MVC ecrit a la main, avec injection de dependances :

- `public/index.php` : point d'entree unique (front controller), declare
  les routes.
- `config/services.php` : **racine de composition**, le seul endroit ou
  les objets sont crees et relies entre eux (PDO, repositories, `Auth`,
  `Mailer`, controleurs), via un petit conteneur (`src/Core/Conteneur.php`)
  qui cree chaque service a la premiere demande, une seule fois par
  requete, et detecte les dependances circulaires.
- `src/Controller/` : couche HTTP uniquement. Ils lisent la requete,
  appellent un service pour toute modification, puis choisissent la
  reponse (redirection, message, 404). Ils recoivent leurs dependances par
  le constructeur (aucun `new` de dependance, aucune lecture de
  `$_POST`/`$_GET` : tout passe par l'objet `Request`).
- `src/Service/` : cas d'usage metier (`TicketService` : creer, changer
  le statut, assigner). Les regles s'y appliquent sans rien savoir du HTTP,
  et une regle violee leve une exception (`RegleMetierViolee`,
  `SaisieInvalide`, `TicketIntrouvable`) que le controleur traduit en
  reponse. Les ecritures concurrentes sont protegees par un verrouillage
  optimiste : un statut change entre-temps par quelqu'un d'autre n'est pas
  ecrase. Les lectures simples restent en acces direct aux repositories ;
  la creation de clients et de techniciens (valider puis inserer) n'a pas
  de service, qui ne ferait que relayer les appels.
- Notifications (`src/Service/Notifieur.php`), declenchees par
  `TicketService` apres chaque modification reussie. Regle commune : on
  ne notifie jamais l'auteur de l'action.

  | Evenement | Qui est prevenu |
  | --- | --- |
  | Nouveau ticket | Les admins, pour l'assigner |
  | Assignation | Le technicien assigne, dans l'application **et par email** |
  | Reassignation | Aussi l'ancien technicien |
  | Changement de statut | Le technicien assigne |

  Badge du nombre de non lues dans la barre laterale, page
  `/notifications` ; ouvrir un ticket marque ses notifications comme
  lues.

  En direct, sans recharger la page (`public/assets/notifications.js`) :
  la page interroge `/notifications/etat` toutes les 15 s, met a jour le
  badge et le titre de l'onglet ("(2) SAV Ticketing") et affiche chaque
  nouvelle notification dans un message cliquable. Interrogation
  periodique plutot que Server-Sent Events ou WebSocket : en PHP
  classique, une connexion gardee ouverte occupe un processus PHP par
  onglet (le serveur de dev n'en a que 4). Aucune requete quand l'onglet
  est en arriere-plan, et la session y est ouverte en lecture seule : un
  onglet oublie ne prolonge pas la session indefiniment. L'email part apres la reponse (`ApresReponse`) et une
  notification en echec est journalisee sans annuler l'action deja
  enregistree.
- `src/Repository/` : acces SQL (requetes preparees), recoivent la
  connexion PDO.
- Liste des tickets paginee (20 par page, `src/Core/Pagination.php`) et
  servie par deux index (`created_at`, `statut + created_at`). La page est
  choisie par une jointure differee : les ids de la page sont lus dans
  l'index, puis seules ces lignes sont jointes aux clients et techniciens
  (sur 20 000 tickets : ~3,5 ms par page au lieu de ~47 ms). Pagination
  par decalage (`OFFSET`), simple et suffisante ici ; sur des millions de
  lignes, une pagination par curseur (`WHERE created_at < ...`) eviterait
  de parcourir les pages precedentes.
- `src/Entity/`, `src/Enum/` (dont la machine a etats des statuts),
  `src/Validation/` : donnees et regles metier, sans acces a la base.
- `templates/` : vues PHP, echappement systematique.

Un test (`tests/Integration/ServicesTest.php`) construit chaque controleur
et chaque service depuis la vraie configuration : un service oublie dans
`config/services.php` fait echouer la CI, pas la production.

## Design (SCSS)

Les styles sont ecrits en SCSS dans `assets/scss/` et compiles vers
`public/assets/style.css` avec [Dart Sass](https://sass-lang.com/dart-sass)
(le compilateur officiel, via npm : Node ne sert qu'a cette etape) :

```bash
npm install
npm run build:css   # compile une fois (CSS minifie, celui qui est versionne)
npm run watch:css   # recompile a chaque modification, pendant le developpement
```

Organisation :

- `abstracts/` : palette, echelles (espacements, rayons, tailles de
  texte, points de rupture), fonctions et mixins. Ne produit aucun CSS.
- `base/` : variables CSS des themes clair et sombre (suit le reglage du
  systeme), reset, typographie.
- `layout/` : coquille de l'application (barre laterale), pages de
  connexion, en-tetes et grilles de page.
- `components/` : boutons, cartes, formulaires, tableaux, badges (un par
  statut de ticket, generes depuis une seule liste), alertes, filtres,
  historique.

`style.css` est versionne (aucune etape de build au deploiement) ; la CI
verifie qu'il correspond au SCSS. Aucune police ni icone externe : la CSP
n'autorise que les ressources du site, les icones sont des SVG inline
(`icone()` dans `src/helpers.php`).

## Lancer le projet en local

```bash
composer install

# Base de donnees (MariaDB via Docker)
docker run -d --name sav-ticketing-mysql \
  -e MARIADB_ROOT_PASSWORD=root -e MARIADB_DATABASE=sav_ticketing \
  -p 3310:3306 mariadb:11

cp .env.example .env

docker exec -i sav-ticketing-mysql mariadb -uroot -proot sav_ticketing < database/schema.sql
docker exec -i sav-ticketing-mysql mariadb -uroot -proot sav_ticketing < database/seed.sql

composer serve   # http://localhost:8090
# equivalent : PHP_CLI_SERVER_WORKERS=4 php -S localhost:8090 -t public
# (4 workers : le serveur integre de PHP ne traite sinon qu'une requete a
# la fois, et l'envoi d'un email bloquerait la page suivante ~0,5 s)
```

Comptes de demo (mot de passe `password` pour tous) :

- `admin@sav.local` — administrateur
- `karim.diallo@sav.local` — technicien SAV
- `lea.nguyen@sav.local` — technicien hotline

### Mot de passe oublie

Lien "Mot de passe oublie ?" sous le formulaire de connexion. Le domaine
du lien envoye vient de `APP_URL` dans `.env`, jamais de l'en-tete
`Host` de la requete (qui est falsifiable).

### Envoi des emails

Choisi par `MAIL_MAILER` dans `.env` (memes noms de variables que
Laravel) :

- `log` (defaut) : rien n'est envoye, l'email est ecrit dans
  `var/mail.log`. Aucun compte necessaire.
- `resend` : envoi reel via l'API HTTP de [Resend](https://resend.com),
  appelee directement avec curl (sans SDK ni dependance). Renseigner
  `RESEND_API_KEY` (cle creee sur https://resend.com/api-keys),
  `MAIL_FROM_ADDRESS` et `MAIL_FROM_NAME`. Avec `onboarding@resend.dev`
  (aucun domaine a configurer), Resend n'envoie qu'a l'adresse du compte
  Resend proprietaire de la cle : suffisant pour tester. Pour ecrire a
  n'importe quel utilisateur, verifier un domaine sur
  https://resend.com/domains (enregistrements DNS) et utiliser une
  adresse de ce domaine.

```dotenv
MAIL_MAILER=resend
MAIL_FROM_ADDRESS=onboarding@resend.dev
MAIL_FROM_NAME="SAV Ticketing"
RESEND_API_KEY=re_...
```

L'email est envoye **apres** la reponse au navigateur
(`ApresReponse`) : un appel a Resend prend ~0,5 s, et sur "mot de passe
oublie" il n'a lieu que si le compte existe ; fait pendant la requete,
il rendrait le temps de reponse revelateur. Un envoi echoue est
journalise (statut et message de Resend, jamais la cle ni le
destinataire) dans le log d'erreurs PHP.

Demandes limitees a 3 par heure et par email (au-dela, meme message
affiche mais aucun email envoye, pour ne pas reveler que le compte
existe) et a 10 par heure et par IP (au-dela, erreur 429).

Base creee avant l'ajout de cette fonctionnalite : creer les tables
manquantes (`password_resets`, `tentatives`) avec

```bash
sed -n '/CREATE TABLE password_resets/,$p' database/schema.sql \
  | docker exec -i sav-ticketing-mysql mariadb -uroot -proot sav_ticketing
```

Base creee avant l'ajout des index de la liste des tickets : les ajouter
avec (rejouable sans erreur)

```bash
docker exec -i sav-ticketing-mysql mariadb -uroot -proot sav_ticketing < database/migrations/001_index_tickets.sql
```

Base creee avant l'ajout des notifications : creer la table avec

```bash
docker exec -i sav-ticketing-mysql mariadb -uroot -proot sav_ticketing < database/migrations/002_notifications.sql
```

## Tests

```bash
composer test
```

Lances aussi par GitHub Actions (`.github/workflows/ci.yml`) a chaque
push sur `main` et sur chaque pull request : PHP 8.5, une MariaDB 11
neuve avec le schema et le seed, puis `composer validate`, verification
de la syntaxe de tous les fichiers PHP et `composer test`. Les emails y
sont en `MAIL_MAILER=log` : aucun envoi reel, aucun secret necessaire.

## Ce qui manque encore (a faire evoluer)

- Formulaire de creation de commande/client depuis la meme page qu'un
  ticket (actuellement il faut deja avoir une commande existante)
- Recherche/filtre par client ou technicien
