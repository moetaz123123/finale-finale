# Système Multi-Tenant avec Création de Dossiers

Ce système Laravel permet de créer automatiquement des espaces d'entreprise avec :
- Base de données dédiée
- Utilisateur administrateur
- Structure de dossiers personnalisée

## Fonctionnalités

### 1. Création de Base de Données
- Base de données nommée : `tenant_{subdomain}`
- Tables créées automatiquement via migrations
- Utilisateur administrateur créé avec les informations fournies

### 2. Création de Dossiers
Structure créée : `/home/{nom_entreprise}/www.{subdomain}.{domaine}/`

```
/home/entreprise_test/
└── www.demo.localhost/
    ├── public/
    │   └── index.html (page de bienvenue)
    ├── private/ (fichiers sensibles)
    ├── uploads/ (téléchargements)
    ├── logs/ (journaux)
    ├── backups/ (sauvegardes)
    └── .htaccess (configuration Apache)
```

### 3. Processus d'Inscription

1. **Validation des données** :
   - Nom de l'entreprise
   - Sous-domaine unique
   - Informations administrateur (nom, email, mot de passe)

2. **Création automatique** :
   - Base de données MySQL
   - Structure de dossiers
   - Enregistrement tenant dans la base principale
   - Utilisateur administrateur
   - Email de vérification

3. **Gestion d'erreurs** :
   - Rollback automatique en cas d'erreur
   - Suppression des dossiers créés si échec
   - Messages d'erreur détaillés

## Configuration

### Variables d'environnement (.env)
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ayoub_laravel
DB_USERNAME=root
DB_PASSWORD=Root@1234

# Configuration multi-tenant
TENANT_DATABASE_PREFIX=tenant_
TENANT_DOMAIN_SUFFIX=.localhost
```

### Configuration des dossiers
- **Développement** : `/tmp/{nom_entreprise}/` (pour les tests)
- **Production** : `/home/{nom_entreprise}/` (dossier réel)

## Utilisation

### 1. Formulaire d'inscription
Accédez à `/tenant/register` pour créer un nouvel espace d'entreprise.

### 2. Données requises
- **Nom de l'entreprise** : Nom complet de l'entreprise
- **Sous-domaine** : Identifiant unique (ex: demo, test, entreprise)
- **Nom administrateur** : Nom de l'utilisateur administrateur
- **Email administrateur** : Email de connexion
- **Mot de passe** : Mot de passe sécurisé

### 3. Résultat
Après inscription réussie :
- Base de données : `tenant_{subdomain}`
- Dossier : `/home/{nom_entreprise}/www.{subdomain}.localhost/`
- URL de connexion : `http://{subdomain}.localhost:8000/login`
- Email de vérification envoyé

## Sécurité

### Protection des dossiers
- Dossier `private/` protégé par `.htaccess`
- Permissions 755 sur les dossiers
- Validation des noms de fichiers

### Base de données
- Connexions séparées par tenant
- Transactions pour garantir l'intégrité
- Rollback automatique en cas d'erreur

## Maintenance

### Commandes utiles
```bash
# Voir les tenants existants
php artisan tenant:list

# Réparer un tenant
php artisan tenant:repair {subdomain}

# Supprimer un tenant
php artisan tenant:delete {subdomain}
```

### Nettoyage
Le système inclut des méthodes pour :
- Supprimer les dossiers d'un tenant
- Nettoyer les bases de données orphelines
- Gérer les erreurs de création

## Développement

### Ajouter de nouveaux champs
1. Modifier le modèle `Tenant`
2. Créer une migration
3. Mettre à jour le contrôleur
4. Modifier les vues si nécessaire

### Personnaliser la structure de dossiers
Modifier `TenantFolderService::createSubFolders()` pour ajouter/supprimer des dossiers.

## Support

Pour toute question ou problème :
1. Vérifier les logs Laravel
2. Contrôler les permissions des dossiers
3. Vérifier la connexion MySQL
4. Consulter les erreurs dans la console 

## Correction à appliquer

1. **Créer le dossier principal de l'entreprise**
2. **Cloner le repo dans le dossier www**
3. **Créer les sous-dossiers nécessaires dans le projet cloné**
4. **Copier .env.example en .env**
5. **Générer la clé Laravel**
6. **Remplir le .env avec les infos DB**
7. **Installer les dépendances Composer**

```php
// 1. Créer le dossier principal de l'entreprise
if (!File::exists($basePath)) {
    File::makeDirectory($basePath, 0755, true);
}

// 2. Cloner le repo dans le dossier www (le dossier ne doit pas exister)
if (!File::exists($wwwPath)) {
    exec("git clone $repoUrl $wwwPath", $output, $code1);
}

// 3. Copier .env.example en .env
if (file_exists("$wwwPath/.env.example")) {
    copy("$wwwPath/.env.example", "$wwwPath/.env");
}

// 4. Générer la clé Laravel
exec("cd $wwwPath && php artisan key:generate", $output, $code2);

// 5. Remplir le .env avec les infos DB
$envPath = "$wwwPath/.env";
if (file_exists($envPath)) {
    $env = file_get_contents($envPath);

    // Variables dynamiques
    $appName = $companyName;
    $appUrl = "http://www.$folderName.$domainName";
    $dbName = "tenant_" . $folderName;
    $dbUser = "root";
    $dbPass = "Root@1234";

    // Remplacement dynamique
    $env = preg_replace('/^APP_NAME=.*/m', 'APP_NAME="' . $appName . '"', $env);
    $env = preg_replace('/^APP_URL=.*/m', 'APP_URL=' . $appUrl, $env);
    $env = preg_replace('/^DB_DATABASE=.*/m', 'DB_DATABASE=' . $dbName, $env);
    $env = preg_replace('/^DB_USERNAME=.*/m', 'DB_USERNAME=' . $dbUser, $env);
    $env = preg_replace('/^DB_PASSWORD=.*/m', 'DB_PASSWORD=' . $dbPass, $env);

    file_put_contents($envPath, $env);
}

// 6. Installer les dépendances Composer
exec("cd $wwwPath && composer install --ignore-platform-reqs", $output, $code3);

// 7. (Optionnel) Créer les sous-dossiers spécifiques si tu veux les forcer
$this->createSubFolders($wwwPath);
``` 