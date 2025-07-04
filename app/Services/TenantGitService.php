<?php

namespace App\Services;

class TenantGitService
{
    public function cloneRepository(string $gitUrl, string $destinationPath)
    {
        $gitVersion = shell_exec('git --version 2>&1');
        if (strpos($gitVersion, 'git version') === false) {
            throw new \Exception('Git n\'est pas installé sur le système.');
        }
        if (!file_exists($destinationPath)) {
            if (!mkdir($destinationPath, 0755, true)) {
                throw new \Exception('Impossible de créer le dossier de destination: ' . $destinationPath);
            }
        }
        $files = scandir($destinationPath);
        if (count($files) > 2) {
            throw new \Exception('Le dossier de destination n\'est pas vide: ' . $destinationPath);
        }
        // Clonage sans sudo
        exec("git clone $gitUrl $destinationPath 2>&1", $output, $returnCode);
        if ($returnCode !== 0) {
            throw new \Exception('Erreur lors du clonage du repository: ' . implode("\n", $output) . " (Code: $returnCode)");
        }
        // Correction des droits
        exec("sudo chown -R www-data:www-data $destinationPath");
        exec("sudo chmod -R 775 $destinationPath");
        if (!file_exists($destinationPath . '/.git')) {
            throw new \Exception('Le repository n\'a pas été cloné correctement. Dossier .git manquant.');
        }
    }
} 