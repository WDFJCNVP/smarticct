<?php

namespace App\Http\Controllers\Web\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class OperatorDashboardController extends Controller
{
    public function index() {
        return view('user.operator.index');
    }
}
