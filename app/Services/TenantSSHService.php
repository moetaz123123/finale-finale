<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;

class TenantSSHService
{
    /**
     * Créer un utilisateur SSH et son environnement chroot pour un tenant
     */
    public function createSSHUser(string $tenantName, string $subdomain)
    {
        try {
            $username = $this->sanitizeUsername($tenantName);
            
            // 0. Vérifier et corriger les permissions du répertoire /home principal
            $this->ensureHomeDirectoryPermissions();
            
            // 1. Créer l'utilisateur SSH
            $this->createSSHUserAccount($username);
            
            // 2. Créer l'environnement chroot (structure système uniquement)
            $this->createChrootEnvironment($username);
            
            // 3. Configurer SSH pour l'utilisateur
            $this->configureSSHForUser($username);
            
            Log::info('Utilisateur SSH et environnement chroot créés', [
                'tenant' => $tenantName,
                'username' => $username,
                'chroot_path' => "/home/$username"
            ]);
            
            return [
                'username' => $username,
                'chroot_path' => "/home/$username",
                'ssh_port' => $this->getSSHPortForUser($username)
            ];
            
        } catch (Exception $e) {
            Log::error('Erreur lors de la création de l\'utilisateur SSH', [
                'tenant' => $tenantName,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Vérifier et corriger les permissions du répertoire /home principal
     */
    private function ensureHomeDirectoryPermissions(): void
    {
        // Vérifier que /home est détenu par root (requis pour SSH chroot)
        $homeOwner = posix_getpwuid(fileowner('/home'));
        if ($homeOwner['name'] !== 'root') {
            exec("sudo chown root:root /home 2>&1", $output, $returnCode);
        }
        $homePerms = fileperms('/home') & 0777;
        if ($homePerms !== 0755) {
            exec("sudo chmod 755 /home 2>&1", $output, $returnCode);
        }
    }
    
    /**
     * Sanitizer le nom d'utilisateur
     */
    private function sanitizeUsername(string $tenantName): string
    {
        // Convertir en minuscules et remplacer les espaces par des underscores
        $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $tenantName));
        
        // Limiter la longueur
        if (strlen($username) > 20) {
            $username = substr($username, 0, 20);
        }
        
        // Ajouter un préfixe pour éviter les conflits
        return 'tenant_' . $username;
    }
    
    /**
     * Créer le compte utilisateur SSH
     */
    private function createSSHUserAccount(string $username): void
    {
        // Créer l'utilisateur avec un shell complet pour SSH
        $commands = [
            "useradd -m -s /bin/bash $username",
            "mkdir -p /home/$username/.ssh",
            "chown $username:$username /home/$username/.ssh",
            "chmod 700 /home/$username/.ssh"
        ];
        
        foreach ($commands as $command) {
            exec("sudo $command 2>&1", $output, $returnCode);
            if ($returnCode !== 0) {
                throw new Exception("Erreur lors de la création de l'utilisateur SSH: " . implode("\n", $output));
            }
        }
    }
    
    /**
     * Créer l'environnement chroot (structure système uniquement)
     */
    private function createChrootEnvironment(string $username): void
    {
        $chrootPath = "/home/$username";
        $this->createChrootStructure($chrootPath);
        $this->copySystemFiles($chrootPath);
        $this->setChrootPermissions($chrootPath, $username);
    }
    
    /**
     * Créer la structure de base du chroot
     */
    private function createChrootStructure(string $chrootPath): void
    {
        $directories = [
            'bin', 'dev', 'etc', 'lib', 'lib64', 'proc', 'tmp', 'usr', 'var', 'htdocs'
        ];
        foreach ($directories as $dir) {
            exec("sudo mkdir -p $chrootPath/$dir 2>&1", $output, $returnCode);
            if ($returnCode !== 0) {
                throw new Exception("Erreur lors de la création de la structure chroot: " . implode("\n", $output));
            }
        }
    }
    
    /**
     * Copier les fichiers système nécessaires
     */
    private function copySystemFiles(string $chrootPath): void
    {
        // Copier les binaires essentiels pour SSH
        $binaries = ['bash', 'ls', 'cat', 'pwd', 'whoami', 'id', 'echo', 'clear', 'cd', 'mkdir', 'rm', 'cp', 'mv', 'nano', 'vim', 'less', 'more', 'grep', 'find', 'ps', 'top', 'htop'];
        
        foreach ($binaries as $binary) {
            if (file_exists("/bin/$binary")) {
                exec("sudo cp /bin/$binary $chrootPath/bin/ 2>&1", $output, $returnCode);
                
                // Copier les dépendances
                $deps = $this->getBinaryDependencies("/bin/$binary");
                foreach ($deps as $dep) {
                    if (file_exists($dep)) {
                        $targetDir = "$chrootPath" . dirname($dep);
                        exec("sudo mkdir -p $targetDir 2>&1");
                        exec("sudo cp $dep $targetDir/ 2>&1");
                    }
                }
            }
        }
        
        // Copier les binaires dans /usr/bin
        $usrBinaries = ['python3', 'php', 'git', 'curl', 'wget', 'unzip', 'tar', 'gzip'];
        foreach ($usrBinaries as $binary) {
            if (file_exists("/usr/bin/$binary")) {
                exec("sudo mkdir -p $chrootPath/usr/bin 2>&1");
                exec("sudo cp /usr/bin/$binary $chrootPath/usr/bin/ 2>&1", $output, $returnCode);
                
                // Copier les dépendances
                $deps = $this->getBinaryDependencies("/usr/bin/$binary");
                foreach ($deps as $dep) {
                    if (file_exists($dep)) {
                        $targetDir = "$chrootPath" . dirname($dep);
                        exec("sudo mkdir -p $targetDir 2>&1");
                        exec("sudo cp $dep $targetDir/ 2>&1");
                    }
                }
            }
        }
        
        // Copier les fichiers de configuration essentiels
        $configFiles = [
            '/etc/passwd' => '/etc/passwd',
            '/etc/group' => '/etc/group',
            '/etc/hosts' => '/etc/hosts',
            '/etc/resolv.conf' => '/etc/resolv.conf',
            '/etc/localtime' => '/etc/localtime'
        ];
        
        foreach ($configFiles as $source => $target) {
            if (file_exists($source)) {
                exec("sudo cp $source $chrootPath$target 2>&1");
            }
        }
        
        // Créer un fichier .bashrc basique pour l'utilisateur
        $bashrc = "export PATH=/bin:/usr/bin:/usr/local/bin\n";
        $bashrc .= "export PS1='\\u@\\h:\\w\\$ '\n";
        $bashrc .= "export TERM=xterm\n";
        $bashrc .= "alias ll='ls -la'\n";
        $bashrc .= "alias la='ls -A'\n";
        $bashrc .= "alias l='ls -CF'\n";
        
        file_put_contents('/tmp/bashrc', $bashrc);
        exec("sudo cp /tmp/bashrc $chrootPath/etc/bash.bashrc 2>&1");
        exec("sudo rm /tmp/bashrc 2>&1");
        
        // Copier le dynamic linker essentiel pour bash
        if (file_exists('/lib64/ld-linux-x86-64.so.2')) {
            exec("sudo mkdir -p $chrootPath/lib64 2>&1");
            exec("sudo cp /lib64/ld-linux-x86-64.so.2 $chrootPath/lib64/ 2>&1");
        }
    }
    
    /**
     * Obtenir les dépendances d'un binaire
     */
    private function getBinaryDependencies(string $binary): array
    {
        $deps = [];
        exec("ldd $binary 2>/dev/null", $output);
        
        foreach ($output as $line) {
            if (preg_match('/=>\s+(.+)\s+\(/', $line, $matches)) {
                $deps[] = trim($matches[1]);
            }
        }
        
        return $deps;
    }
    
  
    
    /**
     * Configurer les permissions du chroot
     */
    private function setChrootPermissions(string $chrootPath, string $username): void
    {
        // Permissions strictes pour SSH chroot
        $commands = [
            "chown root:root $chrootPath",
            "chmod 755 $chrootPath"
        ];
        
        foreach ($commands as $command) {
            exec("sudo $command 2>&1", $output, $returnCode);
            if ($returnCode !== 0) {
                Log::warning("Commande échouée: $command", ['output' => $output]);
            }
        }
        
        // Permissions spéciales pour les binaires et libs
        $binaryCommands = [
            "chmod 755 $chrootPath/bin",
            "chmod 755 $chrootPath/lib",
            "chmod 755 $chrootPath/lib64",
            "chmod 755 $chrootPath/usr",
            "chmod 755 $chrootPath/etc"
        ];
        
        foreach ($binaryCommands as $command) {
            exec("sudo $command 2>&1", $output, $returnCode);
        }
        
        Log::info('Permissions chroot configurées', [
            'chroot_path' => $chrootPath,
            'username' => $username
        ]);
    }
    
    /**
     * Configurer SSH pour l'utilisateur
     */
    private function configureSSHForUser(string $username): void
    {
        $chrootPath = "/home/$username";
        $sshConfig = "Match User $username\n";
        $sshConfig .= "    ChrootDirectory $chrootPath\n";
        $sshConfig .= "    AllowTcpForwarding no\n";
        $sshConfig .= "    X11Forwarding no\n";
        $sshConfig .= "    PasswordAuthentication yes\n";
        $sshConfig .= "    PermitTTY yes\n";
        $sshConfig .= "    PermitUserEnvironment no\n\n";
        file_put_contents('/tmp/ssh_config_addition', $sshConfig);
        exec("sudo cat /tmp/ssh_config_addition >> /etc/ssh/sshd_config 2>&1");
        exec("sudo rm /tmp/ssh_config_addition 2>&1");
        exec("sudo systemctl reload ssh 2>&1", $output, $returnCode);
        if ($returnCode !== 0) {
            Log::warning('Impossible de recharger SSH, redémarrage manuel nécessaire');
        }
    }
    
    /**
     * Obtenir le port SSH pour l'utilisateur (optionnel)
     */
    private function getSSHPortForUser(string $username): int
    {
        // Par défaut, utiliser le port 22
        // Tu peux implémenter une logique pour assigner des ports différents si nécessaire
        return 22;
    }
    
    /**
     * Supprimer l'utilisateur SSH et son environnement chroot
     */
    public function removeSSHUser(string $username): void
    {
        try {
            $commands = [
                "userdel -r $username",
                "rm -rf /home/$username"
            ];
            
            foreach ($commands as $command) {
                exec("sudo $command 2>&1", $output, $returnCode);
                // On ignore les erreurs car l'utilisateur pourrait ne pas exister
            }
            
            Log::info('Utilisateur SSH et environnement chroot supprimés', ['username' => $username]);
            
        } catch (Exception $e) {
            Log::error('Erreur lors de la suppression de l\'utilisateur SSH', [
                'username' => $username,
                'error' => $e->getMessage()
            ]);
        }
    }
} 