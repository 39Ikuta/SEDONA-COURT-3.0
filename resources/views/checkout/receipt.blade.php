<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Official Receipt - {{ $folio->transaction_id }}</title>
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
        .thermal-container {
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
            margin: 6px 0;
        }
        .double-divider {
            border-top: 2px solid #000;
            margin: 6px 0;
        }
        .row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2px;
        }
        .receipt-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin: 4px 0;
        }
        .receipt-table th {
            font-size: 11px;
            font-weight: bold;
            padding: 4px 0;
            border-bottom: 1px dashed #000;
        }
        .receipt-table td {
            font-size: 11px;
            padding: 3px 0;
            vertical-align: top;
        }
        .col-item {
            width: 44%;
            text-align: left;
            word-break: break-word;
            overflow-wrap: break-word;
            padding-right: 6px !important;
        }
        .col-qty {
            width: 12%;
            text-align: center;
            white-space: nowrap;
            padding-left: 2px !important;
            padding-right: 2px !important;
        }
        .col-price {
            width: 22%;
            text-align: right;
            white-space: nowrap;
            padding-left: 2px !important;
            padding-right: 6px !important;
        }
        .col-total {
            width: 22%;
            text-align: right;
            white-space: nowrap;
            padding-left: 6px !important;
            padding-right: 0 !important;
        }
        .no-print {
            margin-bottom: 16px;
            text-align: center;
        }
        .btn-print {
            background-color: #421A2B;
            color: #fff;
            padding: 8px 16px;
            font-size: 12px;
            font-weight: bold;
            border: none;
            cursor: pointer;
            border-radius: 4px;
        }
        @media print {
            body { background-color: #fff; padding: 0; }
            .thermal-container { border: none; box-shadow: none; padding: 4px; width: 100%; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <button class="btn-print" onclick="window.print()">🖨️ Print 80mm Official Receipt</button>
        <button class="btn-print" style="background:#4b5563; margin-left:6px;" onclick="window.close()">Close</button>
    </div>

    <div class="thermal-container" id="printable-receipt">
        <div class="text-center">
            <div class="font-bold" style="font-size: 14px; letter-spacing: 1px;">SEDONA COURT</div>
            <div style="font-size: 9px;">TRAVELLER'S INN &amp; LODGING</div>
            <div style="font-size: 9px;">Doña Remedios Trinidad Hwy, San Rafael, Bulacan</div>
            <div style="font-size: 9px;">TEL: +63 (0939) 905-2816 &bull; TIN: 402-881-294-000</div>
        </div>

        <div class="double-divider"></div>
        <div class="text-center font-bold" style="font-size: 11px;">OFFICIAL SETTLEMENT RECEIPT</div>
        <div class="divider"></div>

        <div class="row">
            <span>Folio #:</span>
            <span class="font-bold">{{ $folio->transaction_id }}</span>
        </div>
        <div class="row">
            <span>Room Assigned:</span>
            <span class="font-bold">ROOM {{ $folio->room->number ?? 'N/A' }} ({{ $folio->room->type ?? '' }})</span>
        </div>
        <div class="row">
            <span>Guest Name:</span>
            <span>{{ $folio->guest->name ?? 'Walk-In Guest' }}</span>
        </div>
        <div class="row">
            <span>Check-In:</span>
            <span>{{ $folio->checked_in_at->format('m/d/Y h:i A') }}</span>
        </div>
        <div class="row">
            <span>Check-Out:</span>
            <span>{{ ($folio->checked_out_at ?? now())->format('m/d/Y h:i A') }}</span>
        </div>
        <div class="row">
            <span>Rate Stay Tier:</span>
            <span>{{ strtoupper($folio->rate_tier) }} ({{ $folio->room ? $folio->room->getTierHours($folio->rate_tier) : 3 }} Hours)</span>
        </div>

        <div class="divider"></div>
        <table class="receipt-table">
            <thead>
                <tr>
                    <th class="col-item">Item / Charge</th>
                    <th class="col-qty">Qty</th>
                    <th class="col-price">Price</th>
                    <th class="col-total">Total</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $tierH = $folio->room ? $folio->room->getTierHours($folio->rate_tier) : 3;
                @endphp
                <tr>
                    <td class="col-item">Room Base ({{ $tierH }}h)</td>
                    <td class="col-qty font-mono">1</td>
                    <td class="col-price font-mono">{{ number_format($folio->room_charge, 2) }}</td>
                    <td class="col-total font-mono font-bold">{{ number_format($folio->room_charge, 2) }}</td>
                </tr>

                @if($folio->extra_hours > 0)
                    <tr>
                        <td class="col-item">Excess Hours ({{ $folio->extra_hours }}h)</td>
                        <td class="col-qty font-mono">{{ $folio->extra_hours }}</td>
                        <td class="col-price font-mono">130.00</td>
                        <td class="col-total font-mono font-bold">{{ number_format($folio->extra_hours * 130, 2) }}</td>
                    </tr>
                @endif

                @if($folio->extra_persons > 0)
                    <tr>
                        <td class="col-item">Extra Guest</td>
                        <td class="col-qty font-mono">{{ $folio->extra_persons }}</td>
                        <td class="col-price font-mono">200.00</td>
                        <td class="col-total font-mono font-bold">{{ number_format($folio->extra_persons * 200, 2) }}</td>
                    </tr>
                @endif

                @if($folio->extra_towels > 0)
                    <tr>
                        <td class="col-item">Extra Towels</td>
                        <td class="col-qty font-mono">{{ $folio->extra_towels }}</td>
                        <td class="col-price font-mono">100.00</td>
                        <td class="col-total font-mono font-bold">{{ number_format($folio->extra_towels * 100, 2) }}</td>
                    </tr>
                @endif

                @if(!empty($folio->pos_items) && is_array($folio->pos_items))
                    @foreach($folio->pos_items as $p)
                        @php
                            $qty = (int)($p['qty'] ?? 1);
                            $subtotal = (float)($p['subtotal'] ?? 0);
                            $price = isset($p['price']) ? (float)$p['price'] : ($qty > 0 ? $subtotal / $qty : $subtotal);
                        @endphp
                        <tr>
                            <td class="col-item">{{ $p['name'] ?? 'Item' }}</td>
                            <td class="col-qty font-mono">{{ $qty }}</td>
                            <td class="col-price font-mono">{{ number_format($price, 2) }}</td>
                            <td class="col-total font-mono font-bold">{{ number_format($subtotal, 2) }}</td>
                        </tr>
                    @endforeach
                @endif
            </tbody>
        </table>
        <div class="divider"></div>
        <div class="row">
            <span>Gross Subtotal:</span>
            <span>₱{{ number_format($grossTotal ?? $folio->gross_total, 2) }}</span>
        </div>

        @php
            $disc = $totalDiscount ?? $folio->discount_amount;
            $dType = $discountType ?? $folio->discount_type;
            $dRef = $discountIdRef ?? $folio->discount_id_ref;
            $dep = $deposit ?? (float)($folio->security_deposit ?? 0);
        @endphp
        @if($disc > 0)
            <div class="row font-bold">
                <span>
                    Less: {{ $dType === 'DC' ? 'Sedona Discount Card' : ($dType === 'PWD' ? 'PWD Statutory Table' : 'Senior Statutory Table') }}{{ $dRef ? ' (' . $dRef . ')' : '' }}:
                </span>
                <span>-₱{{ number_format($disc, 2) }}</span>
            </div>
        @endif

        @if($dep > 0)
            @if($applyDeposit ?? ($folio->deposit_status !== 'not_applied'))
                <div class="row font-bold">
                    <span>Less: Security Deposit Applied:</span>
                    <span>-₱{{ number_format($depositApplied ?? min($dep, $disc > 0 ? max(0, ($grossTotal ?? $folio->gross_total) - $disc) : ($grossTotal ?? $folio->gross_total)), 2) }}</span>
                </div>
                @if(($remainingDeposit ?? 0) > 0)
                    <div class="row font-bold">
                        <span>Remaining Deposit to Refund:</span>
                        <span>₱{{ number_format($remainingDeposit, 2) }}</span>
                    </div>
                @endif
            @else
                <div class="row">
                    <span>Security Deposit (Not Applied):</span>
                    <span>₱{{ number_format($dep, 2) }}</span>
                </div>
                <div class="row font-bold">
                    <span>Remaining Deposit to Refund:</span>
                    <span>₱{{ number_format($remainingDeposit ?? $dep, 2) }}</span>
                </div>
            @endif
        @endif

        <div class="double-divider"></div>
        <div class="row font-bold" style="font-size: 13px;">
            <span>NET AMOUNT DUE:</span>
            <span>₱{{ number_format($finalBalanceToPay ?? $folio->net_total, 2) }}</span>
        </div>
        <div class="divider"></div>

        <div class="row">
            <span>Payment Method:</span>
            <span class="font-bold">
                @if(($folio->payment_method ?? '') === 'split')
                    SPLIT (CASH + GCASH)
                @elseif(($folio->payment_method ?? '') === 'gcash')
                    GCASH E-WALLET
                @else
                    CASH PAYMENT
                @endif
            </span>
        </div>

        @if(($folio->payment_method ?? '') === 'split')
            <div class="row">
                <span>Cash Tendered:</span>
                <span>₱{{ number_format($folio->cash_tendered, 2) }}</span>
            </div>
            <div class="row">
                <span>GCash Amount Paid:</span>
                <span>₱{{ number_format($folio->gcash_amount, 2) }}</span>
            </div>
            @if(!empty($folio->gcash_reference))
                <div class="row">
                    <span>GCash Ref #:</span>
                    <span>{{ $folio->gcash_reference }}</span>
                </div>
            @endif
            <div class="row font-bold">
                <span>Change Returned:</span>
                <span>₱{{ number_format($folio->change_due, 2) }}</span>
            </div>
        @elseif(($folio->payment_method ?? '') === 'gcash')
            <div class="row">
                <span>GCash Amount Paid:</span>
                <span>₱{{ number_format($folio->gcash_amount > 0 ? $folio->gcash_amount : ($finalBalanceToPay ?? $folio->net_total), 2) }}</span>
            </div>
            @if(!empty($folio->gcash_reference))
                <div class="row">
                    <span>GCash Ref #:</span>
                    <span>{{ $folio->gcash_reference }}</span>
                </div>
            @endif
        @else
            <div class="row">
                <span>Cash Tendered:</span>
                <span>₱{{ number_format($folio->cash_tendered, 2) }}</span>
            </div>
            <div class="row font-bold">
                <span>Change Returned:</span>
                <span>₱{{ number_format($folio->change_due, 2) }}</span>
            </div>
        @endif

        <div class="double-divider"></div>

        <div class="double-divider"></div>

        <!-- Scannable Code 128 Barcode -->
        <div style="text-align: center; margin-top: 10px;">
            <div style="height: 36px; width: 85%; margin: 0 auto;">
                {!! \App\Services\BarcodeService::renderSvg($folio->transaction_id, 36) !!}
            </div>
            <div style="font-size: 9px; letter-spacing: 3px; font-weight: bold; margin-top: 3px;">
                *{{ $folio->transaction_id }}*
            </div>
        </div>

        <div class="text-center" style="font-size: 10px; margin-top: 10px;">
            <div>Attending Cashier: <strong>{{ $folio->cashier->name ?? 'Front Desk Staff' }} // FD-01</strong></div>
            <div style="margin-top: 4px; font-weight: bold;">Thank you for choosing Sedona Court!</div>
            <div style="font-size: 9px; color: #444;">Please present your Exit Gate Pass upon vehicle departure.</div>
            <div style="margin-top: 6px; font-size: 8px; color: #666;">"This document serves as an Official Settlement Slip"</div>
        </div>

        <div class="no-print" style="margin-top: 16px; border-top: 1px dashed #ccc; padding-top: 12px; text-align: center;">
            <a href="{{ route('folios.gate_pass', $folio) }}" target="_blank" class="btn-print" style="background: #1e3a8a; text-decoration: none; display: inline-block;">
                🎫 Print Exit Gate Pass
            </a>
        </div>
    </div>

</body>
</html>
