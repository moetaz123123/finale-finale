<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class TenantEnvService
{
    public function configureLaravelApp(string $folderPath, string $companyName, string $subdomain, string $databaseName)
    {
        try {
            // 1. Copier .env.example vers .env si possible
            $envExamplePath = "$folderPath/.env.example";
            $envPath = "$folderPath/.env";
            if (file_exists($envExamplePath)) {
                exec("cp $envExamplePath $envPath");
                exec("sudo chown www-data:www-data $envPath");
                exec("sudo chmod 664 $envPath");
                \Log::info("Copie de .env.example vers .env réussie", ['folder' => $folderPath]);
            } else {
                // Générer un .env minimal si .env.example absent
                $envContent = "APP_NAME=\"$companyName\"\nAPP_ENV=local\nAPP_KEY=\nAPP_DEBUG=true\nAPP_URL=http://$subdomain.localhost:8000\nDB_CONNECTION=mysql\nDB_HOST=127.0.0.1\nDB_PORT=3306\nDB_DATABASE=$databaseName\nDB_USERNAME=root\nDB_PASSWORD=Root@1234\nSESSION_DRIVER=database\nCACHE_STORE=database\nQUEUE_CONNECTION=database\nMAIL_MAILER=log\n";
                file_put_contents($envPath, $envContent);
                exec("sudo chown www-data:www-data $envPath");
                exec("sudo chmod 664 $envPath");
                \Log::warning(".env.example absent, .env minimal généré", ['folder' => $folderPath]);
            }

            // 2. Vérifier que le .env existe
            if (!file_exists($envPath)) {
                \Log::error("Le fichier .env n'a pas pu être créé", ['folder' => $folderPath]);
                throw new \Exception("Le fichier .env n'a pas pu être créé dans $folderPath");
            }

            // 3. Adapter/ajouter les variables dans le .env
            $env = file_get_contents($envPath);
            $replacements = [
                'APP_NAME'         => 'APP_NAME="' . $companyName . '"',
                'APP_URL'          => 'APP_URL=http://' . $subdomain . '.localhost:8000',
                'DB_CONNECTION'    => 'DB_CONNECTION=mysql',
                'DB_HOST'          => 'DB_HOST=127.0.0.1',
                'DB_PORT'          => 'DB_PORT=3306',
                'DB_DATABASE'      => 'DB_DATABASE=' . $databaseName,
                'DB_USERNAME'      => 'DB_USERNAME=root',
                'DB_PASSWORD'      => 'DB_PASSWORD=Root@1234',
                'SESSION_DRIVER'   => 'SESSION_DRIVER=database',
                'CACHE_STORE'      => 'CACHE_STORE=database',
                'QUEUE_CONNECTION' => 'QUEUE_CONNECTION=database',
                'MAIL_MAILER'      => 'MAIL_MAILER=log',
            ];
            foreach ($replacements as $key => $value) {
                if (preg_match('/^' . preg_quote($key, '/') . '=.*/m', $env)) {
                    $env = preg_replace('/^' . preg_quote($key, '/') . '=.*/m', $value, $env);
                } else {
                    $env .= "\n" . $value;
                }
            }
            file_put_contents($envPath, $env);
            \Log::info("Variables .env adaptées/ajoutées", ['folder' => $folderPath]);
 // 5. Configurer les permissions avant d'exécuter les commandes artisan
            exec("sudo chown -R www-data:www-data " . escapeshellarg($folderPath));
            exec("sudo chmod -R 775 " . escapeshellarg($folderPath));
            \Log::info('Permissions corrigées', ['folder' => $folderPath]);
            // 4. Installer les dépendances Composer
            $output = [];
            $returnCode = 0;
            
            // Debug : vérifier si composer est accessible
            exec("which composer", $composerPath, $composerPathCode);
            \Log::info('Composer path check', [
                'path' => implode("\n", $composerPath),
                'code' => $composerPathCode
            ]);
            
            exec("cd " . escapeshellarg($folderPath) . " && composer install --no-interaction 2>&1", $output, $returnCode);
            if ($returnCode !== 0) {
                \Log::warning('Impossible d\'installer les dépendances Composer', [
                    'folder' => $folderPath,
                    'output' => implode("\n", $output)
                ]);
            } else {
                \Log::info('Dépendances Composer installées', ['folder' => $folderPath]);
            }

           

            // 6. Générer la clé Laravel (une seule fois)
            $output = [];
            $returnCode = 0;
            exec("cd " . escapeshellarg($folderPath) . " && php artisan key:generate --force", $output, $returnCode);
            if ($returnCode !== 0) {
                \Log::warning('Impossible de générer la clé Laravel', [
                    'folder' => $folderPath,
                    'output' => implode("\n", $output)
                ]);
            } else {
                \Log::info('Clé Laravel générée', ['folder' => $folderPath]);
            }

            // 7. Configurer la base de données et exécuter les migrations
            Config::set('database.connections.tenant.database', $databaseName);
            DB::purge('tenant');
            DB::reconnect('tenant');
            \Log::info('Connexion DB tenant configurée', ['database' => $databaseName]);
            \Artisan::call('migrate', [
                '--database' => 'tenant',
                '--force' => true,
            ]);
            \Log::info('Migrations exécutées', ['folder' => $folderPath]);

            // Exécuter le seeder ProjetSeeder pour le tenant
            \Artisan::call('db:seed', [
                '--database' => 'tenant',
                '--class' => 'ProjetSeeder',
                '--force' => true,
            ]);
            \Log::info('ProjetSeeder exécuté pour le tenant', ['folder' => $folderPath]);

        } catch (\Exception $e) {
            \Log::error('Erreur lors de la configuration de l\'application Laravel', [
                'folder' => $folderPath,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function runSeederAndStorageLink(string $folderPath)
    {
        try {
            // Nettoyer la base de données avant d'exécuter le seeder
            $output = [];
            $returnCode = 0;
            exec("cd " . escapeshellarg($folderPath) . " && php artisan migrate:fresh --seed --force 2>&1", $output, $returnCode);
            if ($returnCode !== 0) {
                \Log::warning('Impossible d\'exécuter migrate:fresh --seed', [
                    'folder' => $folderPath,
                    'output' => implode("\n", $output),
                    'returnCode' => $returnCode
                ]);
            } else {
                \Log::info('Base de données nettoyée et seeder exécuté avec succès', [
                    'folder' => $folderPath
                ]);
            }
            
            // Exécuter storage:link
            $output = [];
            $returnCode = 0;
            exec("cd " . escapeshellarg($folderPath) . " && php artisan storage:link 2>&1", $output, $returnCode);
            if ($returnCode !== 0) {
                \Log::warning('Impossible d\'exécuter storage:link', [
                    'folder' => $folderPath,
                    'output' => implode("\n", $output),
                    'returnCode' => $returnCode
                ]);
            } else {
                \Log::info('Storage link créé avec succès', [
                    'folder' => $folderPath
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Erreur lors de l\'exécution du seeder et storage:link', [
                'folder' => $folderPath,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function finalizeTenantSetup(string $folderPath, string $subdomain)
    {
        // 2. Corriger les droits sur le dossier
        exec("sudo chown -R www-data:www-data " . escapeshellarg($folderPath));
        exec("sudo chmod -R 775 " . escapeshellarg($folderPath));
        \Log::info('Permissions corrigées', ['folder' => $folderPath]);
        // 1. Installer les dépendances Composer
        $output = [];
        $returnCode = 0;
        exec("cd " . escapeshellarg($folderPath) . " && sudo -u www-data composer install --no-interaction", $output, $returnCode);
        if ($returnCode !== 0) {
            \Log::warning('Impossible d\'installer les dépendances Composer', [
                'folder' => $folderPath,
                'output' => implode("\n", $output)
            ]);
        } else {
            \Log::info('Dépendances Composer installées', ['folder' => $folderPath]);
        }

        

        // 3. Générer la clé Laravel
        $output = [];
        $returnCode = 0;
        exec("cd " . escapeshellarg($folderPath) . " && sudo -u www-data php artisan key:generate --force", $output, $returnCode);
        if ($returnCode !== 0) {
            \Log::warning('Impossible de générer la clé Laravel', [
                'folder' => $folderPath,
                'output' => implode("\n", $output)
            ]);
        } else {
            \Log::info('Clé Laravel générée', ['folder' => $folderPath]);
        }

        // 4. Ajouter l'entrée dans /etc/hosts
        $hostsPath = '/etc/hosts';
        $entry = "127.0.0.1 www.$subdomain.localhost";
        $hosts = file_get_contents($hostsPath);
        if (strpos($hosts, $entry) === false) {
            $command = "echo \"$entry\" | sudo tee -a $hostsPath";
            exec($command, $output, $returnCode);
            if ($returnCode !== 0) {
                \Log::warning('Impossible d\'ajouter l\'entrée dans /etc/hosts', [
                    'subdomain' => $subdomain,
                    'entry' => $entry,
                    'output' => implode("\n", $output),
                    'returnCode' => $returnCode
                ]);
            } else {
                \Log::info('Entrée ajoutée dans /etc/hosts', [
                    'subdomain' => $subdomain,
                    'entry' => $entry
                ]);
            }
        } else {
            \Log::info('Entrée déjà présente dans /etc/hosts', [
                'subdomain' => $subdomain,
                'entry' => $entry
            ]);
        }
    }
} 

