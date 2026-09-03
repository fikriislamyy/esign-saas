<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Signature Request</title>
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
        Signature Request
    </h2>

    <p>
        Hello {{ $signer->name }},
    </p>

    <p>
        You have been requested to review and sign the following document:
    </p>

    <p>
        <strong>
            {{ $signer->document->name }}
        </strong>
    </p>

    <p>
        Click the button below to open the signing page.
    </p>

    <p style="margin: 30px 0;">
        <a
            href="{{ route('signing.show', $signer->token) }}"
            style="
                display: inline-block;
                padding: 12px 24px;
                background-color: #18181b;
                color: #ffffff;
                text-decoration: none;
                border-radius: 8px;
                font-weight: 600;
            "
        >
            Sign Document
        </a>
    </p>

    <!-- OTP -->

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
            Your verification code
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
            Enter this code on the signing page to continue.
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
        If you did not expect this signature request, you can safely ignore
        this email.
    </p>

</div>

</body>
</html>
