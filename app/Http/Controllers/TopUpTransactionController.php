<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TopUpTransactionController extends Controller
{

    public function success()
    {
        return view('topup.success');
    }

    public function cancel()
    {
        return view('topup.cancel');
    }
}
