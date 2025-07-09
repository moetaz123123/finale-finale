# 🔐 SSH et Environnement Chroot pour les Tenants

Cette fonctionnalité permet de créer un **environnement SSH isolé** pour chaque tenant avec un **chroot** qui simule un serveur complet.

## 🎯 Fonctionnalités

- ✅ **Utilisateur SSH unique** pour chaque tenant
- ✅ **Environnement chroot isolé** avec structure `/etc`, `/bin`, `/usr`, `/home`, etc.
- ✅ **Accès sécurisé** au projet du tenant via SSH
- ✅ **Isolement complet** : chaque tenant voit son propre environnement
- ✅ **Gestion automatique** des permissions et configurations

## 🚀 Installation

### 1. Configuration initiale (à faire une seule fois)

```bash
# Exécuter le script de configuration (nécessite sudo)
sudo ./setup-ssh-permissions.sh
```

Ce script :
- Crée le répertoire `/home/chroot`
- Configure les permissions sudo pour `www-data`
- Met à jour la configuration SSH
- Redémarre le service SSH
- Corrige automatiquement les permissions de `/home`

### 2. Test de la configuration

```bash
# Tester la configuration complète
./test-tenant-creation.sh
```

### 2. Vérification

```bash
# Vérifier que SSH fonctionne
sudo systemctl status ssh

# Vérifier les permissions sudo
sudo -l -U www-data
```

## 📋 Utilisation

### Création automatique

Lors de la création d'un tenant via l'interface web, **tout est automatisé** :

1. ✅ **Utilisateur SSH** créé automatiquement
2. ✅ **Environnement chroot** configuré avec la structure complète
3. ✅ **Projet Laravel** copié dans le chroot
4. ✅ **Permissions** configurées automatiquement
5. ✅ **Configuration SSH** mise à jour
6. ✅ **Permissions /home** vérifiées et corrigées si nécessaire

**Aucune intervention manuelle requise !** 🎉

### Gestion manuelle

#### Définir un mot de passe SSH pour un tenant

```bash
# Avec mot de passe en argument
php artisan tenant:ssh-password "nom_tenant" "mot_de_passe"

# Ou en mode interactif
php artisan tenant:ssh-password "nom_tenant"
```

#### Tester l'environnement chroot

```bash
# Tester un tenant spécifique
./test-chroot.sh tenant_nom_entreprise
```

## 🔐 Connexion SSH

### Informations de connexion

- **Utilisateur** : `tenant_nom_entreprise`
- **Hôte** : `localhost`
- **Port** : `22` (par défaut)
- **Commande** : `ssh tenant_nom_entreprise@localhost`

### Structure dans le chroot

Une fois connecté, le tenant voit :

```
/
├── bin/          # Binaires essentiels (bash, ls, cat, etc.)
├── dev/          # Périphériques
├── etc/          # Configuration système
├── home/         # Répertoire utilisateur
│   └── tenant_nom_entreprise/
│       └── www.nom_entreprise.localhost/  # Lien vers le projet
├── lib/          # Bibliothèques système
├── lib64/        # Bibliothèques 64-bit
├── proc/         # Informations processus
├── tmp/          # Fichiers temporaires
├── usr/          # Programmes utilisateur
└── var/          # Données variables
```

### Accès au projet

```bash
# Se connecter
ssh tenant_nom_entreprise@localhost

# Naviguer vers le projet
cd /home/tenant_nom_entreprise/www.nom_entreprise.localhost

# Lister les fichiers
ls -la

# Exécuter des commandes Laravel
php artisan list
composer install
```

## 🛠️ Commandes utiles

### Dans l'environnement chroot

```bash
# Voir où on est
pwd

# Voir l'utilisateur actuel
whoami

# Lister les fichiers
ls -la

# Voir les processus
ps aux

# Voir l'espace disque
df -h
```

### Gestion des tenants

```bash
# Lister tous les tenants
php artisan tenant:list

# Voir les détails d'un tenant
php artisan tenant:show nom_tenant

# Supprimer un tenant (supprime aussi l'utilisateur SSH)
php artisan tenant:delete nom_tenant
```

## 🔧 Dépannage

### Problèmes courants

#### 1. Erreur de permission sudo

```bash
# Vérifier les permissions
sudo -l -U www-data

# Si problème, reconfigurer
sudo ./setup-ssh-permissions.sh
```

#### 2. Connexion SSH refusée

```bash
# Vérifier le service SSH
sudo systemctl status ssh

# Vérifier la configuration
sudo sshd -t

# Redémarrer SSH
sudo systemctl restart ssh
```

#### 3. Environnement chroot incomplet

```bash
# Tester l'environnement
./test-chroot.sh tenant_nom

# Recréer si nécessaire
php artisan tenant:recreate nom_tenant
```

### Logs

```bash
# Logs SSH
sudo tail -f /var/log/auth.log

# Logs Laravel
tail -f storage/logs/laravel.log
```

## 🔒 Sécurité

### Bonnes pratiques

1. **Mots de passe forts** : Utilisez des mots de passe complexes
2. **Clés SSH** : Privilégiez l'authentification par clé plutôt que par mot de passe
3. **Permissions** : Vérifiez régulièrement les permissions des répertoires chroot
4. **Audit** : Surveillez les connexions SSH dans `/var/log/auth.log`

### Limitations

- Les utilisateurs chroot ne peuvent pas installer de nouveaux paquets
- Accès limité aux commandes système
- Pas d'accès aux autres tenants
- Isolation réseau stricte

## 📝 Exemples

### Création complète d'un tenant

1. **Créer le tenant** via l'interface web
2. **Définir le mot de passe SSH** :
   ```bash
   php artisan tenant:ssh-password "Ma Entreprise" "MotDePasse123!"
   ```
3. **Tester la connexion** :
   ```bash
   ssh tenant_ma_entreprise@localhost
   ```
4. **Accéder au projet** :
   ```bash
   cd /home/tenant_ma_entreprise/www.ma_entreprise.localhost
   ls -la
   ```

### Gestion quotidienne

```bash
# Vérifier les tenants actifs
php artisan tenant:list

# Voir les connexions SSH récentes
sudo tail -20 /var/log/auth.log | grep "tenant_"

# Nettoyer les sessions expirées
sudo pkill -f "sshd.*tenant_"
```

## 🆘 Support

En cas de problème :

1. Vérifiez les logs : `tail -f storage/logs/laravel.log`
2. Testez l'environnement : `./test-chroot.sh tenant_nom`
3. Vérifiez SSH : `sudo systemctl status ssh`
4. Consultez les permissions : `sudo -l -U www-data`

---

**Note** : Cette fonctionnalité nécessite des privilèges sudo pour fonctionner correctement. Assurez-vous que l'utilisateur `www-data` a les permissions nécessaires. 