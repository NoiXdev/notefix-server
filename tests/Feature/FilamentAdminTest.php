<?php

use App\Models\User;

function adminUser(): User
{
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();

    return $admin;
}

it('forbids non-admins from the admin panel', function () {
    $user = User::factory()->create(); // is_admin defaults to false

    $this->actingAs($user)->get('/admin')->assertForbidden();
});

it('lets admins into the admin panel', function () {
    $this->actingAs(adminUser())->get('/admin')->assertSuccessful();
});

it('redirects guests to the admin login', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});
