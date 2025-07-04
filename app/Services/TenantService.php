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
use Illuminate\Support\Str;

class TenantService
{
    protected $dbService;
    protected $gitService;
    protected $vhostService;
    protected $envService;
    protected $folderService;

    public function __construct(
        TenantDatabaseService $dbService,
        TenantGitService $gitService,
        TenantVhostService $vhostService,
        TenantEnvService $envService,
        TenantFolderService $folderService
    ) {
        $this->dbService = $dbService;
        $this->gitService = $gitService;
        $this->vhostService = $vhostService;
        $this->envService = $envService;
        $this->folderService = $folderService;
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

        // 2. Création des dossiers
        $folderPath = $this->folderService->createTenantFolders(
            $data['company_name'],
            $subdomain,
            $domain
        );

        // 3. Cloner le repository Git
        $this->gitService->cloneRepository($projet->lien_git, $folderPath);

        // 4. Configurer l'application Laravel clonée (.env, permissions, clé, dépendances, migrations)
        $this->envService->configureLaravelApp($folderPath, $data['company_name'], $subdomain, $databaseName);

        // 4.bis : Lancer le seeder ProjetSeeder pour le tenant (après les migrations, avant la création de l'admin)
        \Artisan::call('db:seed', [
            '--database' => 'tenant',
            '--class' => 'ProjetSeeder',
            '--force' => true,
        ]);
        \Log::info('ProjetSeeder exécuté pour le tenant', ['folder' => $folderPath]);

        // 5. Créer le virtual host Apache
        // $this->vhostService->createApacheVhost($subdomain, $folderPath);
        $this->vhostService->addHostEntry($subdomain);

        // 6. Création du tenant (transaction sur la connexion principale)
        DB::beginTransaction();
        $tenant = Tenant::create([
            'name' => $data['company_name'],
            'subdomain' => $subdomain,
            'database' => $databaseName,
            'folder_path' => $folderPath,
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

        $loginUrl = "http://www.$subdomain.localhost/login";

        // Déclencher l'événement Registered pour la compatibilité Laravel
        event(new Registered($user));

        return [
            'tenant_name' => $tenant->name,
            'login_url' => $loginUrl,
            'admin_email' => $user->email,
            'folder_path' => $folderPath,
        ];
    }
} 