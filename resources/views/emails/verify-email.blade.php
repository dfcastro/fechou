<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >
    <title>Confirme seu e-mail | Fechou</title>
</head>

<body
    style="
        margin: 0;
        padding: 0;
        background: #f4f4f5;
        color: #18181b;
        font-family:
            Inter,
            -apple-system,
            BlinkMacSystemFont,
            'Segoe UI',
            Arial,
            sans-serif;
    "
>
    <div
        style="
            display: none;
            max-height: 0;
            overflow: hidden;
            opacity: 0;
        "
    >
        Confirme seu endereço de e-mail para compartilhar propostas no Fechou.
    </div>

    <table
        role="presentation"
        width="100%"
        cellspacing="0"
        cellpadding="0"
        border="0"
        style="
            width: 100%;
            background: #f4f4f5;
        "
    >
        <tr>
            <td
                align="center"
                style="padding: 40px 16px;"
            >
                <table
                    role="presentation"
                    width="100%"
                    cellspacing="0"
                    cellpadding="0"
                    border="0"
                    style="
                        width: 100%;
                        max-width: 600px;
                    "
                >
                    <tr>
                        <td style="padding: 0 0 20px;">
                            <table
                                role="presentation"
                                cellspacing="0"
                                cellpadding="0"
                                border="0"
                            >
                                <tr>
                                    <td
                                        align="center"
                                        valign="middle"
                                        style="
                                            width: 42px;
                                            height: 42px;
                                            border-radius: 11px;
                                            background: #10b981;
                                            color: #ffffff;
                                            font-size: 21px;
                                            font-weight: 800;
                                            line-height: 42px;
                                        "
                                    >
                                        F
                                    </td>

                                    <td style="padding-left: 12px;">
                                        <div
                                            style="
                                                color: #18181b;
                                                font-size: 19px;
                                                font-weight: 800;
                                                line-height: 1.2;
                                            "
                                        >
                                            Fechou
                                        </div>

                                        <div
                                            style="
                                                margin-top: 3px;
                                                color: #71717a;
                                                font-size: 12px;
                                                line-height: 1.4;
                                            "
                                        >
                                            Propostas que vendem
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td
                            style="
                                overflow: hidden;
                                border: 1px solid #e4e4e7;
                                border-radius: 18px;
                                background: #ffffff;
                            "
                        >
                            <table
                                role="presentation"
                                width="100%"
                                cellspacing="0"
                                cellpadding="0"
                                border="0"
                            >
                                <tr>
                                    <td
                                        style="
                                            height: 6px;
                                            background: #10b981;
                                            font-size: 0;
                                            line-height: 0;
                                        "
                                    >
                                        &nbsp;
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding: 38px 40px 18px;">
                                        <div
                                            style="
                                                margin-bottom: 10px;
                                                color: #059669;
                                                font-size: 12px;
                                                font-weight: 800;
                                                letter-spacing: .08em;
                                                text-transform: uppercase;
                                            "
                                        >
                                            Só falta um passo
                                        </div>

                                        <h1
                                            style="
                                                margin: 0;
                                                color: #18181b;
                                                font-size: 28px;
                                                line-height: 1.2;
                                                font-weight: 800;
                                            "
                                        >
                                            Confirme seu e-mail
                                        </h1>

                                        <p
                                            style="
                                                margin: 20px 0 0;
                                                color: #3f3f46;
                                                font-size: 16px;
                                                line-height: 1.7;
                                            "
                                        >
                                            Olá, <strong>{{ $user->name }}</strong>!
                                        </p>

                                        <p
                                            style="
                                                margin: 10px 0 0;
                                                color: #52525b;
                                                font-size: 15px;
                                                line-height: 1.7;
                                            "
                                        >
                                            Sua conta no Fechou já está pronta para uso.
                                            Confirme seu endereço de e-mail para liberar
                                            o compartilhamento de propostas e a contratação
                                            do Fechou Pro.
                                        </p>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding: 10px 40px 28px;">
                                        <table
                                            role="presentation"
                                            cellspacing="0"
                                            cellpadding="0"
                                            border="0"
                                        >
                                            <tr>
                                                <td
                                                    align="center"
                                                    style="
                                                        border-radius: 10px;
                                                        background: #10b981;
                                                    "
                                                >
                                                    <a
                                                        href="{{ $verificationUrl }}"
                                                        style="
                                                            display: inline-block;
                                                            padding: 14px 22px;
                                                            color: #ffffff;
                                                            font-size: 15px;
                                                            font-weight: 800;
                                                            line-height: 1;
                                                            text-decoration: none;
                                                        "
                                                    >
                                                        Confirmar meu e-mail
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding: 0 40px 32px;">
                                        <div
                                            style="
                                                padding: 18px;
                                                border-radius: 12px;
                                                background: #f4f4f5;
                                            "
                                        >
                                            <p
                                                style="
                                                    margin: 0;
                                                    color: #71717a;
                                                    font-size: 12px;
                                                    line-height: 1.6;
                                                "
                                            >
                                                Se o botão não funcionar, copie e cole
                                                este endereço no navegador:
                                            </p>

                                            <p
                                                style="
                                                    margin: 8px 0 0;
                                                    word-break: break-all;
                                                    color: #059669;
                                                    font-size: 12px;
                                                    line-height: 1.6;
                                                "
                                            >
                                                {{ $verificationUrl }}
                                            </p>
                                        </div>
                                    </td>
                                </tr>

                                <tr>
                                    <td
                                        style="
                                            border-top: 1px solid #f4f4f5;
                                            padding: 24px 40px 32px;
                                        "
                                    >
                                        <p
                                            style="
                                                margin: 0;
                                                color: #71717a;
                                                font-size: 12px;
                                                line-height: 1.6;
                                            "
                                        >
                                            Se você não criou uma conta no Fechou,
                                            pode ignorar este e-mail.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td
                            align="center"
                            style="
                                padding: 22px 16px 0;
                                color: #a1a1aa;
                                font-size: 11px;
                                line-height: 1.6;
                            "
                        >
                            Fechou · Propostas profissionais sem complicação
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
