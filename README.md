# Portfolio photo — Ina Zaoui

Application web Symfony pour présenter un portfolio photographique ainsi que gérer les albums, les médias et les comptes invités.

## Prérequis

- PHP **8.4 ou supérieur**, avec les extensions `ctype`, `iconv`, `pdo_pgsql` et `gd` ;
- [Composer](https://getcomposer.org/) ;
- Docker et Docker Compose pour lancer PostgreSQL 16 ;
- Symfony CLI (facultatif, pour lancer le serveur de développement) ;
- Xdebug ou PCOV activé pour produire le rapport de couverture des tests. Le script `composer test` active le mode couverture d'Xdebug via `XDEBUG_MODE=coverage`.

## Installation

Depuis la racine du projet :

```bash
composer install
docker compose up -d database
```

Avec la configuration du conteneur fournie, ajoutez cette URL à `.env.local` (ou adaptez-la si nécessaire) :

```dotenv
DATABASE_URL="postgresql://postgres:postgres@127.0.0.1:5432/ina_zaoui?serverVersion=16&charset=utf8"
```

Ces identifiants sont réservés au conteneur local ; ne les réutilisez pas en production.

Créez la base et appliquez les migrations :

```bash
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate --no-interaction
```

Pour remplir une base locale avec les fixtures :

```bash
php bin/console doctrine:fixtures:load
```

**Attention :** cette commande vide les données existantes avant de charger les fixtures. Elle crée notamment l'administratrice `ina@zaoui.com` avec le mot de passe `password` ; ce compte est réservé au développement et ne doit jamais être utilisé en production. Les fixtures référencent des images dans `public/uploads`. Pour afficher tous les médias de démonstration, le jeu d'images est nécessaire ; l'archive `backup.zip` mentionnée dans l'ancienne documentation n'est pas présente dans ce dépôt et doit être obtenue séparément.

Lancez le serveur, si Symfony CLI est installé :

```bash
symfony server:start
```

## Utilisation

- Le site public présente le portfolio, ses albums et les profils des invités actifs.
- L'administration permet de gérer albums, médias et invités (création, activation/désactivation et suppression). Connectez-vous avec le compte fixture indiqué ci-dessus en environnement local.

## Fonctionnement du code

- `src/Entity/` définit les entités Doctrine `User`, `Album` et `Media` ; les évolutions du schéma sont versionnées dans `migrations/`.
- `src/Controller/` relie les routes aux vues Twig de `templates/`. Les contrôleurs d'administration sont réservés au rôle `ROLE_ADMIN`.
- `src/Service/` centralise les opérations métier, notamment la désactivation des invités et la suppression de leurs médias et fichiers.
- Les formulaires limitent les images envoyées à 2 Mo et aux formats JPEG, PNG, GIF et WebP.
- Les invités désactivés ne sont pas affichés au public et ne peuvent pas se connecter. 
- Les images sont stockées sous `public/uploads`. LiipImagineBundle génère des versions WebP compressées en cache.

## Tests et qualité

Préparez aussi la base de test (Doctrine ajoute le suffixe `_test`) :

```bash
php bin/console doctrine:database:create --env=test --if-not-exists
php bin/console doctrine:migrations:migrate --env=test --no-interaction
composer test
```

`composer test` exécute PHPUnit et génère le rapport HTML de couverture dans `coverage/`.
La couverture de code est fournie dans `coverage/`. Note : `DataFixtures` est actuellement exclu de la couverture pour des raisons de priorité, mais même en l'incluant, nous sommes au dessus des 70%.
Pour l'analyse statique, lancez `composer pstan` (PHPStan level 6)
Pour la norme de code, lancez `composer pcsfixer` (PHP CS Fixer - standard symfony)

Voir [CONTRIBUTING.md](CONTRIBUTING.md) pour contribuer au projet.
