<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Resend\Laravel\Facades\Resend;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

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

    public function sendResetLink (Request $request) {
        $request->validate(['email' => 'required|email']);

        $email = $request->email;

        $user = User::where('email', $email)->first();
        if (!$user) {
            // Para evitar revelar si el correo existe, retornamos el mismo mensaje
            return response()->json(['message' => 'Si el correo existe, te llegará un link para restablecer tu contraseña.']);
        }

        // 1) Genera token (y lo guarda en password_reset_tokens)
        $token = Password::broker()->createToken(
            \App\Models\User::where('email', $email)->firstOrFail()
        );

        // 2) Arma URL a tu React
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
        $resetUrl = "{$frontendUrl}/reset-password?token={$token}&email=" . urlencode($email);

        // 3) Envía con Resend
        $subject = 'Restablecer contraseña';

        $html = view('emails/reset_password', [
            'destinatario' => $user->name,
            'resetUrl' => $resetUrl,
        ])->render();

        Resend::emails()->send([
            'from' => 'Neón Gonz <servicios@neongonz.com>',
            'to' => [$email],
            'subject' => $subject,
            'reply_to' => 'neongonz@hotmail.com',
            'html' => $html,
        ]);

        return response()->json(['message' => 'Si el correo existe, te llegará un link para restablecer tu contraseña.']);
    }

    public function resetPassword (Request $request) {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                ])->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => 'Contraseña actualizada.'])
            : response()->json(['message' => __($status)], 422);
    }
}
