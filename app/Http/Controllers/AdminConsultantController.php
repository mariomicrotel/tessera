<?php

namespace App\Http\Controllers;

use App\Models\ConsultantAssignment;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Gestione consulenti dalla dashboard superadmin.
 *
 * Permette di:
 *   - Assegnare / revocare il ruolo "consultant" a un utente della piattaforma
 *   - Creare / disattivare / eliminare le assegnazioni consulente → tenant
 *
 * Tutte le azioni sono riservate al super admin (authorizeSuperAdmin).
 */
class AdminConsultantController extends Controller
{
    /* ── Index ─────────────────────────────────────────────────────────── */

    public function index(Request $request)
    {
        $this->authorizeSuperAdmin();

        // Utenti con ruolo consultant (qualunque tenant)
        $consultants = User::whereHas('roles', fn ($q) => $q->where('name', 'consultant'))
            ->withCount([
                'consultantAssignments as assignments_count',
                'consultantAssignments as active_assignments_count' => fn ($q) => $q->where('active', true),
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        // Tutti gli utenti della piattaforma (per il select "assegna ruolo")
        $allUsers = User::orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn ($u) => [
                'id'              => $u->id,
                'name'            => $u->name,
                'email'           => $u->email,
                'is_consultant'   => $consultants->contains('id', $u->id),
            ]);

        // Assegnazioni con relazioni
        $assignments = ConsultantAssignment::with(['consultant:id,name,email', 'tenant:id,name,slug'])
            ->when($request->filled('consultant_id'), fn ($q) => $q->where('consultant_user_id', $request->consultant_id))
            ->when($request->filled('tenant_id'),     fn ($q) => $q->where('tenant_id', $request->tenant_id))
            ->when($request->filled('active'), fn ($q) => $q->where('active', (bool) $request->active))
            ->orderByDesc('created_at')
            ->paginate(30)->withQueryString();

        // Tenant per select
        $tenants = Tenant::orderBy('name')->get(['id', 'name', 'slug']);

        return Inertia::render('Admin/Consultants/Index', [
            'consultants' => $consultants->map(fn ($u) => [
                'id'                      => $u->id,
                'name'                    => $u->name,
                'email'                   => $u->email,
                'assignments_count'       => $u->assignments_count,
                'active_assignments_count' => $u->active_assignments_count,
            ])->values(),
            'allUsers'    => $allUsers->values(),
            'assignments' => $assignments->through(fn ($a) => [
                'id'              => $a->id,
                'consultant'      => ['id' => $a->consultant->id, 'name' => $a->consultant->name, 'email' => $a->consultant->email],
                'tenant'          => ['id' => $a->tenant->id, 'name' => $a->tenant->name, 'slug' => $a->tenant->slug],
                'ruolo'           => $a->ruolo,
                'active'          => $a->active,
                'started_at'      => $a->started_at?->toDateString(),
                'note'            => $a->note,
                'created_at'      => $a->created_at->toDateString(),
            ]),
            'tenants'  => $tenants,
            'filters'  => $request->only('consultant_id', 'tenant_id', 'active'),
        ]);
    }

    /* ── Ruolo consultant ──────────────────────────────────────────────── */

    /**
     * Assegna il ruolo "consultant" a un utente.
     */
    public function assignRole(Request $request)
    {
        $this->authorizeSuperAdmin();

        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $user = User::findOrFail($request->user_id);
        $role = Role::where('name', 'consultant')->firstOrFail();

        if (! $user->roles()->where('name', 'consultant')->exists()) {
            $user->roles()->attach($role->id);
        }

        return back()->with('flash', ['type' => 'success', 'message' => "Ruolo consulente assegnato a {$user->name}."]);
    }

    /**
     * Revoca il ruolo "consultant" da un utente.
     * Disattiva anche tutte le sue assegnazioni attive.
     */
    public function revokeRole(User $user)
    {
        $this->authorizeSuperAdmin();

        $role = Role::where('name', 'consultant')->first();
        if ($role) {
            $user->roles()->detach($role->id);
        }

        // Disattiva tutte le assegnazioni
        ConsultantAssignment::where('consultant_user_id', $user->id)->update(['active' => false]);

        return back()->with('flash', ['type' => 'success', 'message' => "Ruolo consulente revocato a {$user->name}."]);
    }

    /* ── Assegnazioni ──────────────────────────────────────────────────── */

    /**
     * Crea una nuova assegnazione consulente → tenant.
     */
    public function storeAssignment(Request $request)
    {
        $this->authorizeSuperAdmin();

        $request->validate([
            'consultant_user_id' => 'required|integer|exists:users,id',
            'tenant_id'          => 'required|string|exists:tenants,id',
            'ruolo'              => 'required|in:primario,secondario',
            'started_at'         => 'nullable|date',
            'note'               => 'nullable|string|max:1000',
        ]);

        // Verifica che l'utente abbia il ruolo consultant
        $user = User::find($request->consultant_user_id);
        if (! $user->roles()->where('name', 'consultant')->exists()) {
            return back()->with('flash', ['type' => 'error', 'message' => 'L\'utente non ha il ruolo consulente. Assegnalo prima.']);
        }

        ConsultantAssignment::updateOrCreate(
            [
                'consultant_user_id' => $request->consultant_user_id,
                'tenant_id'          => $request->tenant_id,
            ],
            [
                'ruolo'              => $request->ruolo,
                'active'             => true,
                'started_at'         => $request->started_at ?: now()->toDateString(),
                'note'               => $request->note,
                'assigned_by_user_id' => auth()->id(),
            ]
        );

        $tenant = Tenant::find($request->tenant_id);

        return back()->with('flash', ['type' => 'success', 'message' => "Assegnazione creata: {$user->name} → {$tenant->name}."]);
    }

    /**
     * Attiva / disattiva un'assegnazione.
     */
    public function toggleAssignment(ConsultantAssignment $assignment)
    {
        $this->authorizeSuperAdmin();

        $assignment->update(['active' => ! $assignment->active]);

        $status = $assignment->active ? 'attivata' : 'disattivata';

        return back()->with('flash', ['type' => 'success', 'message' => "Assegnazione {$status}."]);
    }

    /**
     * Elimina un'assegnazione.
     */
    public function destroyAssignment(ConsultantAssignment $assignment)
    {
        $this->authorizeSuperAdmin();

        $assignment->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Assegnazione eliminata.']);
    }

    /* ── Helper ─────────────────────────────────────────────────────────── */

    private function authorizeSuperAdmin(): void
    {
        if (! auth()->user()?->is_super_admin) {
            abort(403, 'Accesso riservato al super admin della piattaforma.');
        }
    }
}
