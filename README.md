# ROLTECH_GMAO

Application web de supervision et de maintenance des machines industrielles
(GMAO simplifiée), développée dans le cadre d'un stage chez
**Roller Technology Systems Industry**.

## Objectifs

1. Recenser le parc machines (fiches techniques, historique)
2. Enregistrer les pannes et interventions (préventives et curatives)
3. Suivre l'état de chaque machine (en service, en panne, en maintenance)
4. Générer des tableaux de bord (indicateurs de disponibilité, fréquence des pannes)
5. Gérer les utilisateurs (techniciens, responsables) avec authentification et rôles

## Stack technique

- **Backend** : Symfony (PHP)
- **Base de données** : MySQL
- **Interface d'administration** : EasyAdminBundle
- **Versioning** : Git

## Documentation

Cahier des charges, modélisation UML, comptes-rendus de réunion et démo vidéo
du prototype sont disponibles sur le dépôt de documentation :

[GitHub — Malek720/ROLTECH_STAGE_DOCS](https://github.com/Malek720/ROLTECH_STAGE_DOCS)

## Installation locale

```bash
git clone https://github.com/Malek720/ROLTECH_GMAO.git
cd ROLTECH_GMAO
composer install
```

Configurer la connexion base de données dans `.env.local` :

```
DATABASE_URL="mysql://root:@127.0.0.1:3306/roltech_gmao?serverVersion=8.0"
```

Puis :

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load   # données de test (optionnel)
symfony serve
```

Interface d'administration disponible sur `/admin`.
