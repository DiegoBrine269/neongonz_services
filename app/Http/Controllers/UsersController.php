<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UsersController extends Controller
{
    //

    public function index()
    {
        $users = User::all();
        return response()->json($users);
    }


    public function show($id)
    {
        $user = User::with([
            'projectVehicles.vehicle:eco,id',
            'projectVehicles.project.service:name,id',
            'projectVehicles.project.centre:name,id',

        ])->findOrFail($id);

        return response()->json($user);
    }
}
