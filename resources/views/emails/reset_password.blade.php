@extends('emails.layout')

@section('content')
    <p>Estimado/a {{ $destinatario }},</p>
    
    <p>
        Hemos recibido una solicitud para restablecer la contraseña de su cuenta. Haga clic en el siguiente enlace para proceder con el restablecimiento:
    </p>
    <p>
        <a href="{{ $resetUrl }}">Restablecer Contraseña</a>
    </p>

    <div class="signature">
        <p>Atentamente,</p>
        <p class="brand">Neón Gonz</p>
    </div>
@endsection
