<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Correo Servicios Neón Gonz</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f6f6f6;
            margin: 0;
            padding: 0;
        }

        .email-container {
            max-width: 600px;
            margin: 30px auto;
            background-color: #ffffff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            color: #333333;
        }

        p {
            font-size: 16px;
            line-height: 1.6;
        }

        .signature {
            margin-top: 40px;
            border-top: 1px solid #ddd;
            padding-top: 20px;
            font-size: 14px;
            color: #555;
        }

        .brand {
            color: #720000; /* Verde de tu paleta */
            font-weight: bold;
            font-size: 16px;
        }

        .contact-info {
            margin-top: 5px;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="email-container">
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
    </div>
</body>
</html>

