


@extends('emails.layout')

@section('content') <p>Estimado/a {{ $destinatario }}:</p>


@if ($type === 'COT')
    <p>
        Por medio del presente, le compartimos las cotizaciones correspondientes a los servicios solicitados.
        Quedamos atentos a la recepción de las órdenes de compra para proceder con la emisión de las facturas correspondientes.
    </p>
@elseif ($type === 'PRE')
    <p>
        Por medio del presente, le compartimos los presupuestos correspondientes a los servicios solicitados.
        Quedamos atentos a sus indicaciones para proceder con la ejecución de los servicios.
    </p>
@endif

<p>
    Para cualquier duda o información adicional, quedamos a su disposición.
</p>

<div class="signature">
    <p>Atentamente,</p>
    <p class="brand">{{ $businessProfile->business_name }}</p>
    <p class="contact-info">📞 {{ $businessProfile->phone }} {{ $businessProfile->contact_name }}</p>
</div>

@endsection
