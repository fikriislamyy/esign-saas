<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Organization Invitation</title>
</head>

<body
    style="
        margin: 0;
        padding: 0;
        background-color: #f5f7fb;
        font-family:
            -apple-system,
            BlinkMacSystemFont,
            'Segoe UI',
            Roboto,
            Helvetica,
            Arial,
            sans-serif;
        color: #111827;
    "
>
    <table
        role="presentation"
        width="100%"
        cellpadding="0"
        cellspacing="0"
        border="0"
        style="
            width: 100%;
            margin: 0;
            padding: 0;
            background-color: #f5f7fb;
        "
    >
        <tr>
            <td
                align="center"
                style="padding: 48px 20px;"
            >

                <!-- Main container -->
                <table
                    role="presentation"
                    width="100%"
                    cellpadding="0"
                    cellspacing="0"
                    border="0"
                    style="
                        max-width: 600px;
                        width: 100%;
                        background-color: #ffffff;
                        border: 1px solid #e5e7eb;
                        border-radius: 16px;
                        overflow: hidden;
                    "
                >

                    <!-- Header -->
                    <tr>
                        <td
                            style="
                                padding: 28px 32px;
                                background-color: #111827;
                            "
                        >
                            <table
                                role="presentation"
                                width="100%"
                                cellpadding="0"
                                cellspacing="0"
                                border="0"
                            >
                                <tr>
                                    <td>

                                        <div
                                            style="
                                                font-size: 22px;
                                                line-height: 1.2;
                                                font-weight: 700;
                                                color: #ffffff;
                                                letter-spacing: -0.02em;
                                            "
                                        >
                                            ESign
                                        </div>

                                        <div
                                            style="
                                                margin-top: 5px;
                                                font-size: 13px;
                                                line-height: 1.4;
                                                color: #9ca3af;
                                            "
                                        >
                                            Secure Digital Signing
                                        </div>

                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td
                            style="
                                padding: 40px 40px 36px;
                            "
                        >

                            <!-- Eyebrow -->
                            <div
                                style="
                                    margin-bottom: 14px;
                                    font-size: 12px;
                                    line-height: 1.5;
                                    font-weight: 700;
                                    letter-spacing: 0.08em;
                                    text-transform: uppercase;
                                    color: #6b7280;
                                "
                            >
                                Organization Invitation
                            </div>

                            <!-- Heading -->
                            <h1
                                style="
                                    margin: 0;
                                    font-size: 30px;
                                    line-height: 1.25;
                                    font-weight: 700;
                                    letter-spacing: -0.03em;
                                    color: #111827;
                                "
                            >
                                You're invited to join
                                {{ $invitation->organization->name }}
                            </h1>

                            <!-- Description -->
                            <p
                                style="
                                    margin: 18px 0 0;
                                    font-size: 16px;
                                    line-height: 1.7;
                                    color: #6b7280;
                                "
                            >
                                You have been invited to join
                                <strong style="color: #111827;">
                                    {{ $invitation->organization->name }}
                                </strong>
                                on ESign.
                                Accept the invitation below to access
                                the organization workspace.
                            </p>

                            <!-- Role card -->
                            <table
                                role="presentation"
                                width="100%"
                                cellpadding="0"
                                cellspacing="0"
                                border="0"
                                style="
                                    margin-top: 28px;
                                    background-color: #f9fafb;
                                    border: 1px solid #e5e7eb;
                                    border-radius: 12px;
                                "
                            >
                                <tr>
                                    <td
                                        style="
                                            padding: 18px 20px;
                                        "
                                    >

                                        <div
                                            style="
                                                font-size: 12px;
                                                line-height: 1.5;
                                                font-weight: 600;
                                                color: #6b7280;
                                                text-transform: uppercase;
                                                letter-spacing: 0.05em;
                                            "
                                        >
                                            Your role
                                        </div>

                                        <div
                                            style="
                                                margin-top: 6px;
                                                font-size: 16px;
                                                line-height: 1.5;
                                                font-weight: 600;
                                                color: #111827;
                                            "
                                        >
                                            {{ ucfirst($invitation->role) }}
                                        </div>

                                    </td>
                                </tr>
                            </table>

                            <!-- CTA -->
                            <div style="margin-top: 32px;">
                                <a
                                    href="{{ route('invitations.accept', $invitation->token) }}"
                                    target="_blank"
                                    style="
                                        display: inline-block;
                                        padding: 14px 24px;
                                        background-color: #111827;
                                        border-radius: 10px;
                                        color: #ffffff;
                                        font-size: 15px;
                                        line-height: 1.4;
                                        font-weight: 600;
                                        text-decoration: none;
                                    "
                                >
                                    Accept Invitation
                                </a>
                            </div>

                            <!-- Fallback link -->
                            <p
                                style="
                                    margin: 24px 0 0;
                                    font-size: 13px;
                                    line-height: 1.7;
                                    color: #9ca3af;
                                "
                            >
                                If the button above does not work, copy
                                and paste the following link into your
                                browser:
                            </p>

                            <p
                                style="
                                    margin: 8px 0 0;
                                    font-size: 12px;
                                    line-height: 1.7;
                                    word-break: break-all;
                                "
                            >
                                <a
                                    href="{{ route('invitations.accept', $invitation->token) }}"
                                    target="_blank"
                                    style="
                                        color: #4b5563;
                                        text-decoration: underline;
                                    "
                                >
                                    {{ route('invitations.accept', $invitation->token) }}
                                </a>
                            </p>

                            <!-- Security note -->
                            <table
                                role="presentation"
                                width="100%"
                                cellpadding="0"
                                cellspacing="0"
                                border="0"
                                style="
                                    margin-top: 32px;
                                    border-top: 1px solid #e5e7eb;
                                "
                            >
                                <tr>
                                    <td
                                        style="
                                            padding-top: 24px;
                                        "
                                    >
                                        <p
                                            style="
                                                margin: 0;
                                                font-size: 13px;
                                                line-height: 1.7;
                                                color: #6b7280;
                                            "
                                        >
                                            If you were not expecting this
                                            invitation, you can safely
                                            ignore this email.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td
                            style="
                                padding: 24px 40px;
                                background-color: #f9fafb;
                                border-top: 1px solid #e5e7eb;
                            "
                        >

                            <p
                                style="
                                    margin: 0;
                                    font-size: 12px;
                                    line-height: 1.6;
                                    color: #9ca3af;
                                    text-align: center;
                                "
                            >
                                This email was sent by ESign.
                            </p>

                            <p
                                style="
                                    margin: 6px 0 0;
                                    font-size: 12px;
                                    line-height: 1.6;
                                    color: #9ca3af;
                                    text-align: center;
                                "
                            >
                                Secure digital signing for modern teams.
                            </p>

                        </td>
                    </tr>

                </table>

                <!-- Outside footer -->
                <p
                    style="
                        max-width: 600px;
                        margin: 20px auto 0;
                        padding: 0 20px;
                        font-size: 11px;
                        line-height: 1.6;
                        color: #9ca3af;
                        text-align: center;
                    "
                >
                    © {{ date('Y') }} ESign. All rights reserved.
                </p>

            </td>
        </tr>
    </table>
</body>
</html>
