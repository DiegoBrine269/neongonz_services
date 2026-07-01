<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Models\ProjectVehicle;

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
        $user = User::findOrFail($id);

        return response()->json(['user' => $user]);
    }

    public function projectVehicles($id)
    {
        User::findOrFail($id);

        return ProjectVehicle::with([
            'vehicle:eco,id',
            'project:id,service_id,centre_id',
            'project.service:name,id',
            'project.centre:name,id',
        ])
        ->where('user_id', $id)
        ->orderBy('created_at', 'desc')
        ->paginate(50);
    }
}
