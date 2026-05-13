<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Models\Tenant;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_super_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
        'is_super_admin'    => 'boolean',
    ];

    /**
     * Ruoli assegnati all'utente (admin, contabile, segreteria, socio).
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')->withTimestamps();
    }

    /**
     * Socio/volontario collegato (opzionale, per area self-service).
     */
    public function member(): HasOne
    {
        return $this->hasOne(Member::class);
    }

    /**
     * Cache locale (per-request) del check "consulente attivo sul tenant corrente".
     */
    protected ?array $_consultantCache = null;

    /**
     * Verifica se l'utente ha uno dei ruoli indicati.
     * Il superadmin bypassa qualsiasi vincolo di ruolo.
     *
     * Se l'utente è un consulente (commercialista) attivo sul tenant corrente,
     * acquisisce automaticamente i diritti di admin/contabile/segreteria per
     * poter gestire tutti i dati necessari a bilancio e adempimenti dell'ente.
     */
    public function hasRole(string ...$roles): bool
    {
        if ($this->is_super_admin) {
            return true;
        }

        // Check diretto sui ruoli assegnati
        if ($this->roles()->whereIn('name', $roles)->exists()) {
            return true;
        }

        // Consulente attivo sul tenant corrente → diritti di gestione completa
        $rolesProConsultant = ['admin', 'contabile', 'segreteria'];
        if (array_intersect($roles, $rolesProConsultant) && $this->isConsultantForCurrentTenant()) {
            return true;
        }

        return false;
    }

    /**
     * Verifica se l'utente è un consulente attivo sul tenant corrente.
     * Usa cache locale per evitare query ripetute nella stessa request.
     */
    public function isConsultantForCurrentTenant(): bool
    {
        if (! app()->bound('current_tenant')) {
            return false;
        }

        $tenantId = app('current_tenant')->id;
        $cacheKey = $this->id . ':' . $tenantId;

        if ($this->_consultantCache !== null && array_key_exists($cacheKey, $this->_consultantCache)) {
            return $this->_consultantCache[$cacheKey];
        }

        $isConsultant = ConsultantAssignment::where('consultant_user_id', $this->id)
            ->where('tenant_id', $tenantId)
            ->where('active', true)
            ->exists();

        $this->_consultantCache[$cacheKey] = $isConsultant;

        return $isConsultant;
    }

    /**
     * Verifica se è admin (bypass permessi).
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Assegnazioni come consulente (cross-tenant).
     */
    public function consultantAssignments(): HasMany
    {
        return $this->hasMany(ConsultantAssignment::class, 'consultant_user_id');
    }

    /**
     * Tenant (organizzazioni) a cui l'utente appartiene.
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Verifica se l'utente è super admin della piattaforma SaaS.
     * Priorità: flag DB `is_super_admin`; fallback su email configurata.
     */
    public function getIsSuperAdminAttribute(): bool
    {
        if (! empty($this->attributes['is_super_admin'])) {
            return (bool) $this->attributes['is_super_admin'];
        }
        return $this->email === config('saas.super_admin_email');
    }

    /**
     * Ruolo dell'utente nel tenant corrente.
     */
    public function roleInTenant(?Tenant $tenant = null): ?string
    {
        $tenant = $tenant ?? (app()->bound('current_tenant') ? app('current_tenant') : null);

        if (! $tenant) {
            return null;
        }

        $membership = $this->tenants()->where('tenants.id', $tenant->id)->first();

        return $membership?->pivot?->role;
    }

    /**
     * Verifica se l'utente ha un determinato ruolo nel tenant corrente.
     */
    public function hasRoleInTenant(string ...$roles): bool
    {
        $role = $this->roleInTenant();

        return $role && in_array($role, $roles);
    }
}
