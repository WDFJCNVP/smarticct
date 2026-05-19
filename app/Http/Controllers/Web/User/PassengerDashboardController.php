<?php

namespace App\Http\Controllers\Web\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PassengerDashboardController extends Controller
{
    public function index() {
        return view('user.commuter.index');
    }
}   
