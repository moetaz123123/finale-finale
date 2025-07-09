<?php

namespace App\Console\Commands\Tenant;

use Illuminate\Console\Command;
use App\Models\Tenant;
use Illuminate\Support\Facades\Hash;

class SetSSHPassword extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:ssh-password {tenant : Nom ou subdomain du tenant} {password? : Mot de passe SSH (optionnel, sera demandé si non fourni)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Définir le mot de passe SSH pour un tenant';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tenantIdentifier = $this->argument('tenant');
        $password = $this->argument('password');

        // Trouver le tenant
        $tenant = Tenant::where('name', $tenantIdentifier)
            ->orWhere('subdomain', $tenantIdentifier)
            ->first();

        if (!$tenant) {
            $this->error("Tenant non trouvé : $tenantIdentifier");
            return 1;
        }

        // Générer le nom d'utilisateur SSH
        $username = 'tenant_' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $tenant->name));
        
        // Demander le mot de passe si non fourni
        if (!$password) {
            $password = $this->secret("Entrez le mot de passe SSH pour $username");
            $confirmPassword = $this->secret("Confirmez le mot de passe SSH");
            
            if ($password !== $confirmPassword) {
                $this->error("Les mots de passe ne correspondent pas !");
                return 1;
            }
        }

        // Définir le mot de passe SSH
        try {
            $command = "echo '$username:$password' | sudo chpasswd";
            exec($command, $output, $returnCode);

            if ($returnCode === 0) {
                $this->info("✅ Mot de passe SSH défini avec succès pour $username");
                $this->line("📋 Informations de connexion :");
                $this->line("   Utilisateur : $username");
                $this->line("   Commande : ssh $username@localhost");
                $this->line("   Projet : /home/$username/www.{$tenant->subdomain}.localhost");
            } else {
                $this->error("❌ Erreur lors de la définition du mot de passe SSH");
                return 1;
            }
        } catch (\Exception $e) {
            $this->error("❌ Erreur : " . $e->getMessage());
            return 1;
        }

        return 0;
    }
} 