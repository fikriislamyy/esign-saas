<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Confirm it's you</title>
</head>

<body
    style="
        margin: 0;
        padding: 40px 20px;
        background-color: #f4f4f5;
        font-family: Arial, Helvetica, sans-serif;
        color: #18181b;
    "
>

<div
    style="
        max-width: 600px;
        margin: 0 auto;
        background-color: #ffffff;
        border-radius: 12px;
        padding: 32px;
        box-sizing: border-box;
    "
>

    <h2 style="margin-top: 0;">
        Confirm it's you
    </h2>

    <p>
        Hello {{ $user->name }},
    </p>

    <p>
        Someone just signed in to EZSign with your password. Enter the code below to finish signing in. It expires in 10 minutes.
    </p>

    <div
        style="
            margin-top: 30px;
            padding: 24px;
            background-color: #f4f4f5;
            border-radius: 10px;
            text-align: center;
        "
    >

        <p
            style="
                margin: 0 0 10px;
                font-size: 14px;
                color: #71717a;
            "
        >
            Your sign-in code
        </p>

        <div
            style="
                font-size: 32px;
                font-weight: 700;
                letter-spacing: 8px;
            "
        >
            {{ $otp }}
        </div>

        <p
            style="
                margin: 12px 0 0;
                font-size: 13px;
                color: #71717a;
            "
        >
            Enter this code on the sign-in page to continue.
        </p>

    </div>

    <p
        style="
            margin-top: 30px;
            font-size: 13px;
            color: #71717a;
        "
    >
        This verification code will expire in 10 minutes.
    </p>

    <p
        style="
            font-size: 13px;
            color: #71717a;
        "
    >
        If this wasn't you, change your password now — someone else knows it.
    </p>

</div>

</body>
</html>
