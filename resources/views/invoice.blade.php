<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Cotización</title>

    <style> 

        body {
            font-family: Arial, Helvetica, sans-serif;
        }

        html {
            width:100%;
            margin: 40px 60px;
            font-size: 12px;
        }

        img{
            max-width: 100%;
        }

        .d-inline-block {
            display: inline-block;
        }
        
        
        .bold {
            font-weight: bold;
        }
    
        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .text-left {
            text-align: left;
        }

        table {
            width: 100%;
        } 

        table, tr, td {
            padding-left: 0;
        }

        td {
            /* text-align: center; */
            /* vertical-align: middle; */
        }



        /* .logo-container {
            width: 25%;
        } */

        .header-text {

            text-align: center;
            font-size: 15px;
            padding-top: 0; 
        }

        .nombre {
            font-size: 15px;
        }

        .titulo {
            width: 25%;
            color: rgb(126, 126, 126) ;
            font-size: 30px;
            text-align: right
        }

        .datos {
            width: 100%;
        }

        .fecha {
            width: 100%;
        }

        .fiscales, p {
            margin-top: 0px;
            margin-bottom: 2px;
        }

        .cotizacion {
            padding: 0;
            vertical-align:top;
        }

        .lista td, .lista th {
            border: 1px solid rgb(104, 103, 103);
            border-collapse: collapse;
            padding: 2px 4px;
            height: 15px;
        }

    </style>
</head>
<body>
    <header>
        <table>
            <tr>
                <td style="width: 32%; padding:20px">
                    <img class="logo" src="{{ public_path('images/logo-invoice.jpg') }}" alt="Logotipo de neongonz">
                </td>
                
                <td>
                    <div class="header-text">
                        <p class="nombre bold">Neon Gonz</p>
                    </div>
                    
                    <div style="text-align: center; font-size: 11px">
                        <p>Carlos Ramón González Oloarte GOOC021121EX0</p>
                        <p>Calle UNO, MZ 1, LT 15, Col. Xalpa, CP. 09640, Iztapalapa. Ciudad de México.</p>
                        <p>Correo: neongonz@hotmail.com</p>
                        <p>Teléfono: 55 3026 3958</p>
                        
                    </div>

                </td>
        
                <td style="width: 32%; padding-left:5px;">
                    <div style="width:100%; border: 1px solid black; padding: 8px; font-size: 10px;">
                        <p class="bold" style=" text-align: center; text-transform: uppercase">Cotización</p>
                        <p><span class="bold" >Número:</span> {{$invoice_number}}</p>
                        <p><span class="bold" >Lugar de expedición</span>: Ciudad de México</p>
                        <p><span class="bold" >Fecha de expedición</span>: {{$date}}</p>
                    </div>
                </td>
            </tr>
        </table>
    </header>



            

    <main>

        <br>
        <p class="bold">Cotización para:</p>
        <table class="destinatario">
            <tr>
                <td style="width: 75%; padding:0">
                    BIMBO, S.A DE C.V
                </td>
                <td><p style="line-height: 0; margin:0; text-align: right"><span class="bold">At'n:</span> {{ $centre->responsible->name }}</p> </td>
            </tr>
            <tr>
                <td>
                    {{ $centre->name }}
                </td>
            </tr>
        </table>
    
        <br>
    
        @if ($comments)
            <p><span class="bold">Comentarios o instrucciones especiales:</span> {{$comments}} </p>
        @endif
        <table class="lista" cellspacing="0" cellpadding="0">
            <tr>
                <th class="bold">Cantidad</th>
                <th class="bold">Descripción</th>
                <th class="bold">Precio</th>
                <th class="bold">Total</th>
            </tr>

            @php
                $grandTotal = 0; // Variable para acumular el total
            @endphp


            @if($custom)
                <tr>
                    <td class="text-center" style="min-width: 70px">{{ $quantity }}</td>
                    <td >
                        {{ $concept }} 
                    </td>
                    <td class="text-right" style="min-width: 70px"><span class="text-left">$</span> {{ number_format($price, 2) }}</td>
                    <td class="text-right" style="min-width: 70px"><span class="text-left">$</span> {{ number_format($quantity * $price, 2) }}</td>
                </tr>

            @php
                $grandTotal = $quantity * $price; 
            @endphp
            
            {{-- Si no es custom, muestra los vehículos agrupados por proyecto y tipo --}}
            @else
                @foreach ($projects as $project)                
                    @foreach ($project->vehicles_grouped_by_price as $price => $grouped_by_price)

                        @foreach ($grouped_by_price as $data)
                            @php
                                $grouped_vehicles_by_type = $data['group'];
                                $type = $data['type'];
                                // $grouped_vehicles = $data['group']; // Obtén el grupo de vehículos
                                $totalForGroup = $grouped_vehicles_by_type->sum('price'); // Calcula el total del grupo
                                $grandTotal += $totalForGroup; 
                            @endphp
                            <tr>
                                <td class="text-center" style="min-width: 70px">{{ count( $grouped_vehicles_by_type) }}</td>
                                <td >
                                    {{ $project->service . " (" . $type ."):" }} 
                                    {{ implode(', ', $grouped_vehicles_by_type->pluck('eco')->toArray()) }}
                                </td>
                                <td class="text-right" style="min-width: 70px"><span class="text-left">$</span> {{ number_format($price,2) }}</td>
                                <td class="text-right" style="min-width: 70px"><span class="text-left">$</span> {{ number_format($totalForGroup, 2) }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                @endforeach
            @endif



                <tr><td></td><td></td><td></td> <td></td></tr>
                <tr>
                    <td></td>
                    <td></td>
                    <td class="text-right">SUBTOTAL</td>                    
                    <td class="text-right"><span class="text-left">$</span> {{ number_format($grandTotal, 2)}} </td>
                </tr>
        </table>
    </main>

    <br>

    <footer>
        <p class="bold">NOTA: Los precios antes mencionados no incluyen IVA.</p>
        <p>Si tiene alguna duda con respecto a esta cotización, favor de comunicarse al 55 3026 3958 con atención a Jazmín Jasso.</p>
    </footer>

</body>
</html>