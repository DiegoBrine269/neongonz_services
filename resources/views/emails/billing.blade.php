


@extends('emails.layout')

@section('content')
    <p>Estimado/a {{ $to }},</p>
    
    

    <p>
        Le compartimos la(s) factura(s) correspondiente(s) a la(s) orden(es) de compra:
    </p>

    <table>
        @foreach ($list as $item)
            <tr>
                <td>{{ $item['invoice_number'] }}</td>
                <td>{{ $item['oc'] }}</td>
                <td>{{ $item['billing'] }}</td>
            </tr>
        @endforeach
        </table>

    <p>
        Quedo a la espera del numero de recibo para la validación de la factura en el portal.
    </p>

    <p>
        Para cualquier duda o información adicional, quedamos a su disposición.
    </p>

    <div class="signature">
        <p>Atentamente,</p>
        <p class="brand">{{ $businessProfile->business_name }}</p>
        <p class="contact-info">📞 {{ $businessProfile->phone }} {{ $businessProfile->contact_name }}</p>
    </div>
@endsection
