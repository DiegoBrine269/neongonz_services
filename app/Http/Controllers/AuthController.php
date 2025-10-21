<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request){
        $fields = $request->validate([
            'email' => 'required|email|unique:users',
            'name' => 'required|max:255',
            'last_name' => 'required|max:255',
            'password' => 'required|min:8',
        ],
        [
            'email.unique' => 'El correo electrónico ya ha sido registrado.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El correo electrónico no es válido.',
            'name.required' => 'El nombre es obligatorio.',
            'last_name.required' => 'El apellido es obligatorio.',
            'name.max' => 'El nombre no puede tener más de 255 caracteres.',
            'last_name.max' => 'El apellido no puede tener más de 255 caracteres.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ]);

        

        $user = User::create($fields);

        $token = $user->createToken($request->name);

        return [
            'user' => $user,
            'token' => $token->plainTextToken
        ];
    }

    public function login(Request $request){
        $fields = $request->validate([
            'email' => 'required|email|exists:users',
            'password' => 'required',
        ],
        [
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El correo electrónico no es válido.',
            'email.exists' => 'El correo electrónico no está registrado.',
            'password.required' => 'La contraseña es obligatoria.',
        ]);

        $user = User::where('email', $request->email)->first();

        if(!$user || !Hash::check($request->password, $user->password)){
            return response()->json([
                'errors' => [
                    'email' => ['Las credenciales son incorrectas.'],
                ],
            ], 401);
        }

        $token = $user->createToken($user->name);

        return [
            'user' => $user,
            'token' => $token->plainTextToken
        ];
    }

    public function logout(Request $request){
        $current = $request->user()->currentAccessToken();
        if ($current) {
            $current->delete();
        }

        return [
            'message' => 'Has cerrado sesión correctamente.',
        ];

        return [
            'message' => 'Has cerrado sesión correctamente.',
        ];
    }
}
