<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Billing Statement - Room {{ $folio->room->number ?? '' }}</title>
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
        table {
            width: 100%;
            border-collapse: collapse;
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
        th, td {
            padding: 3px 0;
            font-size: 11px;
        }
        .no-print {
            margin-bottom: 16px;
            text-align: center;
        }
        .btn-print {
            background-color: #0275d8;
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
        <button class="btn-print" onclick="window.print()">🖨️ Print 80mm Statement / Billing</button>
        <button class="btn-print" style="background:#4b5563; margin-left:6px;" onclick="window.close()">Close</button>
    </div>

    <div class="thermal-container" id="printable-billing">
        <!-- Header -->
        <div class="text-center">
            <div class="font-bold" style="font-size: 14px; letter-spacing: 1px;">SEDONA COURT</div>
            <div style="font-size: 9px;">TRAVELLER'S INN & LODGING</div>
            <div style="font-size: 9px;">Doña Remedios Trinidad Hwy, San Rafael, Bulacan</div>
            <div style="font-size: 9px;">TEL: +63 (0939) 905-2816 &bull; TIN: 402-881-294-000</div>
        </div>

        <div class="double-divider"></div>
        <div class="text-center font-bold" style="font-size: 11px;">STATEMENT OF ACCOUNT / GUEST FOLIO</div>
        <div class="text-center" style="font-size: 8px; color: #444;">*** PRE-PRINT BILLING FOR ROOM DELIVERY ***</div>
        <div class="divider"></div>

        <!-- Meta -->
        <div class="row">
            <span>Folio Ref:</span>
            <span class="font-bold">{{ $folio->transaction_id }}</span>
        </div>
        <div class="row">
            <span>Room Number:</span>
            <span class="font-bold">ROOM {{ $folio->room->number ?? 'N/A' }} ({{ $folio->room->type ?? '' }})</span>
        </div>
        <div class="row">
            <span>Guest Name:</span>
            <span>{{ $folio->guest->name ?? 'Walk-In Guest' }}</span>
        </div>
        <div class="row">
            <span>Check-In:</span>
            <span>{{ ($checkInTime ?? $folio->checked_in_at)->format('m/d/Y h:i A') }}</span>
        </div>
        <div class="row">
            <span>Expected Out:</span>
            <span>{{ ($expectedCheckoutAt ?? $folio->expected_checkout_at)->format('m/d/Y h:i A') }}</span>
        </div>
        <div class="row">
            <span>Check-Out:</span>
            <span>{{ ($checkOutTime ?? now())->format('m/d/Y h:i A') }}</span>
        </div>
        <div class="row">
            <span>Stay Tier:</span>
            <span>{{ strtoupper($folio->rate_tier) }} ({{ $tierHours }} Hours)</span>
        </div>
        <div class="row">
            <span>Actual Stay:</span>
            <span class="font-bold">{{ $actualHoursDiff }} {{ \Illuminate\Support\Str::plural('Hour', $actualHoursDiff) }}</span>
        </div>
        @if($excessHours > 0)
        <div class="row">
            <span>Excess Overtime:</span>
            <span class="font-bold">{{ $excessHours }} {{ \Illuminate\Support\Str::plural('Hour', $excessHours) }}</span>
        </div>
        @endif
        @if($xtendHours > 0)
        <div class="row">
            <span>Stay Extension:</span>
            <span class="font-bold">+{{ $xtendHours }} {{ \Illuminate\Support\Str::plural('Hour', $xtendHours) }}</span>
        </div>
        @endif
        <div class="row">
            <span>Bill Prepared:</span>
            <span>{{ now()->format('m/d/Y h:i A') }}</span>
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
                <!-- Lodging Charge -->
                <tr>
                    <td class="col-item">Room Lodging Charge ({{ strtoupper($folio->rate_tier) }})</td>
                    <td class="col-qty font-mono">1</td>
                    <td class="col-price font-mono">{{ number_format($roomRate, 2) }}</td>
                    <td class="col-total font-mono font-bold">{{ number_format($roomRate, 2) }}</td>
                </tr>

                @if($folio->status === 'checked_out' && !request()->has('checked_in_at') && !request()->has('checked_out_at'))
                    @if($folio->extra_hours > 0)
                        <tr>
                            <td class="col-item">Excess Hours ({{ $folio->extra_hours }}h)</td>
                            <td class="col-qty font-mono">{{ $folio->extra_hours }}</td>
                            <td class="col-price font-mono">130.00</td>
                            <td class="col-total font-mono font-bold">{{ number_format($folio->surcharge_total, 2) }}</td>
                        </tr>
                    @endif
                @else
                    <!-- Stay Extension (+Xh Xtend @ ₱130/hr) -->
                    @if($xtendHours > 0)
                        <tr>
                            <td class="col-item">Stay Extension (+{{ $xtendHours }}h Xtend)</td>
                            <td class="col-qty font-mono">{{ $xtendHours }}</td>
                            <td class="col-price font-mono">130.00</td>
                            <td class="col-total font-mono font-bold">{{ number_format($xtendRate, 2) }}</td>
                        </tr>
                    @endif

                    <!-- Excess Overtime (+Xh Overstay @ ₱130/hr) -->
                    @if($excessHours > 0)
                        <tr>
                            <td class="col-item">Excess Hours (+{{ $excessHours }}h Overstay)</td>
                            <td class="col-qty font-mono">{{ $excessHours }}</td>
                            <td class="col-price font-mono">130.00</td>
                            <td class="col-total font-mono font-bold">{{ number_format($excessRate, 2) }}</td>
                        </tr>
                    @elseif($xtendHours == 0 && ($folio->extra_hours ?? 0) > 0)
                        <tr>
                            <td class="col-item">Excess Hours ({{ $folio->extra_hours }}h)</td>
                            <td class="col-qty font-mono">{{ $folio->extra_hours }}</td>
                            <td class="col-price font-mono">130.00</td>
                            <td class="col-total font-mono font-bold">{{ number_format($folio->extra_hours * 130, 2) }}</td>
                        </tr>
                    @endif
                @endif

                <!-- Extra Guests -->
                @if($folio->extra_persons > 0)
                    <tr>
                        <td class="col-item">Extra Guest</td>
                        <td class="col-qty font-mono">{{ $folio->extra_persons }}</td>
                        <td class="col-price font-mono">200.00</td>
                        <td class="col-total font-mono font-bold">{{ number_format($folio->extra_persons * 200, 2) }}</td>
                    </tr>
                @endif

                <!-- Extra Towels -->
                @if($folio->extra_towels > 0)
                    <tr>
                        <td class="col-item">Extra Towels</td>
                        <td class="col-qty font-mono">{{ $folio->extra_towels }}</td>
                        <td class="col-price font-mono">100.00</td>
                        <td class="col-total font-mono font-bold">{{ number_format($folio->extra_towels * 100, 2) }}</td>
                    </tr>
                @endif

                <!-- POS Items -->
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
            <span>₱{{ number_format($grossTotal, 2) }}</span>
        </div>

        @php
            $dType = $discountType ?? $folio->discount_type;
            $dRef = $discountIdRef ?? $folio->discount_id_ref;
        @endphp
        @if($totalDiscount > 0)
            <div class="row font-bold">
                <span>
                    Less: {{ $dType === 'DC' ? 'Sedona Discount Card' : ($dType === 'PWD' ? 'PWD Statutory Table' : 'Senior Statutory Table') }}{{ $dRef ? ' (' . $dRef . ')' : '' }}:
                </span>
                <span>-₱{{ number_format($totalDiscount, 2) }}</span>
            </div>
        @endif

        @if($deposit > 0)
            @if($applyDeposit)
                <div class="row font-bold">
                    <span>Less: Security Deposit Applied:</span>
                    <span>-₱{{ number_format($depositApplied, 2) }}</span>
                </div>
                @if($remainingDeposit > 0)
                    <div class="row font-bold">
                        <span>Remaining Deposit to Refund:</span>
                        <span>₱{{ number_format($remainingDeposit, 2) }}</span>
                    </div>
                @endif
            @else
                <div class="row">
                    <span>Security Deposit (Not Applied):</span>
                    <span>₱{{ number_format($deposit, 2) }}</span>
                </div>
                <div class="row font-bold">
                    <span>Remaining Deposit to Refund:</span>
                    <span>₱{{ number_format($remainingDeposit, 2) }}</span>
                </div>
            @endif
        @else
            <div class="row">
                <span>Less: Security Deposit:</span>
                <span>₱0.00</span>
            </div>
        @endif

        <div class="double-divider"></div>
        <div class="row font-bold" style="font-size: 13px;">
            <span>TOTAL AMOUNT DUE:</span>
            <span>₱{{ number_format($finalBalanceToPay, 2) }}</span>
        </div>
        <div class="double-divider"></div>

        <!-- Cashier Settlement & Payment Details -->
        @if(($paymentMethod && $paymentMethod !== 'cash') || $cashTendered > 0 || $gcashAmount > 0 || !empty($gcashRef) || ($folio->status === 'checked_out') || request()->has('payment_method'))
            <div class="row">
                <span>Payment Method:</span>
                <span class="font-bold">
                    @if($paymentMethod === 'split')
                        SPLIT (CASH + GCASH)
                    @elseif($paymentMethod === 'gcash')
                        GCASH E-WALLET
                    @else
                        CASH PAYMENT
                    @endif
                </span>
            </div>

            @if($paymentMethod === 'split')
                <div class="row">
                    <span>Cash Tendered:</span>
                    <span>₱{{ number_format($cashTendered, 2) }}</span>
                </div>
                <div class="row">
                    <span>GCash Amount Paid:</span>
                    <span>₱{{ number_format($gcashAmount, 2) }}</span>
                </div>
                @if(!empty($gcashRef))
                    <div class="row">
                        <span>GCash Reference #:</span>
                        <span>{{ $gcashRef }}</span>
                    </div>
                @endif
                <div class="row font-bold">
                    <span>Change Due to Guest:</span>
                    <span>₱{{ number_format($changeDue, 2) }}</span>
                </div>
            @elseif($paymentMethod === 'gcash')
                <div class="row">
                    <span>GCash Amount Paid:</span>
                    <span>₱{{ number_format($gcashAmount > 0 ? $gcashAmount : $finalBalanceToPay, 2) }}</span>
                </div>
                @if(!empty($gcashRef))
                    <div class="row">
                        <span>GCash Reference #:</span>
                        <span>{{ $gcashRef }}</span>
                    </div>
                @endif
            @else
                <div class="row">
                    <span>Cash Tendered:</span>
                    <span>₱{{ number_format($cashTendered, 2) }}</span>
                </div>
                <div class="row font-bold">
                    <span>Change Due to Guest:</span>
                    <span>₱{{ number_format($changeDue, 2) }}</span>
                </div>
            @endif
            <div class="double-divider"></div>
        @endif

        <div class="divider"></div>

        <!-- Scannable Code 128 Barcode -->
        <div style="text-align: center; margin-top: 8px;">
            <div style="height: 36px; width: 85%; margin: 0 auto;">
                {!! \App\Services\BarcodeService::renderSvg($folio->transaction_id, 36) !!}
            </div>
            <div style="font-size: 9px; letter-spacing: 3px; font-weight: bold; margin-top: 3px;">
                *{{ $folio->transaction_id }}*
            </div>
        </div>

        <div class="text-center" style="font-size: 9px; margin-top: 10px; color: #333;">
            <div>Please review charges before checkout.</div>
            <div>Settle your balance at Frontdesk Reception.</div>
            <div style="margin-top: 4px; font-size: 8px; color: #666;">
                "Interim Billing Folio &bull; Sedona Court PMS"
            </div>
        </div>
    </div>

</body>
</html>
