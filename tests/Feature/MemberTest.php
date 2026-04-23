<?php

use App\Models\Member;
use App\Models\MemberType;
use App\Models\Role;
use App\Models\User;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\MemberTypeSeeder::class);
});

test('members index is accessible by user with segreteria role', function () {
    $user = User::factory()->create();
    $user->roles()->attach(Role::where('name', 'segreteria')->first());

    $response = $this->actingAs($user)->get(route('members.index'));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->component('Members/Index')->has('members'));
});

test('members index shows members', function () {
    $user = User::factory()->create();
    $user->roles()->attach(Role::where('name', 'admin')->first());

    $type = MemberType::first();
    Member::factory()->count(2)->create(['member_type_id' => $type->id]);

    $response = $this->actingAs($user)->get(route('members.index'));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->has('members')->has('members.data'));
});

test('members create is accessible by segreteria', function () {
    $user = User::factory()->create();
    $user->roles()->attach(Role::where('name', 'segreteria')->first());

    $response = $this->actingAs($user)->get(route('members.create'));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->component('Members/Create')->has('memberTypes'));
});
