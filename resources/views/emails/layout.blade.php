<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
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
            color: #720000;
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
        @yield('content')
    </div>
</body>
</html>
