# RolTech GMAO

Application web de **GMAO simplifiée** (gestion de maintenance assistée par
ordinateur) développée dans le cadre d'un stage chez **Roller Technology
Systems Industry (RolTech)**.

Elle couvre un parc d'environ 40 machines réparties sur plusieurs sites en
Tunisie (Sousse, Béja, Jendouba, Tunis), qui fonctionnent **24h/24**. Les
machines sont majoritairement des convoyeurs aériens RolTech de plusieurs
modèles, auxquels s'ajoutent des modèles de démonstration (une presse, un
compresseur) qui prouvent que le catalogue est générique.

## Objet

L'application permet de :

- recenser le parc : fiche machine (numéro de série, modèle, site, zone, état,
  heures de fonctionnement) et historique ;
- **déclarer une panne** : formulaire mobile avec notification automatique ;
- **affecter automatiquement un technicien** : même site que la machine, le
  moins d'interventions ouvertes ; sinon l'intervention reste non affectée et
  les responsables du site sont notifiés ;
- dérouler un **workflow d'intervention** (démarrage, progression de la
  check-list, clôture) avec **check-list de consignation** (coupure des
  alimentations électrique et pneumatique avant toute intervention) ;
- piloter la **maintenance préventive** à partir des heures de fonctionnement
  (échéances calculées, interventions préventives générées automatiquement) ;
- utiliser un **tableau de répartition** (dispatch) pour affecter ou
  réaffecter les interventions ;
- visualiser une **carte de la Tunisie** avec l'état de chaque site ;
- explorer une **visionneuse 3D des modules** d'une machine (schéma) ;
- consulter une **page d'indicateurs (KPI)** avec **export CSV** ;
- utiliser une **recherche globale** (machines et interventions) ;
- imprimer un **QR code par machine** menant au formulaire de déclaration de
  panne pré-rempli ;
- administrer le **catalogue** (modèles, modules, pièces, tâches) avec
  **import/export CSV**.

### Les trois rôles

| Rôle | Identifiant technique | Ce qu'il peut faire |
| --- | --- | --- |
| **Administrateur** | `ROLE_ADMIN` | Accès complet : administration du catalogue (modèles, modules, pièces, tâches, plannings), utilisateurs, sites, machines ; toutes les pages des autres rôles. |
| **Responsable** | `ROLE_RESPONSABLE` | Tableau de répartition, création manuelle d'interventions, affectation/réaffectation, clôture/réouverture d'une panne, report d'une échéance, fiche machine et QR code, plans de maintenance, indicateurs KPI et export CSV. Voit tous les sites. |
| **Technicien** | `ROLE_TECHNICIEN` | Déclaration de panne, mes interventions assignées, workflow d'intervention, parc et carte. Ne voit que **son propre site** (machines, carte, recherche). |

## Stack et prérequis

- **PHP 8.2+** (extensions `intl`, `ctype`, `iconv` requises)
- **Composer**
- **MariaDB ou MySQL** : XAMPP sous Windows, MariaDB natif sous Linux
- **Symfony 7**
- **EasyAdminBundle 5** (interface d'administration)
- **AssetMapper** : aucun build Node.js/npm n'est nécessaire
- **Symfony UX Turbo** pour la navigation
- **Leaflet** (carte) et **Three.js** (visionneuse 3D) installés **localement**
  via l'importmap AssetMapper
- **Chart.js** chargé en import dynamique **depuis un CDN** (seule exception CDN
  autorisée pour le projet)
- **PHPUnit** pour les tests

## Installation

### Windows (XAMPP, PowerShell)

1. Cloner le dépôt et installer les dépendances PHP :

   ```powershell
   git clone https://github.com/Malek720/ROLTECH_GMAO.git
   cd ROLTECH_GMAO
   composer install
   ```

   `composer install` exécute aussi `importmap:install` (nommé dans les
   `auto-scripts`). Si besoin, la commande peut être relancée seule :

   ```powershell
   php bin/console importmap:install
   ```

   Le dossier `assets/vendor/` (Leaflet, Three.js, Stimulus, Turbo…) n'est **pas
   versionné** (il est listé dans `.gitignore`) : il est recréé par
   `importmap:install`.

2. Activer l'extension **`intl`** dans le `php.ini` utilisé par XAMPP
   (EasyAdmin en a besoin pour les dates), puis redémarrer Apache/PHP.

3. Créer le fichier **`.env.local`** (jamais versionné) à la racine :

   ```
   DATABASE_URL="mysql://root:@127.0.0.1:3306/roltech_gmao?serverVersion=10.4.32-MariaDB"
   DEFAULT_URI="http://127.0.0.1:8000"
   ```

   - `DATABASE_URL` : XAMPP utilise par défaut l'utilisateur `root` **sans mot
     de passe**. Remplacez `10.4.32-MariaDB` par **votre** version de MariaDB
     (visible dans phpMyAdmin ou `mysql --version`).
   - `DEFAULT_URI` : URL publique de base de l'application. Elle sert notamment
     à construire le lien encodé dans le **QR code** de la fiche machine : elle
     doit être **joignable depuis un téléphone** (donc pas `127.0.0.1` si le
     téléphone est un autre appareil ; utilisez l'adresse IP ou le nom d'hôte
     de la machine, par exemple `http://192.168.1.20:8000`).

4. Créer la base et jouer les migrations :

   ```powershell
   php bin/console doctrine:database:create
   php bin/console doctrine:migrations:migrate
   ```

5. Jeu de données de démonstration (**optionnel**) :

   ```powershell
   php bin/console doctrine:fixtures:load
   ```

   > **Attention** : cette commande **efface toutes les tables** de la base
   > visée avant de la recharger. Ne l'exécutez jamais sur une base contenant
   > des données à conserver.

6. Lancer le serveur :

   ```powershell
   symfony serve
   # ou, sans la CLI Symfony :
   php -S 127.0.0.1:8000 -t public
   ```

### Linux (zsh)

Les étapes sont identiques ; adaptez les commandes à zsh et au PHP/MariaDB du
système :

```zsh
git clone https://github.com/Malek720/ROLTECH_GMAO.git
cd ROLTECH_GMAO
composer install
php bin/console importmap:install   # si nécessaire
```

Créez `.env.local` (jamais versionné) :

```
DATABASE_URL="mysql://root:@127.0.0.1:3306/roltech_gmao?serverVersion=10.11.6-MariaDB"
DEFAULT_URI="http://127.0.0.1:8000"
```

Adaptez `serverVersion` à **votre** version de MariaDB, puis :

```zsh
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
# php bin/console doctrine:fixtures:load   # optionnel, efface toutes les tables
symfony serve
```

## Comptes de test

Les comptes de démonstration (administrateur, techniciens, responsable) sont
définis dans **`src/DataFixtures/AppFixtures.php`**. Les **mots de passe ne sont
pas reproduits ici** : consultez ce fichier pour les valeurs exactes.

À titre indicatif, les adresses de connexion utilisées par les fixtures sont :

- `admin@roltechsystems.com` — Administrateur
- `malek.responsable@roltechsystems.com` — Responsable
- `karim.technicien@roltechsystems.com` — Technicien
- `sami.technicien@roltechsystems.com` — Technicien

Le sélecteur de rôle sur la page de connexion est **cosmétique** : les
autorisations viennent uniquement des rôles stockés en base.

## Tests

Les tests s'exécutent sur une base **séparée**. Par convention, le nom de la
base de test est le nom configuré dans `DATABASE_URL` suivi de **`_test`**
(suffixe `dbname_suffix: _test` défini dans `config/packages/doctrine.yaml`).
Avec `roltech_gmao`, la base de test est donc `roltech_gmao_test`.

Créer la base et son schéma (une fois par machine) :

```bash
php bin/console doctrine:database:create --env=test
php bin/console doctrine:schema:create --env=test
```

**Après tout changement de schéma**, recréez le schéma de la base de test :

```bash
php bin/console doctrine:schema:drop --env=test --force
php bin/console doctrine:schema:create --env=test
```

Lancer la suite :

```bash
php vendor/bin/phpunit
# avec le détail de chaque test :
php vendor/bin/phpunit --testdox
```

> **Avertissement** : ne pointez **jamais** les tests vers une base contenant
> des données réelles. Les tests de base de données écrivent et isolent leurs
> données ; vérifiez la base ciblée avant de lancer la suite.

## Planification de `app:maintenance:check`

La commande :

```bash
php bin/console app:maintenance:check
```

recalcule les échéances des plannings, crée les **interventions préventives
échues** et génère les notifications associées. Elle est **idempotente** :
relancée plusieurs fois, elle ne crée jamais deux interventions préventives
ouvertes pour le même planning.

### Windows — Planificateur de tâches

Créez d'abord un petit script `maintenance.bat` (pour éviter les problèmes de
guillemets), en adaptant les chemins :

```bat
@echo off
cd /d C:\chemin\vers\ROLTECH_GMAO
C:\xampp\php\php.exe bin\console app:maintenance:check >> var\log\maintenance-check.log 2>&1
```

Puis planifiez-le toutes les heures :

```powershell
schtasks /Create /TN "RolTech GMAO - maintenance" /SC HOURLY /TR "C:\chemin\vers\maintenance.bat"
```

Via l'interface graphique :

1. Ouvrir **Planificateur de tâches** (`taskschd.msc`).
2. **Créer une tâche** (et non « tâche de base »).
3. Onglet **Déclencheurs** → **Nouveau** → *Toutes les 1 heure*, répétition
   illimitée.
4. Onglet **Actions** → **Nouveau** → *Démarrer un programme* → le fichier
   `maintenance.bat` (ou `php.exe` avec `bin\console app:maintenance:check`
   comme arguments).
5. Valider, puis tester avec **Exécuter**.

### Linux — cron

Toutes les heures (`crontab -e`) :

```
0 * * * * cd /chemin/vers/ROLTECH_GMAO && php bin/console app:maintenance:check >> var/log/maintenance-check.log 2>&1
```

## Structure du projet

```
src/
├── Command/        app:maintenance:check
├── Controller/     pages applicatives (panne, intervention, carte, fiche, KPI,
│                   recherche, sites, notifications) et Admin/ (CRUD EasyAdmin)
├── Entity/         entités Doctrine (Machine, MachineModel, MachineModule, Part,
│                   MaintenanceTask, MaintenanceSchedule, Fault, Intervention…)
├── Repository/     requêtes (dont la restriction d'accès par rôle)
├── Service/        logique métier : MaintenancePlanner, workflows panne et
│                   intervention, génération de plannings, KPI, catalogue CSV,
│                   carte, santé des modules, QR code
├── Security/       authentification et redirection par rôle
├── EventListener/  synchronisation des plannings (MachineScheduleListener)
└── DataFixtures/   jeu de données de démonstration
templates/          gabarits Twig (dashboards, admin, panne, intervention…)
assets/             AssetMapper : styles, carte, visionneuse 3D des modules
docs/               documentation projet et notes de phase
tests/              tests unitaires et fonctionnels (PHPUnit)
migrations/         migrations Doctrine
```

### Données de démonstration

Seul le modèle **OC-CA1100** s'appuie sur le **manuel de maintenance RolTech**.
Tous les autres modèles, ainsi que leurs modules, pièces et tâches, sont des
**données de démonstration inventées**. Elles sont systématiquement signalées
par le badge « Données de démonstration », leurs références de pièces suivent
le format `DEMO-<MODEL>-<MODULE>-<nn>`, et les exports incluent le statut des
données afin qu'aucune donnée de démonstration ne puisse être confondue avec
une donnée RolTech.

## Confidentialité

Le manuel de maintenance OC-CA1100 et les données qui en sont dérivées sont
**confidentiels** et la propriété de RolTech. Ils ne doivent **ni être publiés,
ni recopiés** dans un dépôt public ni dans aucune page publique. Ce README ne
reproduit volontairement aucun texte, référence de pièce ni tableau issu du
manuel.

## Limites connues

- La **visionneuse 3D est schématique** : elle construit des formes
  procédurales à partir des modules. Le chargement de fichiers `.glb` n'est
  **pas implémenté**.
- Les **fréquences de maintenance de l'OC-CA1100** sont des propositions par
  défaut, à confirmer avec RolTech ; elles restent modifiables (données, pas
  code).
- Certaines **icônes Font Awesome** sont chargées depuis un **CDN** : sans
  connexion, elles peuvent ne pas s'afficher (le texte et la couleur restent
  présents, l'état n'est jamais indiqué par la couleur seule).
- Certains comportements **navigateur** (rendu 3D, carte interactive, graphiques
  Chart.js, QR à l'impression) n'ont **pas de test automatisé** et relèvent
  d'une vérification manuelle.

## Documentation

Cahier des charges, modélisation UML, comptes rendus de réunion et démo vidéo du
prototype sont disponibles sur le dépôt de documentation :

[GitHub — Malek720/ROLTECH_STAGE_DOCS](https://github.com/Malek720/ROLTECH_STAGE_DOCS)
