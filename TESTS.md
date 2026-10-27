# Contrôles réalisés lors de la livraison

Date : 27 octobre 2026. Environnement de génération : Linux, PHP CLI 8.4.24, Node.js disponible, **sans serveur MariaDB/XAMPP et sans pilote `pdo_mysql`**.

| Contrôle | Résultat |
| --- | --- |
| Lint de tous les fichiers PHP (`php -l`) | **PASS** — 30 fichiers, aucune erreur de syntaxe |
| Validation DateTime (date valide/invalide), horaires, participants | **PASS** — via `tests/test_validation.php` |
| Échappement XSS `h()` | **PASS** |
| Validation des données de réservation hors base | **PASS** |
| Syntaxe JavaScript (`node --check`) | **PASS** |
| Migration des données historiques préparée | **PASS** — 7 comptes d’origine + 1 compte désactivé pour ancienne référence manquante, 3 salles, 9 réservations, 4 déclarations |
| Mot de passe historique absent en clair du nouveau dossier | **PASS** — hashes bcrypt uniquement dans SQL import |
| Jeton Telegram historique absent de la livraison | **PASS** — contrôle de présence de l'ancien jeton |
| `config/local.php` absent de l'archive (configurable sur PC) | **PASS** |
| Disponibilité des images des 3 salles historiques | **PASS** — images présentes dans `uploads/` |
| Import SQL effectif dans MariaDB / phpMyAdmin | **NON TESTÉ** — service MySQL absent |
| Connexion PDO XAMPP Windows | **NON TESTÉ** — pas de `pdo_mysql` dans le runtime de génération |
| CRUD DB / permissions HTTP / concurrence réelle | **NON TESTÉ** — test sur installation XAMPP nécessaire |
| Interface visuelle navigateur Windows / CDN FullCalendar/Chart.js | **NON TESTÉ** — vérifier sous Chrome/Firefox et Console F12 |

**Note sur les données source :** `users.json` contient deux comptes avec l'ID `11`, et l'ID `5` est référencé sans exister. Le script importe le second compte `11` sous `12` et crée un utilisateur `5` désactivé pour respecter les clés étrangères, préserver les 9 réservations et les 4 déclarations.

Les protections de chevauchement reposent sur une transaction InnoDB, le verrouillage `SELECT ... FOR UPDATE` de la ligne de salle, puis la recherche d'intersections. Un **test de deux réservations concurrentes avec MariaDB réel** reste indispensable pour confirmer le comportement dans votre version XAMPP.

## Recette manuelle proposée sous XAMPP

1. Importer `database/install.sql` puis `database/legacy_import.sql` depuis phpMyAdmin. Vérifier les quatre tables des données historiques et les relations FK.
2. Se connecter avec un ancien compte admin (identifiant numérique historique) puis vérifier l'affichage du dashboard.
3. Aller dans Gestion des salles et compléter la capacité historique avant de créer une réservation future.
4. Créer un créneau; tenter un créneau qui se superpose sur la même salle : refus attendu; un créneau juste après doit passer.
5. Tester 2 sessions de navigateur créant le même créneau quasiment simultanément; une seule doit passer.
6. Activer « Approbation obligatoire », créer une demande comme utilisateur et la valider comme admin.
7. Tester un compte utilisateur sur `gestion.php` : réponse HTTP 403 attendue; vérifier qu'il ne peut éditer une réservation qui n'est pas la sienne.
8. Déclarer du matériel manquant, marquer les notifications comme lues, changer le mot de passe, tester les images.
9. Dans DevTools F12, contrôler les requêtes CSS/JS/API (200 ou erreurs explicitement rapportées).
