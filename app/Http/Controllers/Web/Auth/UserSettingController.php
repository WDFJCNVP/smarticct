<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UserSettingController extends Controller
{
    public function profile() {
        return view('pages.auth.profile');
    }

    public function appearance() {
        return view('pages.auth.appearance');
    }

    public function security() {
        return view('pages.settings.security');
    }
}
