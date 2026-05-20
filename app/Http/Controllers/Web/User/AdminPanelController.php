<?php

namespace App\Http\Controllers\Web\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AdminPanelController extends Controller
{
    public function index() {
        return view('user.admin.index');
    }

    public function users() {
        return view('user.admin.users');
    }
}
