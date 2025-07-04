<!DOCTYPE html>
<html>
<head>
    <title>Inscription Réussie ! - Laravel Multi-Tenant</title>
    <style>
        /* Using the same styles for consistency */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .container { background: white; border-radius: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); max-width: 650px; width: 100%; overflow: hidden; text-align: center; padding: 3rem; }
        .icon { font-size: 5rem; color: #34D399; margin-bottom: 1.5rem; }
        h1 { color: #333; font-size: 2.2rem; margin-bottom: 1rem; }
        p { color: #666; font-size: 1.1rem; line-height: 1.6; margin-bottom: 1rem; }
        .login-btn { display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 30px; border: none; border-radius: 10px; font-size: 1.1rem; text-decoration: none; transition: transform 0.3s ease; margin-top: 1rem; }
        .login-btn:hover { transform: translateY(-2px); }
        .details { background: #f8f9fa; border-radius: 10px; padding: 1.5rem; margin: 1.5rem 0; text-align: left; }
        .details h3 { color: #333; margin-bottom: 1rem; font-size: 1.2rem; }
        .detail-item { margin-bottom: 0.8rem; }
        .detail-label { font-weight: bold; color: #555; }
        .detail-value { color: #007bff; font-family: monospace; background: #e9ecef; padding: 0.2rem 0.5rem; border-radius: 3px; }
        .success-list { text-align: left; margin: 1rem 0; }
        .success-list li { margin-bottom: 0.5rem; color: #666; }
        .success-list li::before { content: "✅ "; color: #34D399; }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🎉</div>
        <h1>Félicitations, {{ $tenant_name }} !</h1>
        <p>Votre espace d'entreprise a été créé avec succès. Voici ce qui a été configuré :</p>

        <div class="success-list">
            <ul>
                <li>Base de données créée : <span class="detail-value">tenant_{{ session('subdomain', 'demo') }}</span></li>
                <li>Utilisateur administrateur créé : <span class="detail-value">{{ $admin_email }}</span></li>
                <li>Dossier d'entreprise créé : <span class="detail-value">{{ $folder_path ?? 'N/A' }}</span></li>
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
                <span class="detail-label">Sous-dossiers :</span><br>
                <span class="detail-value">public/</span> (fichiers web)<br>
                <span class="detail-value">private/</span> (fichiers sensibles)<br>
                <span class="detail-value">uploads/</span> (téléchargements)<br>
                <span class="detail-value">logs/</span> (journaux)<br>
                <span class="detail-value">backups/</span> (sauvegardes)
            </div>
        </div>

        <p>Vous pouvez maintenant vous connecter et commencer à utiliser votre espace d'entreprise.</p>

        <a href="{{ $login_url }}" class="login-btn">Accéder à mon espace</a>
    </div>
</body>
</html> 