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
            margin: 0;
            padding: 22mm;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: #111827;
        }

        .title {
            margin: 0;
            font-size: 38px;
            letter-spacing: 2px;
            font-weight: 700;
            color: #111827;
        }

        .tag {
            display: inline-block;
            margin-top: 8px;
            border: 1px solid #111827;
            border-radius: 7px;
            padding: 5px 12px;
            font-size: 13px;
            font-weight: 700;
        }

        .invoice-no {
            margin: 10px 0 0;
            font-size: 18px;
            font-weight: 700;
        }

        .muted {
            color: #334155;
            font-size: 12px;
            line-height: 1.45;
        }

        .section {
            margin-top: 18px;
        }

        .section-title {
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: 0.4px;
            color: #0f172a;
        }

        .small {
            margin: 0;
            color: #334155;
        }

        .two-col {
            display: table;
            width: 100%;
            table-layout: fixed;
        }

        .col {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }

        .col.right {
            text-align: right;
        }

        .note {
            border-top: 1px solid #cbd5e1;
            border-bottom: 1px solid #cbd5e1;
            padding: 8px 0;
            margin-top: 2px;
        }

        .grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 8px;
            font-size: 12px;
        }

        .grid th,
        .grid td {
            border: 1px solid #d1d5db;
            padding: 10px 8px;
            vertical-align: top;
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
            margin-top: 12px;
            font-weight: 700;
            font-size: 13px;
        }

        .grand {
            margin-top: 6px;
            font-size: 28px;
            font-weight: 800;
            letter-spacing: 0.3px;
        }

        .divider {
            margin-top: 24px;
            border-top: 1px solid #cbd5e1;
        }

        .footer {
            margin-top: 120px;
            color: #475569;
            font-size: 11px;
        }

        .footer .brand {
            margin: 0;
            font-size: 30px;
            letter-spacing: 1px;
            font-weight: 700;
            color: #111827;
        }

        .signature {
            margin-top: 20px;
            width: 40%;
            font-size: 12px;
            color: #334155;
        }
    </style>
</head>

<body>
    <h1 class="title">FREDDIE</h1>
    <p class="muted" style="margin: 4px 0 0;">Jl. Dr. Ir. H. Soekarno.30-32, Apartemen Puncak Dharmahusada Ruko No.9H</p>

    <div class="two-col" style="margin-top: 12px;">
        <div class="col">
            <span class="tag">INVOICE</span>
            <p class="invoice-no">No. {{ $invoiceNo }}</p>
        </div>
        <div class="col right muted">{{ $issuedAt }}</div>
    </div>

    <div class="section">
        <div class="note">
            <div class="section-title">PAYMENT NOTE</div>
            <p class="small">Endorse by {{ $maker }}</p>
        </div>
    </div>

    <div class="section">
        <div class="section-title">CUSTOMER NAME</div>
        <p class="small">{{ $customerName }}</p>
        <p class="small">{{ $customerAddress ?: '—' }}</p>
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
        <p class="small">—</p>
    </div>

    <div class="section">
        <div class="section-title">SYARAT DAN KETENTUAN PEMBAYARAN</div>
        <p class="small">1. Silakan kirim pembayaran setelah menerima faktur ini.</p>
        <p class="small">2. Tidak dapat melakukan pembatalan setelah pembayaran dilakukan.</p>
        <p class="small">Saya telah setuju dengan syarat dan ketentuan yang berlaku.</p>
        <p class="small">Terimakasih telah bekerjasama dengan kami.</p>
    </div>

    <div class="section">
        <div class="section-title">BANK ACCOUNT</div>
        <p class="small">{{ $bankAccount }} {{ $bankName }} {{ $bankOwner }}</p>
    </div>

    <div class="divider"></div>

    <div class="footer">
        <p class="brand">Freddie</p>
        <p class="small">Jl. Dr. Ir. H. Soekarno.30-32,</p>
        <p class="small">Apartemen Puncak Dharmahusada Ruko No.9H</p>
    </div>

    <p class="signature">{{ $maker }}</p>
</body>

</html>
