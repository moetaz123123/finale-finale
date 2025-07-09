<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class TenantFolderService
{
    /**
     * Créer la structure de dossiers pour un tenant
     */
    public function createTenantFolders(string $companyName, string $subdomain, string $domain = 'localhost'): string
    {
        // Nettoyer le nom de l'entreprise pour le dossier
        $folderName = $this->sanitizeFolderName($companyName);
        $domainName = $domain;
        
        // Utiliser /home/tenants/ pour organiser les dossiers des entreprises
        $basePath = "/home/{$folderName}";
        $wwwPath = "{$basePath}/www.{$folderName}.{$domainName}";

        \Log::info('Début création des dossiers du tenant', [
            'companyName' => $companyName,
            'subdomain' => $subdomain,
            'basePath' => $basePath,
            'wwwPath' => $wwwPath
        ]);

        try {
            // 1. Créer le dossier principal de l'entreprise avec sudo
            if (!File::exists($basePath)) {
                exec("sudo mkdir -p $basePath 2>&1", $outputBase, $returnCodeBase);
                \Log::info('Création du dossier principal', [
                    'basePath' => $basePath,
                    'output' => $outputBase,
                    'returnCode' => $returnCodeBase
                ]);
                if ($returnCodeBase !== 0) {
                    throw new \Exception("Erreur lors de la création du dossier principal: " . implode("\n", $outputBase));
                }
            }

            // 2. Créer le dossier www avec sudo
            if (!File::exists($wwwPath)) {
                exec("sudo mkdir -p $wwwPath 2>&1", $outputWww, $returnCodeWww);
                \Log::info('Création du dossier www', [
                    'wwwPath' => $wwwPath,
                    'output' => $outputWww,
                    'returnCode' => $returnCodeWww
                ]);
                if ($returnCodeWww !== 0) {
                    throw new \Exception("Erreur lors de la création du dossier www: " . implode("\n", $outputWww));
                }
            } else {
                // Si le dossier existe déjà, retourner simplement le chemin
                // Corriger les droits même si le dossier existe déjà
                $this->fixPermissions($basePath);
                \Log::info('Le dossier www existe déjà, permissions corrigées', [
                    'wwwPath' => $wwwPath
                ]);
                return $wwwPath;
            }

            // Corriger les droits sur tous les dossiers créés
            $this->fixPermissions($basePath);
            \Log::info('Permissions corrigées sur les dossiers', [
                'basePath' => $basePath
            ]);

            // 3. Copier le .env si besoin
            if (!file_exists("$wwwPath/.env") && file_exists("$wwwPath/.env.example")) {
                copy("$wwwPath/.env.example", "$wwwPath/.env");
                \Log::info('Copie de .env.example vers .env', [
                    'wwwPath' => $wwwPath
                ]);
            }

            return $wwwPath;
            
        } catch (\Exception $e) {
            \Log::error('Erreur lors de la création des dossiers du tenant', [
                'error' => $e->getMessage(),
                'basePath' => $basePath,
                'wwwPath' => $wwwPath
            ]);
            throw new \Exception("Erreur lors de la création des dossiers: " . $e->getMessage());
        }
    }
    
    /**
     * Nettoyer le nom pour créer un nom de dossier valide
     */
    private function sanitizeFolderName(string $name): string
    {
        // Remplacer les espaces par des underscores
        $name = str_replace(' ', '_', $name);
        
        // Supprimer les caractères spéciaux
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $name);
        
        // Convertir en minuscules
        $name = strtolower($name);
        
        // Limiter la longueur
        return substr($name, 0, 50);
    }
    
    /**
     * Créer les sous-dossiers nécessaires
     */
    private function createSubFolders(string $wwwPath): void
    {
        $subFolders = [
            'public',
            'private',
            'uploads',
            'logs',
            'backups'
        ];
        
        foreach ($subFolders as $folder) {
            $path = "{$wwwPath}/{$folder}";
            if (!File::exists($path)) {
                File::makeDirectory($path, 0755, true);
            }
        }
    }
    
    /**
     * Créer un fichier index.html par défaut
     */
    private function createDefaultIndex(string $wwwPath, string $companyName): void
    {
        $indexContent = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$companyName}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 40px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; text-align: center; }
        .status { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Bienvenue chez {$companyName}</h1>
        <div class="status">
            ✅ Votre espace d'entreprise a été créé avec succès !
        </div>
        <div class="info">
            <strong>Informations techniques :</strong><br>
            • Dossier créé : {$wwwPath}<br>
            • Date de création : {$this->getCurrentDate()}
        </div>
        <p>Ce site est en cours de configuration. Votre équipe technique travaille pour finaliser l'installation.</p>
        <p>Vous recevrez bientôt un email avec vos informations de connexion.</p>
    </div>
</body>
</html>
HTML;
        
        File::put("{$wwwPath}/public/index.html", $indexContent);
    }
    
    /**
     * Créer un fichier .htaccess pour Apache
     */
    private function createHtaccess(string $wwwPath): void
    {
        $htaccessContent = <<<APACHE
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ public/$1 [L]

# Sécurité
<Files ".htaccess">
    Order allow,deny
    Deny from all
</Files>

# Protection des dossiers sensibles
<Directory "private">
    Order allow,deny
    Deny from all
</Directory>
APACHE;
        
        File::put("{$wwwPath}/.htaccess", $htaccessContent);
    }
    
    /**
     * Obtenir la date actuelle formatée
     */
    private function getCurrentDate(): string
    {
        return date('d/m/Y H:i:s');
    }
    
    /**
     * Supprimer les dossiers d'un tenant
     */
    public function deleteTenantFolders(string $folderPath): bool
    {
        try {
            if (File::exists($folderPath)) {
                File::deleteDirectory($folderPath);
                return true;
            }
            return false;
        } catch (\Exception $e) {
            throw new \Exception("Erreur lors de la suppression des dossiers: " . $e->getMessage());
        }
    }

    /**
     * Corrige les droits sur tous les sous-dossiers du tenant
     */
    public function fixPermissions(string $folderPath): void
    {
        // Utiliser sudo pour définir les permissions
        exec("sudo chown -R www-data:www-data $folderPath 2>&1", $output, $returnCode);
        if ($returnCode !== 0) {
            \Log::warning("Impossible de changer le propriétaire de $folderPath", ['output' => $output]);
        }
        
        exec("sudo chmod -R 775 $folderPath 2>&1", $output, $returnCode);
        if ($returnCode !== 0) {
            \Log::warning("Impossible de changer les permissions de $folderPath", ['output' => $output]);
        }
        
        \Log::info("Permissions corrigées pour $folderPath");
    }
} 

