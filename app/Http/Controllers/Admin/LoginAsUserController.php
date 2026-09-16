<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class LoginAsUserController extends Controller
{
    public function __invoke(User $user): RedirectResponse
    {
        /** @var User|null $admin */
        $admin = Auth::user();

        abort_unless($admin instanceof User && $admin->isAdmin(), 403);
        abort_if($admin->id === $user->id, 403);

        session(['impersonator_id' => $admin->id]);

        Auth::login($user);

        return redirect()->to($user->dashboardUrl());
    }
}
