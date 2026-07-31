<?php

namespace Vatger\Auth\Controllers;

use BookStack\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use JetBrains\PhpStorm\NoReturn;
use Vatger\Auth\Provider\IAuthProvider;
use Vatger\Auth\Provider\VatgerAuthProvider;

class AuthController extends Controller
{
    private IAuthProvider $provider;

    public function __construct()
    {
        $this->provider = new VatgerAuthProvider();
    }

    #[NoReturn]
    public function login(): RedirectResponse
    {
        return $this->provider->login();
    }

    #[NoReturn]
    public function callback(Request $request): RedirectResponse
    {
        return $this->provider->callback($request);
    }

    #[NoReturn]
    public function logout(): RedirectResponse 
    {
        auth()->logout();
        session()->invalidate();

        return redirect("/")->with("success", "Logged out successfully!");
    }
}
