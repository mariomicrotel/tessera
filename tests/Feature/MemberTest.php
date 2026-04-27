<?php

use App\Models\Member;
use App\Models\MemberType;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    (new RoleSeeder)->run();

    $this->tenant = Tenant::create([
        'name'              => 'ETS Member Test',
        'slug'              => 'ets-member-' . uniqid(),
        'organization_type' => 'ets',
        'plan'              => 'free',
        'is_active'         => true,
        'codice_fiscale'    => '91000099999',
    ]);

    app()->instance('current_tenant', $this->tenant);

    $memberType = MemberType::create([
        'tenant_id'    => $this->tenant->id,
        'name'         => 'socio',
        'display_name' => 'Socio',
    ]);
    $this->memberType = $memberType;
});

test('members index is accessible by user with segreteria role', function () {
    $user = User::factory()->create();
    $user->roles()->attach(Role::where('name', 'segreteria')->first());
    $user->tenants()->attach($this->tenant);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => inertiaVersion()])
        ->get(route('members.index', $this->tenant));

    $response->assertStatus(200);
    $page = json_decode($response->getContent(), true);
    expect($page['component'])->toBe('Members/Index');
    expect($page['props'])->toHaveKey('members');
});

test('members index shows members', function () {
    $user = User::factory()->create();
    $user->roles()->attach(Role::where('name', 'admin')->first());
    $user->tenants()->attach($this->tenant);

    Member::factory()->count(2)->create([
        'tenant_id'      => $this->tenant->id,
        'member_type_id' => $this->memberType->id,
    ]);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => inertiaVersion()])
        ->get(route('members.index', $this->tenant));

    $response->assertStatus(200);
    $page = json_decode($response->getContent(), true);
    expect($page['props'])->toHaveKey('members');
});

test('members create is accessible by segreteria', function () {
    $user = User::factory()->create();
    $user->roles()->attach(Role::where('name', 'segreteria')->first());
    $user->tenants()->attach($this->tenant);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => inertiaVersion()])
        ->get(route('members.create', $this->tenant));

    $response->assertStatus(200);
    $page = json_decode($response->getContent(), true);
    expect($page['component'])->toBe('Members/Create');
    expect($page['props'])->toHaveKey('memberTypes');
});
