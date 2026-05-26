<?php

namespace App\Http\Controllers\Web\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AdminPanelController extends Controller
{
    public function index() {
        return view('pages.content-by-role.admin.index');
    }

    public function users() {
        return view('pages.content-by-role.admin.users_page');
    }

    public function register() {
        return view('pages.content-by-role.admin.register_user');
    }
}
