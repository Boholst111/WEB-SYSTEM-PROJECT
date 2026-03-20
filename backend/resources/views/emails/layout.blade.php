<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Diecast Empire')</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .email-container {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 2px solid #e74c3c;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #e74c3c;
        }
        .content {
            padding: 20px 0;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #e74c3c;
            color: #ffffff;
            text-decoration: none;
            border-radius: 4px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 12px;
            color: #666;
        }
        .order-details {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .order-details table {
            width: 100%;
            border-collapse: collapse;
        }
        .order-details td {
            padding: 8px 0;
        }
        .order-details .label {
            font-weight: bold;
            width: 40%;
        }
        h1 {
            color: #333;
            font-size: 24px;
            margin-bottom: 10px;
        }
        h2 {
            color: #555;
            font-size: 18px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <div class="logo">🏎️ DIECAST EMPIRE</div>
        </div>
        
        <div class="content">
            @yield('content')
        </div>
        
        <div class="footer">
            <p>Thank you for shopping with Diecast Empire!</p>
            <p>
                Questions? Contact us at <a href="mailto:support@diecastempire.com">support@diecastempire.com</a>
            </p>
            <p>
                &copy; {{ date('Y') }} Diecast Empire. All rights reserved.
            </p>
        </div>
    </div>
</body>
</html>
