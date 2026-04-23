<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Inertia\Inertia;

/**
 * Gestione utenti, ruoli e associazione utente-socio. Solo admin.
 */
class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin');
    }

    public function index(Request $request)
    {
        $query = User::query()->with(['roles', 'member']);

        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($qry) use ($q) {
                $qry->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }
        if ($request->filled('role')) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('name', $request->role);
            });
        }
        if ($request->filled('has_member')) {
            if ($request->has_member === '1') {
                $query->whereHas('member');
            } elseif ($request->has_member === '0') {
                $query->whereDoesntHave('member');
            }
        }

        $users = $query->orderBy('name')->paginate(15)->withQueryString();
        $roles = Role::orderBy('name')->get(['id', 'name', 'display_name']);
        $membersForSelect = Member::query()
            ->whereNull('user_id')
            ->orderBy('cognome')
            ->orderBy('nome')
            ->get(['id', 'cognome', 'nome', 'email']);

        return Inertia::render('Users/Index', [
            'users' => $users,
            'roles' => $roles,
            'filters' => $request->only('search', 'role', 'has_member'),
            'membersForSelect' => $membersForSelect,
        ]);
    }

    public function store(Request $request)
    {
        $validIds = Role::pluck('id')->toArray();
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['nullable', 'string', PasswordRule::default()],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['integer', 'in:'.implode(',', $validIds)],
        ]);

        $password = $request->filled('password')
            ? Hash::make($request->password)
            : Hash::make(Str::random(32));

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $password,
        ]);

        $user->roles()->sync($request->input('role_ids', []));

        $message = 'Utente creato.';
        if (! $request->filled('password')) {
            try {
                Password::sendResetLink(['email' => $user->email]);
                $message = 'Utente creato. È stata inviata un\'email per impostare la password.';
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('users.index')
            ->with('flash', ['type' => 'success', 'message' => $message]);
    }

    public function updateRoles(Request $request, User $user)
    {
        $validIds = Role::pluck('id')->toArray();
        $request->validate([
            'role_ids' => 'nullable|array',
            'role_ids.*' => 'integer|in:'.implode(',', $validIds),
        ]);

        $user->roles()->sync($request->input('role_ids', []));

        return redirect()->route('users.index')
            ->with('flash', ['type' => 'success', 'message' => 'Ruoli aggiornati.']);
    }

    public function linkMember(Request $request, User $user)
    {
        $memberId = $request->input('member_id');

        if ($memberId === null || $memberId === '') {
            return $this->unlinkMember($user);
        }

        $member = Member::find($memberId);
        if (! $member) {
            return redirect()->route('users.index')
                ->with('flash', ['type' => 'error', 'message' => 'Socio non trovato.']);
        }
        if ($member->user_id !== null && $member->user_id != $user->id) {
            return redirect()->route('users.index')
                ->with('flash', ['type' => 'error', 'message' => 'Questo socio ha già un account collegato.']);
        }

        $socioRole = Role::where('name', 'socio')->first();
        if (! $socioRole) {
            return redirect()->route('users.index')
                ->with('flash', ['type' => 'error', 'message' => 'Ruolo "socio" non trovato.']);
        }

        $wasAlreadyLinked = (int) $member->user_id === (int) $user->id;

        $member->update(['user_id' => $user->id]);
        if (! $user->roles()->where('name', 'socio')->exists()) {
            $user->roles()->attach($socioRole);
        }

        if (! $wasAlreadyLinked) {
            try {
                Password::sendResetLink(['email' => $user->email]);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $message = $wasAlreadyLinked
            ? 'Associazione aggiornata.'
            : 'Socio collegato. È stata inviata un\'email per reimpostare la password.';

        return redirect()->route('users.index')
            ->with('flash', ['type' => 'success', 'message' => $message]);
    }

    private function unlinkMember(User $user): \Illuminate\Http\RedirectResponse
    {
        $member = $user->member;
        if (! $member) {
            return redirect()->route('users.index')
                ->with('flash', ['type' => 'error', 'message' => 'L\'utente non ha un socio collegato.']);
        }

        $member->update(['user_id' => null]);
        $socioRole = Role::where('name', 'socio')->first();
        if ($socioRole) {
            $user->roles()->detach($socioRole->id);
        }

        return redirect()->route('users.index')
            ->with('flash', ['type' => 'success', 'message' => 'Socio scollegato.']);
    }

    /**
     * Invia all'utente l'email per reimpostare la password.
     */
    public function sendPasswordResetLink(User $user)
    {
        try {
            $status = Password::sendResetLink(['email' => $user->email]);
            if ($status === Password::RESET_LINK_SENT) {
                return redirect()->route('users.index')
                    ->with('flash', ['type' => 'success', 'message' => 'Email per reimpostare la password inviata a ' . $user->email . '.']);
            }
            $message = $status === Password::INVALID_USER
                ? 'Nessun utente trovato con questa email.'
                : 'Impossibile inviare l\'email. Riprova più tardi.';
            return redirect()->route('users.index')
                ->with('flash', ['type' => 'error', 'message' => $message]);
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('users.index')
                ->with('flash', ['type' => 'error', 'message' => 'Errore durante l\'invio: ' . $e->getMessage()]);
        }
    }

    /**
     * Elimina l'utente. Il socio eventualmente collegato non viene eliminato (viene solo scollegato).
     */
    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return redirect()->route('users.index')
                ->with('flash', ['type' => 'error', 'message' => 'Non puoi eliminare il tuo account da qui.']);
        }

        if ($user->member) {
            $user->member->update(['user_id' => null]);
        }

        $user->deleteProfilePhoto();
        $user->tokens->each->delete();
        $user->delete();

        return redirect()->route('users.index')
            ->with('flash', ['type' => 'success', 'message' => 'Utente eliminato. Il socio eventualmente collegato non è stato modificato.']);
    }
}
