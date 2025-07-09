<?php

namespace App\Services;

use Exception;
use App\Models\Tenant;
use App\Models\Projet;
use App\Services\TenantFolderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Auth\Events\Registered;
use App\Services\TenantDatabaseService;
use App\Services\TenantGitService;
use App\Services\TenantVhostService;
use App\Services\TenantEnvService;
use App\Services\TenantSSHService;
use Illuminate\Support\Str;

class TenantService
{
    protected $dbService;
    protected $gitService;
    protected $vhostService;
    protected $envService;
    protected $folderService;
    protected $sshService;

    public function __construct(
        TenantDatabaseService $dbService,
        TenantGitService $gitService,
        TenantVhostService $vhostService,
        TenantEnvService $envService,
        TenantFolderService $folderService,
        TenantSSHService $sshService
    ) {
        $this->dbService = $dbService;
        $this->gitService = $gitService;
        $this->vhostService = $vhostService;
        $this->envService = $envService;
        $this->folderService = $folderService;
        $this->sshService = $sshService;
    }

    public function createTenant(array $data)
    {
        // Générer le nom court à partir de company_name (mp)
        $mp = Str::slug($data['company_name'], '_');
        $subdomain = $mp;
        $databaseName = 'tenant_' . $mp;
        $databaseTestName = 'tenant_' . $mp . '_test';
        $projet = Projet::findOrFail($data['projet_id']);
        $domain = config('app.domain', 'localhost');

        // 1. Création de la base de données principale
        $this->dbService->createDatabase($databaseName);
        // 1.bis Création de la base de test
        $this->dbService->createDatabase($databaseTestName);

        // 2. Créer l'utilisateur SSH et l'environnement chroot (structure système + htdocs)
        $sshInfo = $this->sshService->createSSHUser($data['company_name'], $subdomain);
        $chrootPath = $sshInfo['chroot_path'];
        $projectPath = "$chrootPath/htdocs/www.$subdomain.localhost";

        // 3. Cloner le repository Git directement dans le chroot
        $this->gitService->cloneRepository($projet->lien_git, $projectPath);

        // 4. Configurer l'application Laravel clonée (.env, permissions, clé, dépendances, migrations) dans le chroot
        $this->envService->configureLaravelApp($projectPath, $data['company_name'], $subdomain, $databaseName);

        // 4.bis : Lancer le seeder ProjetSeeder pour le tenant (après les migrations, avant la création de l'admin)
        \Artisan::call('db:seed', [
            '--database' => 'tenant',
            '--class' => 'ProjetSeeder',
            '--force' => true,
        ]);
        \Log::info('ProjetSeeder exécuté pour le tenant', ['folder' => $projectPath]);

   

        // 6. Création du tenant (transaction sur la connexion principale)
        DB::beginTransaction();
        $tenant = Tenant::create([
            'name' => $data['company_name'],
            'subdomain' => $subdomain,
            'database' => $databaseName,
            'folder_path' => $projectPath,
            'is_active' => true,
        ]);
        DB::commit();

        // 7. Création de l'admin (transaction sur la connexion tenant)
        DB::connection('tenant')->beginTransaction();
        $userId = DB::connection('tenant')->table('users')->insertGetId([
            'name' => $data['admin_name'],
            'email' => $data['admin_email'],
            'password' => Hash::make($data['admin_password']),
            'is_admin' => true,
            'tenant_id' => $tenant->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user = DB::connection('tenant')->table('users')->where('id', $userId)->first();
        DB::connection('tenant')->commit();

        $loginUrl = "http://www.$subdomain.localhost:8000/";

        // Déclencher l'événement Registered pour la compatibilité Laravel
        event(new Registered($user));

        // Générer la clé d'application Laravel dans le sous-dossier du tenant
        $tenantPath = $projectPath;

        // Installer les dépendances Composer
        exec("cd $tenantPath && composer install --no-interaction --no-progress 2>&1", $outputComposer, $retComposer);
        if ($retComposer !== 0) {
            throw new \Exception('Erreur composer install : ' . implode(PHP_EOL, $outputComposer));
        }

        // Correction des permissions
        exec('chown -R www-data:www-data ' . escapeshellarg($tenantPath));
        exec('chmod -R 775 ' . escapeshellarg($tenantPath));
        exec('chown www-data:www-data ' . escapeshellarg($tenantPath . '/.env'));
        exec('chmod 664 ' . escapeshellarg($tenantPath . '/.env'));

        // Génération de la clé
        $this->envService->generateAppKey($tenantPath);

        \Log::info('Après key:generate', [
            'env_content' => file_get_contents("$tenantPath/.env"),
        ]);

        \Log::info('Création du tenant terminée sans erreur', ['tenant' => $tenant]);
        return [
            'tenant_name' => $tenant->name,
            'login_url' => $loginUrl,
            'admin_email' => $user->email,
            'folder_path' => $projectPath,
            'subdomain' => $tenant->subdomain,
            'ssh_username' => $sshInfo['username'],
            'ssh_port' => $sshInfo['ssh_port'],
            'chroot_path' => $sshInfo['chroot_path'],
        ];
    }
} 