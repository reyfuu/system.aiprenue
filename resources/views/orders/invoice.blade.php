<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoiceNo }}</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #0f172a;
            margin: 0;
            padding: 28px 32px;
        }

        .title {
            margin: 0;
            font-size: 38px;
            letter-spacing: 2px;
            font-weight: 700;
            color: #0f172a;
        }

        .badge {
            display: inline-block;
            border: 1px solid #0f172a;
            border-radius: 8px;
            font-weight: 700;
            padding: 6px 12px;
            margin-top: 6px;
        }

        .invoice-no {
            font-size: 18px;
            font-weight: 700;
            margin-top: 14px;
            margin-bottom: 0;
        }

        .header {
            margin-top: 8px;
            margin-bottom: 8px;
        }

        .section {
            margin-top: 16px;
            display: block;
        }

        .section-title {
            font-size: 13px;
            letter-spacing: 0.4px;
            color: #334155;
            margin-bottom: 8px;
            font-weight: 700;
        }

        .small {
            font-size: 12px;
            color: #334155;
        }

        .row {
            display: table;
            width: 100%;
            margin-bottom: 6px;
        }

        .label {
            display: table-cell;
            width: 120px;
            font-size: 12px;
            color: #334155;
            vertical-align: top;
        }

        .value {
            display: table-cell;
            font-size: 12px;
            color: #0f172a;
            white-space: pre-line;
        }

        .grid {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 12px;
        }

        .grid th,
        .grid td {
            border: 1px solid #d1d5db;
            padding: 10px 8px;
            text-align: left;
        }

        .grid th {
            background: #f8fafc;
            font-weight: 700;
        }

        .text-right {
            text-align: right;
        }

        .total-box {
            margin-top: 14px;
            font-size: 13px;
            font-weight: 700;
        }

        .grand {
            margin-top: 6px;
            font-size: 26px;
            font-weight: 800;
            letter-spacing: 0.3px;
        }

        .mt-20 {
            margin-top: 20px;
        }

        .divider {
            border-top: 1px solid #cbd5e1;
            margin: 14px 0;
        }

        .footer {
            margin-top: 120px;
            font-size: 11px;
            color: #475569;
        }

        .footer .title {
            font-size: 30px;
            letter-spacing: 1px;
        }

        .signature {
            margin-top: 24px;
            font-size: 12px;
            color: #334155;
            width: 42%;
        }
    </style>
</head>

<body>
    <h1 class="title">FREDDIE</h1>

    <div class="header">
        <div class="badge">INVOICE</div>
        <p class="invoice-no">No. {{ $invoiceNo }}</p>
    </div>

    <div class="section">
        <div class="section-title">PAYMENT NOTE</div>
        <p class="small">Endorse {{ $maker }}</p>
        <p class="small">{{ $issuedAt }}</p>
    </div>

    <div class="section">
        <div class="section-title">CUSTOMER NAME</div>
        <div class="small">{{ $customerName }}</div>
        <div class="small">{{ $customerAddress ?: '—' }}</div>
    </div>

    <div class="section">
        <table class="grid">
            <thead>
                <tr>
                    <th style="width: 62%;">DESCRIPTION</th>
                    <th style="width: 12%;">QTY</th>
                    <th style="width: 26%;">TOTAL</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($items as $item)
                    <tr>
                        <td>{{ $item['description'] }}</td>
                        <td class="text-right">{{ $item['qty'] }}</td>
                        <td class="text-right">Rp {{ number_format((float) $item['total'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

    <p class="total-box">TOTAL PEMBAYARAN</p>
    <p class="grand">Rp {{ number_format($subtotal, 0, ',', '.') }}</p>

    <div class="section">
        <div class="section-title">LAMPIRAN NPWP :</div>
    </div>

    <div class="section">
        <div class="section-title">SYARAT DAN KETENTUAN PEMBAYARAN</div>
        <div class="small">1. Silakan kirim pembayaran setelah menerima faktur ini.</div>
        <div class="small">2. Tidak dapat melakukan pembatalan setelah pembayaran dilakukan.</div>
        <div class="small">Saya telah setuju dengan syarat dan ketentuan yang berlaku.</div>
        <div class="small">Terimakasih telah bekerjasama dengan kami.</div>
    </div>

    <div class="section mt-20">
        <div class="section-title">BANK ACCOUNT</div>
        <div class="small">{{ $bankAccount }} {{ $bankName }} {{ $bankOwner }}</div>
    </div>

    <div class="divider"></div>

    <div class="footer">
        <p class="title" style="font-size:20px;">Freddie</p>
        <p class="small">Jl. Dr. Ir. H. Soekarno.30-32,</p>
        <p class="small">Apartemen Puncak Dharmahusada Ruko No.9H</p>
    </div>

    <p class="small signature">{{ $maker }}</p>
</body>

</html>
