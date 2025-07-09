<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\Projet;
use App\Services\TenantFolderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use App\Mail\CustomVerifyEmail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\URL;
use App\Services\TenantService;

class TenantRegistrationController extends Controller
{
    protected $folderService;
    protected $tenantService;

    public function __construct(TenantFolderService $folderService, TenantService $tenantService)
    {
        $this->folderService = $folderService;
        $this->tenantService = $tenantService;
    }

    public function showRegistrationForm()
    {
        $projets = Projet::all();
        return view('auth.tenant-register', compact('projets'));
    }

    public function showSuccessPage()
    {
        // Vérifier si les données de session sont disponibles
        if (!session('tenant_name') || !session('login_url')) {
            // Si pas de données, rediriger vers la création
            return redirect()->route('tenant.register')
                ->with('info', 'Session expirée. Veuillez créer un nouvel espace.');
        }

        // Exemple de génération du nom SSH
        $subdomain = session('subdomain');
        $sshUser = 'tenant_' . $subdomain;
        $chrootPath = '/home/' . $sshUser;

        // Passe ces valeurs à la vue
        $tenant_name = $subdomain; // ou la valeur correcte
        $admin_email = session('admin_email');
        return view('auth.tenant-success', [
            'tenant_name'   => $tenant_name,
            'admin_email'   => $admin_email,
            'folder_path'   => session('folder_path'),
            'ssh_username'  => $sshUser,
            'chroot_path'   => $chrootPath,
            'subdomain'     => $subdomain,
            'login_url'     => session('login_url'),
            'ssh_port'      => 22, // ou la valeur dynamique si besoin
        ]);
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_name' => 'required|string|max:255',
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|string|email|max:255',
            'admin_password' => ['required', 'confirmed', Password::min(8)],
            'projet_id' => 'required|exists:projets,id',
        ]);

        if ($validator->fails()) {
            return redirect()->route('tenant.register')
                        ->withErrors($validator)
                        ->withInput();
        }

        $validated = $validator->validated();
        // Générer le sous-domaine à partir du nom de l'entreprise (mp)
        $subdomain = Str::slug($validated['company_name'], '_');
        $validated['subdomain'] = $subdomain;

        try {
            $result = $this->tenantService->createTenant($validated);
            \Log::info('Résultat création tenant', $result);
        } catch (\Exception $e) {
            return redirect()->route('tenant.register')
                ->withErrors(['error' => 'Une erreur est survenue lors de la création de votre espace: ' . $e->getMessage()])
                ->withInput();
        }

        if ($result instanceof \Illuminate\Http\JsonResponse) {
            $data = $result->getData(true); // true = tableau associatif
        } else {
            $data = $result;
        }

        // Mettre les informations en session flash pour la page de succès
        return redirect()->route('tenant.register.success')->with([
            'tenant_name' => $result['tenant_name'],
            'login_url' => $result['login_url'],
            'admin_email' => $result['admin_email'],
            'folder_path' => $result['folder_path'],
            'subdomain' => $result['subdomain'],
        ]);
    }

    public function checkTenantExists(Request $request)
    {
        $companyName = $request->input('company_name');
        $exists = \App\Models\Tenant::where('name', $companyName)->exists();

        return response()->json(['exists' => $exists]);
    }
}