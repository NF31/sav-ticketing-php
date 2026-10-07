# SAV Ticketing (PHP natif)

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
| Aucune recuperation de mot de passe | "Mot de passe oublie" : lien a usage unique, valable 1 h, jeton stocke hashe (SHA-256) |

## Stack

- PHP 8.5, sans framework (routeur, DI, ORM tous ecrits a la main)
- PDO / MySQL (MariaDB en dev), uniquement des requetes preparees
- `enum` natifs pour les statuts/roles/types (pas de "magic strings")
- Proprietes `readonly` pour les entites (DTO immutables)
- Nouveautes PHP 8.5 dans `Request` : extension URI native
  (`Uri\Rfc3986\Uri`, conforme RFC 3986, a la place de `parse_url()`),
  operateur pipe `|>`, `clone()` avec proprietes modifiees, et
  `#[\NoDiscard]` sur `withRouteParams()`
- PHPUnit (tests unitaires sur la machine a etats + tests d'integration
  sur les repositories, contre une vraie base)

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
```

Comptes de demo (mot de passe `password` pour tous) :

- `admin@sav.local` — administrateur
- `karim.diallo@sav.local` — technicien SAV
- `lea.nguyen@sav.local` — technicien hotline

### Mot de passe oublie

Lien "Mot de passe oublie ?" sous le formulaire de connexion. Aucun
serveur SMTP n'est configure : l'email (avec le lien de
reinitialisation) est ecrit dans `var/mail.log`. Le domaine du lien
vient de `APP_URL` dans `.env`, jamais de l'en-tete `Host` de la requete
(qui est falsifiable).

Base creee avant l'ajout de cette fonctionnalite : creer la table
manquante avec

```bash
sed -n '/CREATE TABLE password_resets/,$p' database/schema.sql \
  | docker exec -i sav-ticketing-mysql mariadb -uroot -proot sav_ticketing
```

## Tests

```bash
composer test
```

## Ce qui manque encore (a faire evoluer)

- Formulaire de creation de commande/client depuis la meme page qu'un
  ticket (actuellement il faut deja avoir une commande existante)
- Pagination sur la liste des tickets
- Recherche/filtre par client ou technicien
