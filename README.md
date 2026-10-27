# EMS Workspace — Gestion et réservation des salles de réunion

Refonte du projet **projet ems**, en conservant ses salles, images, utilisateurs, réservations et déclarations de matériel. Stack : **PHP natif 8+, PDO, MySQL/MariaDB, HTML, CSS, JavaScript, Bootstrap 5**, sans Laravel, Node ni Docker. Bootstrap 5 CSS/JS est fourni localement; Bootstrap Icons, Chart.js et FullCalendar sont optionnels et actuellement chargés depuis des CDN (connexion Internet nécessaire pour ces éléments).

## Installation XAMPP (Windows)

1. Décompressez le dossier **`meeting-room-reservation`** vers **`C:\xampp\htdocs\meeting-room-reservation`**. Évitez un niveau de dossier supplémentaire.
2. Ouvrez **XAMPP Control Panel** et cliquez **Start** pour **Apache** et **MySQL** (voyants verts).
3. Allez sur `http://localhost/phpmyadmin` et ouvrez l'onglet **Importer** depuis la page d'accueil de phpMyAdmin. Sélectionnez **`database/install.sql`**. Ce fichier crée automatiquement `meeting_room_reservation` et les tables.
4. Cliquez sur la base `meeting_room_reservation` dans phpMyAdmin, puis importez **`database/legacy_import.sql`** pour récupérer les **7 comptes historiques**, **3 salles**, **9 réservations** et **4 signalements** issus de l'archive, plus un compte fantôme désactivé pour garder les anciennes références. Les imports utilisent `INSERT IGNORE` : ils ne remplacent pas les données d'une base déjà remplie.
5. Copiez `config/local.example.php` vers **`config/local.php`**. Éditez-le dans VS Code et configurez **`host`**, **`port`**, **`database`**, **`username`**, **`password`**, selon *votre* MySQL. Le mot de passe `root` n'est pas imposé comme vide : le fichier d'exemple n'est qu'un point de départ.
6. Dans VS Code, faites **File → Open Folder**, choisissez `C:\xampp\htdocs\meeting-room-reservation`.
7. Dans votre navigateur, ouvrez **`http://localhost/meeting-room-reservation/`**. Connectez-vous avec **l'identifiant numérique et le mot de passe de votre ancien projet**. Les mots de passe ont été convertis en `password_hash()` dans le fichier d'import; ils ne figurent pas en clair dans le projet livré.
8. Pour votre sécurité, chaque utilisateur devrait modifier son mot de passe depuis **Profil**. Les anciens postes `ADMIN`, `RH` et `CP` ont les droits administrateur d'origine; les autres sont des utilisateurs standards. **Anomalies des anciennes données :** deux comptes avaient l'ID `11`; le second a été importé sous l'ID **`12`** pour éviter sa disparition. Le compte **`5`** n'existe pas dans `users.json` mais est référencé dans des réservations et déclarations : un compte **désactivé** a été ajouté uniquement pour conserver l'historique. Ne l'activez pas sans vérification de son identité.
9. Rendez-vous dans **Gestion des salles** et renseignez la **capacité de chaque salle**. Les données d'origine ne contenaient pas les capacités : aucune capacité n'a été inventée. Tant qu'elle n'est pas renseignée, une salle ne peut pas être réservée.

Si vous créez une installation **vide**, importez `install.sql` seul puis utilisez `scripts/create_admin.php` depuis un terminal local XAMPP pour créer le premier compte administrateur :

```bat
cd C:\xampp\htdocs\meeting-room-reservation
C:\xampp\php\php.exe scripts\create_admin.php
```

Ce script demande un nom et un mot de passe (≥10 caractères) via terminal local, sans identifiant codé en dur.

### Vérifications fonctionnelles

- **Administrateur** : connexion, Dashboard, Gestion des salles (ajout/édition/photo/archivage), Utilisateurs, Toutes les réservations (approbation/refus), Paramètres, Historique, rapports de matériel et Notifications.
- **Utilisateur** : connexion, Salles, Détails, Nouvelle réservation, Vérifier les disponibilités, Mes réservations (modifier/annuler), Calendrier, Matériel, Profil.
- **Conflits** : deux réservations de la même salle à la même date et à des heures qui se croisent doivent être refusées. Un créneau adjacent est autorisé. Une salle en maintenance, archivée, sans capacité définie ou trop petite doit être rejetée côté serveur.
- **Rôles** : tentez d'ouvrir `gestion.php` sans compte administrateur : HTTP 403. Toutes les écritures sont exclusivement en POST avec CSRF.

### Architecture

- `app/core.php` : PDO, session, autorisation, validation et logique métier partagée.
- `app/layout.php` : navigation, design Bootstrap 5 et composants réutilisables.
- `actions.php` : mutations sécurisées centralisées (réservations, salles, comptes, matériel, notifications).
- `api_disponibilite.php`, `api_evenements.php` : API Fetch/FullCalendar en lecture.
- `assets/` : CSS/JS/icônes, `uploads/` et `img/` : images préservées.
- `database/install.sql` : schéma neuf ; `database/legacy_import.sql` : migration des données JSON historiques, avec mots de passe **hachés**.
- `config/local.php` : identifiants locaux, non fournis dans l'archive. `config/local.example.php` : exemple.

Les fichiers historiques `pageacceuil.php`, `med.php`, `modifier_reservation.php`, `annuler_reservation.php` et `checkReservations.php` redirigent vers les nouveaux écrans afin d'éviter les anciennes opérations JSON dangereuses. Le token Telegram trouvé dans le projet original a été **retiré**; s'il est encore utilisé ailleurs, **révoquez-le et remplacez-le** auprès de Telegram.

### Débogage

| Problème | Solution |
| --- | --- |
| Apache ne démarre pas | Vérifier port 80/443 (IIS/Skype), ouvrir *Logs* Apache dans XAMPP, changer le port si nécessaire. |
| MySQL ne démarre pas | Vérifier port 3306, autres services MySQL, logs MySQL XAMPP. Ne supprimez pas le dossier `mysql/data`. |
| "Connexion MySQL impossible" | Démarrer MySQL; contrôler `config/local.php`, nom de BD, utilisateur, mot de passe; vérifier `pdo_mysql` activé. |
| Table inexistante | Importer `database/install.sql`, puis `legacy_import.sql` dans phpMyAdmin. |
| Login refusé | Utiliser l'ID numérique historique, mot de passe historique; confirmer l'import des `users` dans phpMyAdmin. |
| Page 404 ou ressources CSS 404 | Vérifier le nom exact du dossier dans `htdocs`, ne pas ouvrir les `.php` en `file://`, utiliser l'URL `localhost`. |
| Erreur PHP 500 | XAMPP → Apache → **Logs → error.log**; activer temporairement `display_errors` seulement en local, vérifier extensions `pdo_mysql`, `fileinfo`, `mbstring` (ou la compatibilité fournie). |
| Calendrier ou Bootstrap non affiché | Vérifier les erreurs/onglet Network dans **F12 DevTools**, accès aux CDN; aucun gestionnaire Node n'est requis. |
| Requête AJAX échoue | F12 → Network, inspecter `api_disponibilite.php`, session active et paramètres date/heures. |
| Import impossible dans phpMyAdmin | Exécuter `install.sql` depuis l'accueil phpMyAdmin (serveur), ou créer d'abord la BD et importer les tables. |

### Limitations / vérifications avant production

- Le projet est optimisé pour localhost. Pour le rendre public : activer HTTPS, règles de serveur renforcées, secrets hors dossier public, sauvegardes, tests de charge et revue de sécurité.
- Les rappels 24 h sont envoyés *dans l'application* quand l'administrateur clique sur **Générer rappels 24 h**. L'automatisation via le Planificateur de tâches Windows peut être ajoutée.
- Un test MySQL **réel** n'a pas été possible dans l'environnement de génération : PHP lint et tests de logique sont exécutés, pas de validation complète XAMPP/Chrome réelle. Voir `TESTS.md`.
- **Bootstrap CSS et JavaScript sont inclus en local.** Bootstrap Icons, FullCalendar et Chart.js dépendent encore du CDN : les formulaires et la navigation restent utilisables sans Internet. Une liste de calendrier de secours permet la consultation des réunions sans bibliothèque externe.
