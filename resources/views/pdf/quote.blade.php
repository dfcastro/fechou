<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <title>
        Orçamento #{{ str_pad($quote->number, 4, '0', STR_PAD_LEFT) }}
    </title>

    <style>
        @page {
            margin: 25px 34px 32px 34px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;

            font-family: DejaVu Sans, sans-serif;

            font-size: 11px;
            line-height: 1.45;

            color: #27272a;
            background: #ffffff;
        }

        table {
            border-collapse: collapse;
        }

        /*
        |--------------------------------------------------------------------------
        | Cabeçalho
        |--------------------------------------------------------------------------
        */

        .header {
            width: 100%;

            border-bottom: 2px solid #059669;

            padding-bottom: 14px;
            margin-bottom: 18px;
        }

        .header td {
            vertical-align: top;
        }

        .brand-box {
            width: 42px;
            height: 42px;

            background: #059669;

            color: #ffffff;

            font-size: 20px;
            font-weight: bold;

            text-align: center;
            vertical-align: middle;

            border-radius: 7px;
        }

        .business {
            padding-left: 12px;
        }

        .business-name {
            margin: 0;

            font-size: 17px;
            font-weight: bold;

            color: #18181b;
        }

        .business-details {
            margin-top: 3px;

            font-size: 9.5px;
            line-height: 1.55;

            color: #71717a;
        }

        .quote-number {
            text-align: right;

            padding-left: 18px;
        }

        .label-uppercase {
            font-size: 8px;
            font-weight: bold;

            text-transform: uppercase;
            letter-spacing: 0.8px;

            color: #71717a;
        }

        .number {
            margin-top: 3px;

            font-size: 18px;
            font-weight: bold;

            color: #18181b;
        }

        /*
        |--------------------------------------------------------------------------
        | Introdução
        |--------------------------------------------------------------------------
        */

        .proposal-label {
            margin-bottom: 4px;

            color: #059669;

            font-size: 8.5px;
            font-weight: bold;

            text-transform: uppercase;
            letter-spacing: 0.7px;
        }

        .client-name {
            margin: 0;

            font-size: 20px;
            font-weight: bold;

            color: #18181b;
        }

        .quote-title {
            margin-top: 12px;
            margin-bottom: 0;

            font-size: 15px;
            font-weight: bold;

            color: #27272a;
        }

        .description {
            margin-top: 4px;

            line-height: 1.6;

            color: #52525b;

            white-space: pre-line;
            word-wrap: break-word;
        }

        /*
        |--------------------------------------------------------------------------
        | Informações
        |--------------------------------------------------------------------------
        */

        .info-grid {
            width: 100%;

            margin-top: 14px;
            margin-bottom: 18px;

            border-top: 1px solid #e4e4e7;
            border-bottom: 1px solid #e4e4e7;
        }

        .info-grid td {
            width: 33.33%;

            padding: 8px 5px;
        }

        .info-grid td:first-child {
            padding-left: 0;
        }

        .info-label {
            display: block;

            margin-bottom: 3px;

            font-size: 8px;

            color: #a1a1aa;
        }

        .info-value {
            font-size: 10px;
            font-weight: bold;

            color: #3f3f46;
        }

        /*
        |--------------------------------------------------------------------------
        | Seções
        |--------------------------------------------------------------------------
        */

        .section-title {
            margin: 0 0 8px 0;

            font-size: 12px;
            font-weight: bold;

            color: #18181b;
        }

        /*
        |--------------------------------------------------------------------------
        | Itens
        |--------------------------------------------------------------------------
        */

        .items {
            width: 100%;

            margin-top: 6px;
        }

        .items thead {
            background: #eeeeef;
        }

        .items th {
            padding: 8px 8px;

            border-bottom: 1px solid #d4d4d8;

            font-size: 8px;
            font-weight: bold;

            text-transform: uppercase;
            letter-spacing: 0.4px;

            color: #71717a;

            text-align: left;
        }

        .items td {
            padding: 7px 8px;

            border-bottom: 1px solid #eeeeef;

            vertical-align: middle;
        }

        .items tr {
            page-break-inside: avoid;
        }

        .items .description-column {
            width: 43%;
        }

        .items .quantity-column {
            width: 10%;

            text-align: center;
        }

        .items .unit-column {
            width: 12%;

            text-align: center;
        }

        .items .price-column {
            width: 17%;

            text-align: right;
        }

        .items .total-column {
            width: 18%;

            text-align: right;
        }

        .item-description {
            font-weight: bold;

            word-wrap: break-word;

            color: #27272a;
        }

        .item-type {
            display: inline-block;

            margin-left: 6px;

            padding: 2px 5px;

            border-radius: 4px;

            background: #f4f4f5;

            font-size: 7px;
            font-weight: bold;

            line-height: 1.2;
            vertical-align: middle;

            text-transform: uppercase;

            color: #71717a;
        }

        /*
        |--------------------------------------------------------------------------
        | Totais
        |--------------------------------------------------------------------------
        */

        .totals-container {
            width: 100%;

            margin-top: 12px;

            page-break-inside: avoid;
        }

        .totals-spacer {
            width: 50%;
        }

        .totals {
            width: 50%;
        }

        .totals td {
            padding: 5px 0;
        }

        .totals-label {
            color: #71717a;
        }

        .totals-value {
            text-align: right;

            font-weight: bold;

            color: #3f3f46;
        }

        .discount {
            color: #dc2626;
        }

        .grand-total td {
            padding-top: 10px;

            border-top: 1px solid #d4d4d8;
        }

        .grand-total-label {
            font-size: 11px;
            font-weight: bold;

            color: #3f3f46;
        }

        .grand-total-value {
            text-align: right;

            font-size: 20px;
            font-weight: bold;

            white-space: nowrap;

            color: #059669;
        }

        /*
        |--------------------------------------------------------------------------
        | Observações
        |--------------------------------------------------------------------------
        */

        .notes {
            margin-top: 14px;

            padding: 13px 14px;

            page-break-inside: avoid;

            border: 1px solid #e4e4e7;
            border-radius: 6px;

            background: #fafafa;
        }

        .notes-title {
            margin-bottom: 6px;

            font-size: 10px;
            font-weight: bold;

            color: #3f3f46;
        }

        .notes-content {
            margin: 0;

            line-height: 1.6;

            color: #52525b;

            white-space: pre-line;
        }

        /*
        |--------------------------------------------------------------------------
        | Validade
        |--------------------------------------------------------------------------
        */

        .validity {
            margin-top: 10px;

            padding: 8px 13px;

            page-break-inside: avoid;

            border-left: 3px solid #059669;

            background: #f0fdf4;

            color: #3f3f46;
        }

        .validity strong {
            color: #047857;
        }

        /*
        |--------------------------------------------------------------------------
        | Rodapé
        |--------------------------------------------------------------------------
        */

        .footer {
            margin-top: 10px;

            padding-top: 7px;

            border-top: 1px solid #e4e4e7;

            text-align: center;

            font-size: 7.5px;
            line-height: 1.25;

            page-break-inside: avoid;

            color: #a1a1aa;
        }

        .footer-brand {
            font-weight: bold;

            color: #71717a;
        }
    </style>

</head>


<body>

    {{-- ========================================================= --}}
    {{-- CABEÇALHO --}}
    {{-- ========================================================= --}}

    <table class="header">

        <tr>

            <td style="width: 48px;">

                {{-- O controller só envia logoDataUri quando o plano possui CUSTOM_BRANDING. --}}
                @if ($logoDataUri)

                    <table>
                        <tr>
                            <td style="
                        width: 80px;
                        height: 48px;
                        vertical-align: middle;
                    ">
                                <img src="{{ $logoDataUri }}" style="
                            max-width: 75px;
                            max-height: 45px;
                        " alt="">
                            </td>
                        </tr>
                    </table>

                @else

                                <table>
                                    <tr>
                                        <td class="brand-box">
                                            {{ mb_strtoupper(
                        mb_substr(
                            $quote->business->name,
                            0,
                            1
                        )
                    ) }}
                                        </td>
                                    </tr>
                                </table>

                @endif

            </td>


            <td class="business">

                <p class="business-name">
                    {{ $quote->business->name }}
                </p>


                <div class="business-details">

                    @if ($quote->business->document)
                        {{ $quote->business->document }}
                    @endif


                    @if ($quote->business->phone || $quote->business->whatsapp)

                                    @if ($quote->business->document)
                                        &nbsp;&bull;&nbsp;
                                    @endif

                                    {{ $quote->business->whatsapp
                        ?: $quote->business->phone
                                        }}

                    @endif


                    @if ($quote->business->email)

                        @if (
                                $quote->business->document
                                || $quote->business->phone
                                || $quote->business->whatsapp
                            )
                            <br>
                        @endif

                        {{ $quote->business->email }}

                    @endif


                    @if (
                            $quote->business->address
                            || $quote->business->city
                        )

                        <br>

                        @if ($quote->business->address)
                            {{ $quote->business->address }}
                        @endif


                        @if ($quote->business->city)

                            @if ($quote->business->address)
                                -
                            @endif

                            {{ $quote->business->city }}

                            @if ($quote->business->state)
                                /{{ $quote->business->state }}
                            @endif

                        @endif

                    @endif

                </div>

            </td>


            <td class="quote-number" style="width: 145px;">

                <div class="label-uppercase">
                    Orçamento
                </div>

                <div class="number">
                    #{{ str_pad(
    $quote->number,
    4,
    '0',
    STR_PAD_LEFT
) }}
                </div>

            </td>

        </tr>

    </table>


    {{-- ========================================================= --}}
    {{-- CLIENTE --}}
    {{-- ========================================================= --}}

    <div class="proposal-label">
        Proposta comercial para
    </div>


    <h1 class="client-name">
        {{ $quote->client->name }}
    </h1>


    {{-- Dados do cliente --}}

    @if (
            $quote->client->document
            || $quote->client->phone
            || $quote->client->whatsapp
            || $quote->client->email
        )

        <div style="
                margin-top: 4px;
                font-size: 9px;
                color: #71717a;
            ">

            @if ($quote->client->document)

                CPF/CNPJ:
                {{ $quote->client->document }}

            @endif


            @if (
                    $quote->client->whatsapp
                    || $quote->client->phone
                )

                @if ($quote->client->document)
                    &nbsp;&bull;&nbsp;
                @endif

                {{ $quote->client->whatsapp
                    ?: $quote->client->phone
                        }}

            @endif


            @if ($quote->client->email)

                @if (
                        $quote->client->document
                        || $quote->client->whatsapp
                        || $quote->client->phone
                    )
                    &nbsp;&bull;&nbsp;
                @endif

                {{ $quote->client->email }}

            @endif

        </div>

    @endif


    <h2 class="quote-title">
        {{ $quote->title }}
    </h2>


    @if ($quote->description)

        <div class="description">
            {{ $quote->description }}
        </div>

    @endif


    {{-- ========================================================= --}}
    {{-- DATAS --}}
    {{-- ========================================================= --}}

    <table class="info-grid">

        <tr>

            <td>

                <span class="info-label">
                    Emitido em
                </span>

                <span class="info-value">
                    {{ $quote->created_at->format('d/m/Y') }}
                </span>

            </td>


            <td>

                <span class="info-label">
                    Validade
                </span>

                <span class="info-value">
                    {{ $quote->valid_until?->format('d/m/Y')
    ?? 'Sem prazo definido'
                    }}
                </span>

            </td>


            <td>

                <span class="info-label">
                    Quantidade de itens
                </span>

                <span class="info-value">

                    {{ $quote->items->count() }}

                    {{ $quote->items->count() === 1
    ? 'item'
    : 'itens'
                    }}

                </span>

            </td>

        </tr>

    </table>


    {{-- ========================================================= --}}
    {{-- ITENS --}}
    {{-- ========================================================= --}}

    <h3 class="section-title">
        Itens da proposta
    </h3>


    <table class="items">

        <thead>

            <tr>

                <th class="description-column">
                    Descrição
                </th>

                <th class="quantity-column">
                    Qtd.
                </th>

                <th class="unit-column">
                    Unidade
                </th>

                <th class="price-column">
                    Unitário
                </th>

                <th class="total-column">
                    Total
                </th>

            </tr>

        </thead>


        <tbody>

            @foreach ($quote->items as $item)

                        <tr>

                            <td class="description-column">

                                {{-- ITEM TYPE INLINE PDF --}}
                                <div class="item-description">
                                    {{ $item->description }}

                                    <span class="item-type">
                                        {{ match ($item->type) {
                    'service' => 'Serviço',
                    'material' => 'Material',
                    default => 'Outro',
                } }}
                                    </span>
                                </div>

                            </td>


                            <td class="quantity-column">

                                {{ rtrim(
                    rtrim(
                        number_format(
                            (float) $item->quantity,
                            3,
                            ',',
                            '.'
                        ),
                        '0'
                    ),
                    ','
                ) }}

                            </td>


                            <td class="unit-column">
                                {{ $item->unit }}
                            </td>


                            <td class="price-column">

                                R$ {{ number_format(
                    (float) $item->unit_price,
                    2,
                    ',',
                    '.'
                ) }}

                            </td>


                            <td class="total-column">

                                <strong>

                                    R$ {{ number_format(
                    (float) $item->total,
                    2,
                    ',',
                    '.'
                ) }}

                                </strong>

                            </td>

                        </tr>

            @endforeach

        </tbody>

    </table>


    {{-- ========================================================= --}}
    {{-- TOTAIS --}}
    {{-- ========================================================= --}}

    <table class="totals-container">

        <tr>

            <td class="totals-spacer"></td>


            <td>

                <table class="totals">

                    <tr>

                        <td class="totals-label">
                            Subtotal
                        </td>

                        <td class="totals-value">

                            R$ {{ number_format(
    (float) $quote->subtotal,
    2,
    ',',
    '.'
) }}

                        </td>

                    </tr>


                    @if ((float) $quote->discount > 0)

                                        <tr>

                                            <td class="totals-label">
                                                Desconto
                                            </td>

                                            <td class="totals-value discount">

                                                - R$ {{ number_format(
                            (float) $quote->discount,
                            2,
                            ',',
                            '.'
                        ) }}

                                            </td>

                                        </tr>

                    @endif


                    <tr class="grand-total">

                        <td class="grand-total-label">
                            Total
                        </td>

                        <td class="grand-total-value">

                            R$ {{ number_format(
    (float) $quote->total,
    2,
    ',',
    '.'
) }}

                        </td>

                    </tr>

                </table>

            </td>

        </tr>

    </table>


    {{-- ========================================================= --}}
    {{-- OBSERVAÇÕES --}}
    {{-- ========================================================= --}}

    @if ($quote->notes)

        <div class="notes">

            <div class="notes-title">
                Condições e observações
            </div>


            <p class="notes-content">
                {{ $quote->notes }}
            </p>

        </div>

    @endif


    {{-- ========================================================= --}}
    {{-- VALIDADE --}}
    {{-- ========================================================= --}}

    @if ($quote->valid_until)

        <div class="validity">

            Esta proposta é válida até

            <strong>
                {{ $quote->valid_until->format('d/m/Y') }}
            </strong>

        </div>

    @endif


    {{-- ========================================================= --}}
    {{-- RODAPÉ --}}
    {{-- ========================================================= --}}

    <div class="footer">

        <span class="footer-brand">
            {{ $quote->business->name }}
        </span>

        &nbsp;&bull;&nbsp;

        Documento gerado pelo Fechou

    </div>

</body>

</html>