<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Deposit Refund Slip - {{ $folio->transaction_id }}</title>
    <style>
        @page {
            size: 80mm auto;
            margin: 0;
        }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 11px;
            line-height: 1.35;
            color: #000;
            background-color: #f5f5f0;
            margin: 0;
            padding: 16px 8px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .slip-container {
            width: 76mm;
            max-width: 100%;
            background-color: #fff;
            border: 1px solid #ddd;
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
            padding: 14px 12px;
            box-sizing: border-box;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .font-bold { font-weight: bold; }
        .divider {
            border-top: 1px dashed #000;
            margin: 8px 0;
        }
        .double-divider {
            border-top: 2px solid #000;
            margin: 8px 0;
        }
        .row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
        }
        .banner {
            border: 1px solid #000;
            padding: 4px;
            text-align: center;
            font-weight: bold;
            font-size: 12px;
            margin: 6px 0;
            text-transform: uppercase;
        }
        .sig-block {
            margin-top: 24px;
            text-align: center;
        }
        .sig-line {
            border-top: 1px solid #000;
            margin-top: 28px;
            padding-top: 4px;
            font-size: 10px;
            font-weight: bold;
        }
        .no-print {
            margin-bottom: 16px;
            text-align: center;
        }
        .btn-print {
            background-color: #421A2B;
            color: #fff;
            padding: 8px 18px;
            font-size: 12px;
            font-weight: bold;
            border: none;
            cursor: pointer;
            border-radius: 4px;
        }
        @media print {
            body { background-color: #fff; padding: 0; }
            .slip-container { border: none; box-shadow: none; padding: 4px; width: 100%; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <button class="btn-print" onclick="window.print()">🖨️ Print Deposit Refund Slip</button>
        <button class="btn-print" style="background:#4b5563; margin-left:6px;" onclick="window.close()">Close Window</button>
    </div>

    <div class="slip-container" id="printable-deposit-refund">
        <!-- Header -->
        <div class="text-center">
            <div class="font-bold" style="font-size: 14px; letter-spacing: 1px;">SEDONA COURT</div>
            <div style="font-size: 9px;">TRAVELLER'S INN & LODGING</div>
            <div style="font-size: 9px;">Doña Remedios Trinidad Hwy, San Rafael, Bulacan</div>
            <div style="font-size: 9px;">TEL: +63 (0939) 905-2816</div>
        </div>

        <div class="double-divider"></div>

        <div class="banner">SECURITY DEPOSIT REFUND / RESOLUTION</div>

        <!-- Info -->
        <div style="margin-top: 6px;">
            <div class="row">
                <span>Slip Ref:</span>
                <span class="font-bold">DEP-REF-{{ $folio->transaction_id }}</span>
            </div>
            <div class="row">
                <span>Room Number:</span>
                <span class="font-bold">ROOM {{ $folio->room->number ?? 'N/A' }}</span>
            </div>
            <div class="row">
                <span>Guest Name:</span>
                <span>{{ $folio->guest->name ?? 'Walk-In Guest' }}</span>
            </div>
            <div class="row">
                <span>Date & Time:</span>
                <span>{{ now()->format('m/d/Y h:i A') }}</span>
            </div>
            <div class="row">
                <span>Attending Cashier:</span>
                <span>{{ $folio->cashier->name ?? 'Frontdesk Staff' }} // FD-01</span>
            </div>
        </div>

        <div class="divider"></div>

        <!-- Financial Resolution -->
        <div>
            <div class="row">
                <span>Original Deposit Held:</span>
                <span>₱{{ number_format($depositAmount, 2) }}</span>
            </div>
            <div class="row">
                <span>Deductions / Damages / POS:</span>
                <span>₱{{ number_format($deductions, 2) }}</span>
            </div>
            <div class="double-divider"></div>
            <div class="row font-bold" style="font-size: 13px;">
                <span>NET CASH REFUNDED:</span>
                <span>₱{{ number_format($refundAmount, 2) }}</span>
            </div>
            <div class="divider"></div>
            <div class="row">
                <span>Resolution Status:</span>
                <span class="font-bold uppercase">REFUNDED &amp; CLEARED</span>
            </div>
        </div>

        <div class="divider"></div>

        <div style="font-size: 9px; color: #333; line-height: 1.3;">
            I hereby acknowledge receipt of the full security deposit refund stated above and confirm all room keys, remotes, and amenities were returned in good condition.
        </div>

        <!-- Counter Signatures -->
        <div class="sig-block">
            <div class="sig-line">
                Guest Signature: {{ $folio->guest->name ?? 'Valued Guest' }}
            </div>
            <div class="sig-line" style="margin-top: 18px;">
                Authorized Cashier: {{ $folio->cashier->name ?? 'Front Desk Staff' }} // FD-01
            </div>
        </div>

        <div class="divider" style="margin-top: 14px;"></div>

        <!-- Scannable Code 128 Barcode -->
        <div style="text-align: center; margin-top: 6px;">
            <div style="height: 34px; width: 85%; margin: 0 auto;">
                {!! \App\Services\BarcodeService::renderSvg('REF-' . $folio->transaction_id, 34) !!}
            </div>
            <div style="font-size: 9px; letter-spacing: 2px; font-weight: bold; margin-top: 3px;">
                *DEP-REF-{{ $folio->transaction_id }}*
            </div>
        </div>

        <div style="margin-top: 10px; text-align: center; font-size: 8px; color: #555;">
            *** OFFICIAL SETTLEMENT RECORD • SEDONA COURT PMS ***
        </div>
    </div>

</body>
</html>
