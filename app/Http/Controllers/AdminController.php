<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;


class AdminController extends Controller
{
    public function getUsers(Request $request)
    {
        $users = User::all();

        return $users;
    }

    public function changeUserPassword(Request $request, User $user)
    {
        $fields = $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user->forceFill([
            'password' => Hash::make($fields['password']),
        ])->save();

        return response()->json(['message' => 'Contraseña actualizada exitosamente.']);
    }

    public function changeUserStatus(Request $request, User $user)
    {
        $fields = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $user->is_active = $fields['is_active'];
        $user->save();

        return response()->json(['message' => 'Estado de usuario actualizado exitosamente.']);
    }
}
