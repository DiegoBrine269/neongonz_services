


@extends('emails.layout')

@section('content')
    <p>Estimado/a {{ $destinatario }},</p>
    
    
    @if ($type === 'COT')
        <p>
            Le compartimos las cotizaciones correspondientes a los servicios realizados.    
            Quedamos a la espera de las órdenes de compra para proceder con la emisión de las facturas.
        </p>
    @elseif ($type === 'PRE')
        <p>
            Le comparto los presupuestos correspondientes a los servicios solicitados, quedamos en la espera de sus indicaciones para comenzar con los servicios. 
        </p>
    @endif
    <p>
        Para cualquier duda o información adicional, quedamos a su disposición.
    </p>

    <div class="signature">
        <p>Atentamente,</p>
        <p class="brand">Neón Gonz</p>
        <p class="contact-info">📞 55 3026 3958 (Jazmín Jasso)</p>
    </div>
@endsection
