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

    public function showSuccessPage(Request $request)
    {
        // Assurez-vous que les données sont bien passées en session flash
        if (!session('tenant_name') || !session('login_url') || !session('admin_email')) {
            return redirect()->route('tenant.register');
        }

        return view('auth.tenant-success', [
            'tenant_name' => session('tenant_name'),
            'login_url' => session('login_url'),
            'admin_email' => session('admin_email'),
            'folder_path' => session('folder_path'),
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
        } catch (\Exception $e) {
            return redirect()->route('tenant.register')
                ->withErrors(['error' => 'Une erreur est survenue lors de la création de votre espace: ' . $e->getMessage()])
                ->withInput();
        }

        // Mettre les informations en session flash pour la page de succès
        return redirect()->route('tenant.register.success')->with([
            'tenant_name' => $result['tenant_name'],
            'login_url' => $result['login_url'],
            'admin_email' => $result['admin_email'],
            'folder_path' => $result['folder_path'],
        ]);
    }
}
