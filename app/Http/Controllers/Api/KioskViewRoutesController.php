<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\RouteList;

class KioskViewRoutesController extends Controller
{
    public function getRouteList() {

        $data = RouteList::with('operatorTicketRate')->get();

        return response->json([
            
        ]);

    }
}
