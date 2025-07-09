<?php

namespace App\Services;

class TenantVhostService
{
    /**
     * Ajoute une entrée dans /etc/hosts pour le sous-domaine du tenant
     */
    public function addHostEntry(string $subdomain)
    {
        $hostsPath = '/etc/hosts';
        $entry = "127.0.0.1 www.$subdomain.localhost";
        
        // Vérifier si l'entrée existe déjà
        $hosts = file_get_contents($hostsPath);
        if (strpos($hosts, $entry) === false) {
            // Utiliser echo avec sudo pour ajouter l'entrée
            $command = "echo '$entry' | sudo tee -a $hostsPath";
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