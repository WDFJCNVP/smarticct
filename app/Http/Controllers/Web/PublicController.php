<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Carbon;

use App\Models\Vehicle;
use App\Models\Queue;

class PublicController extends Controller
{
    public function index() {
        return view('pages.welcome');
    }
}
