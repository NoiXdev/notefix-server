<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class SetupController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if (Setting::get('setupComplete') === 'true') {
            return redirect('/admin');
        }

        return view('setup');
    }

    public function store(Request $request): RedirectResponse
    {
        if (Setting::get('setupComplete') === 'true') {
            return redirect('/admin');
        }

        $data = $request->validate([
            'instance_name' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = new User;
            $user->forceFill([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'is_admin' => true,
            ])->save();

            Setting::put('instanceName', $data['instance_name']);
            Setting::put('setupComplete', 'true');

            return $user;
        });

        Auth::login($user);

        return redirect('/admin');
    }
}
