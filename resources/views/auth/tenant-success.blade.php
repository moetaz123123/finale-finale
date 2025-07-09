<!DOCTYPE html>
<html>
<head>
    <title>Inscription Réussie ! - Laravel Multi-Tenant</title>
    <meta name="robots" content="noindex, nofollow">
    <style>
        /* Using the same styles for consistency */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .container { background: white; border-radius: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); max-width: 650px; width: 100%; overflow: hidden; text-align: center; padding: 3rem; }
        .icon { font-size: 5rem; color: #34D399; margin-bottom: 1.5rem; }
        h1 { color: #333; font-size: 2.2rem; margin-bottom: 1rem; }
        p { color: #666; font-size: 1.1rem; line-height: 1.6; margin-bottom: 1rem; }
        .login-btn { display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 30px; border: none; border-radius: 10px; font-size: 1.1rem; text-decoration: none; transition: transform 0.3s ease; margin: 0.5rem; }
        .login-btn:hover { transform: translateY(-2px); }
        .secondary-btn { display: inline-block; background: #6c757d; color: white; padding: 15px 30px; border: none; border-radius: 10px; font-size: 1.1rem; text-decoration: none; transition: transform 0.3s ease; margin: 0.5rem; }
        .secondary-btn:hover { transform: translateY(-2px); background: #5a6268; }
        .details { background: #f8f9fa; border-radius: 10px; padding: 1.5rem; margin: 1.5rem 0; text-align: left; }
        .details h3 { color: #333; margin-bottom: 1rem; font-size: 1.2rem; }
        .detail-item { margin-bottom: 0.8rem; }
        .detail-label { font-weight: bold; color: #555; }
        .detail-value { color: #007bff; font-family: monospace; background: #e9ecef; padding: 0.2rem 0.5rem; border-radius: 3px; }
        .success-list { text-align: left; margin: 1rem 0; }
        .success-list li { margin-bottom: 0.5rem; color: #666; }
        .success-list li::before { content: "✅ "; color: #34D399; }
        .session-notice { background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 8px; padding: 1rem; margin: 1rem 0; color: #0c5460; }
        .button-group { margin-top: 2rem; }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🎉</div>
        <h1>Félicitations, {{ $tenant_name }} !</h1>
        <p>Votre espace d'entreprise a été créé avec succès. Voici ce qui a été configuré :</p>

        <div class="success-list">
            <ul>
                <li>Base de données créée : <span class="detail-value">tenant_{{ $subdomain }}</span></li>
                <li>Utilisateur administrateur créé : <span class="detail-value">{{ $admin_email }}</span></li>
                <li>Dossier d'entreprise créé : <span class="detail-value">{{ $folder_path ?? 'N/A' }}</span></li>
                <li>Utilisateur SSH créé : <span class="detail-value">{{ $ssh_username ?? 'N/A' }}</span></li>
                <li>Environnement chroot isolé configuré</li>
                <li>Email de vérification envoyé</li>
            </ul>
        </div>

        <div class="details">
            <h3>📁 Structure des dossiers créés :</h3>
            <div class="detail-item">
                <span class="detail-label">Dossier principal :</span><br>
                <span class="detail-value">{{ $folder_path ?? 'N/A' }}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Environnement chroot :</span><br>
                <span class="detail-value">{{ $chroot_path ?? 'N/A' }}</span>
            </div>
        </div>

        <div class="details">
            <h3>🔐 Accès SSH :</h3>
            <div class="detail-item">
                <span class="detail-label">Nom d'utilisateur SSH :</span><br>
                <span class="detail-value">{{ $ssh_username ?? 'N/A' }}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Port SSH :</span><br>
                <span class="detail-value">{{ $ssh_port ?? '22' }}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Commande de connexion :</span><br>
                <span class="detail-value">ssh {{ $ssh_username ?? 'N/A' }}@localhost</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Accès au projet dans chroot :</span><br>
                <span class="detail-value">/home/{{ $ssh_username ?? 'N/A' }}/www.{{ $subdomain }}.localhost</span>
            </div>
        </div>


       

        <div class="button-group">
        <a href="{{ $login_url }}" class="login-btn">Accéder à mon espace</a>
            <a href="{{ route('tenant.register') }}" class="secondary-btn">Créer un autre espace</a>
        </div>
    </div>
</body>
</html> 