<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class SessionUserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('auth.login-option');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(string $type)
    {
        return view('auth.login', ['type' => $type]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        //Validation
        $validate = $request->validate([
            'email' => ['required'],
            'password' => ['required'],
        ]);

        //Login Attempts
        if (!Auth::attempt($validate)) {
            throw ValidationException::withMessages([
                'message' => "Sorry those credentials don't match. Please try again"
            ]);
        }

        //Regenerate Sessions
        $request->session()->regenerate();

        $user = $request->user();

        return match ($user->role) {
            'admin'     => redirect()->route('admin.dashboard'),
            'operator'  => redirect()->route('operator.dashboard'),
            'passenger' => redirect()->route('passenger.dashboard')
        };
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
       Auth::logout();
 
        $request->session()->invalidate();
    
        $request->session()->regenerateToken();
    
        return redirect('/');
    }
}
