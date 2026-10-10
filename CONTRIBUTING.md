# Contribuer au projet

Merci de vouloir contribuer à ce projet ! Les échanges et la documentation du projet sont en français. Le code est en anglais.

## Signaler un problème

Ouvrez un ticket dans le dépôt GitHub et indiquez :

- les étapes pour reproduire le problème ;
- le résultat attendu et le résultat obtenu ;
- votre environnement (système, version de PHP) ;
- les messages d'erreur utiles, en masquant mots de passe, jetons et autres données personnelles et/ou sensibles.

Avant de créer un ticket, vérifiez qu'un ticket similaire n'existe pas déjà.

## Proposer une fonctionnalité

Ouvrez un ticket de proposition avant d'entreprendre un changement important. Décrivez le besoin utilisateur, le comportement proposé et son périmètre. Attendez un retour de la part d'un mainteneur pour éviter les travaux en double ou une solution incompatible avec les choix existants.

## Contribuer au code, aux tests et à la documentation

1. Récupérez la dernière version de `main` et installez le projet en suivant le [README](README.md). Si vous n'avez pas accès en écriture au dépôt, créez un fork.
2. Créez une branche dédiée à votre contribution (voir les conventions ci-dessous).
3. Préparez les bases de développement et de test en suivant les instructions du [README](README.md).
4. Gardez les changements ciblés et suivez l'architecture en place : entités et dépôts Doctrine, contrôleurs Symfony, logique métier dans `src/Service/`, vues Twig et migrations Doctrine.
5. Ajoutez ou adaptez les tests pour couvrir le comportement modifié : tests unitaires sous `tests/Unit/`, tests fonctionnels sous `tests/Functional/`.
6. Mettez à jour le README ou la documentation concernée si le comportement, l'installation ou les choix d'implémentation changent. Une contribution uniquement documentaire ne nécessite pas les vérifications PHP, mais ses liens et instructions doivent être vérifiés.
7. N'oubliez pas l'analyse :

   ```bash
   composer test
   composer pstan
   ./vendor/bin/php-cs-fixer fix --dry-run --diff
   ```

   PHPStan est configuré au niveau 6 et PHP CS Fixer utilise le standard Symfony. Pour appliquer les corrections de style, utilisez `composer pcsfixer`.

   `composer test` produit un rapport HTML de couverture dans `coverage/` (Xdebug ou PCOV requis). Maintenez une couverture d'au moins 70 %.
8. Committez et poussez votre branche, puis ouvrez une pull request vers `main`. Référencez le ticket éventuel, résumez le problème résolu, les changements visibles, les tests effectués et, pour une modification d'interface, ajoutez une capture d'écran. Signalez clairement les migrations ou changements de configuration requis.

N'incluez jamais de secrets, de fichiers de configuration locaux, de données personnelles ni de gros jeux de données dans un commit.

## Conventions de nommage

Les conventions suivantes prolongent celles utilisées dans l'historique du dépôt :

- **Branches** : `<type>/<description-courte>`, en minuscules avec des tirets, par exemple `feature/deprecations-fixes`, `bugfix/guest-upload` ou `documentation/contributing`. Utilisez notamment `feature`, `bugfix`, `refactor`, `tests`, `documentation` ou `performance`.
- **Commits** : `<type> - <description>`, avec un message court et précis, de préférence en anglais comme dans l'historique. Exemples : `bugfix - guests can now upload their medias`, `tests - add album controller tests`, `documentation - update README.md`. Pour les dépendances ou la configuration, utilisez `install`, `upgrade` ou `config`.
- **Code** : conservez les noms en anglais et les conventions existantes : classes en `PascalCase`, méthodes et propriétés en `camelCase`, classes de tests avec le suffixe `Test`.

Chaque commit doit correspondre à un changement cohérent ; évitez de mélanger une correction avec un reformatage sans rapport.

## Validation d'une contribution

Une contribution est intégrée après relecture et approbation par le responsable du dépôt si :

- le changement répond au besoin décrit et reste dans le périmètre de la pull request ;
- les tests passent, PHPStan ne signale pas de nouvelle erreur et le style Symfony est respecté ;
- les comportements existants et les droits d'accès sont préservés, les tests et la documentation sont adaptés ;
- les éventuelles migrations et modifications de configuration sont expliquées.

Répondez aux remarques de revue et poussez les corrections sur la même branche. Pensez à relancer tests + analyse statique après chaque correction.
