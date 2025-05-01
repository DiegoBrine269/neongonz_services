<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
        /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $fields = $request->validate([
            'email' => 'required|email|unique:users,email,' . $request->id . ',id',
            'name' => 'required|max:255',
            'last_name' => 'required|max:255',
        ],
        [
            'email.unique' => 'El correo electrónico ya ha sido registrado.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El correo electrónico no es válido.',
            'name.required' => 'El nombre es obligatorio.',
            'last_name.required' => 'El apellido es obligatorio.',
            'name.max' => 'El nombre no puede tener más de 255 caracteres.',
            'last_name.max' => 'El apellido no puede tener más de 255 caracteres.',
        ]);

        $user = User::findOrFail($id);

        $user->update([
            'email' => $fields['email'],
            'name' => $fields['name'],
            'last_name' => $fields['last_name'],
        ]);

        return response()->json([
            'message' => 'Usuario actualizado correctamente.',
            'user' => $user,
        ], 200);
    }

    public function changePasswordSave(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|confirmed|min:8|string'
        ],[
            'current_password.required' => 'La contraseña actual es obligatoria.',
            'new_password.required' => 'La nueva contraseña es obligatoria.',
            'new_password.confirmed' => 'Por favor, confirma la nueva contraseña.',
            'new_password.min' => 'La nueva contraseña debe ser de al menos 8 caracteres.',
        ]);

        $auth = Auth::user();

        // The passwords matches
        if (!Hash::check($request->get('current_password'), $auth->password)) 
        {
            return back()->with('error', "Current Password is Invalid");
        }

        // Current password and new password same
        if (strcmp($request->get('current_password'), $request->new_password) == 0) 
        {
            return redirect()->back()->with("error", "New Password cannot be same as your current password.");
        }

        $user =  User::find($auth->id);
        $user->password =  Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'message' => 'Contraseña actualizada correctamente.',
            'user' => $user,
        ], 200);
    }
}
