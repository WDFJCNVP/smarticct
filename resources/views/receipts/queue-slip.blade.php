<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        @page { 
            size: 58mm auto; 
            margin: 0; 
        }
        * {
            color: #000000 !important;
            -webkit-font-smoothing: none !important;
            -moz-osx-font-smoothing: unset !important;
            text-rendering: geometricPrecision !important;
        }
        body {
            width: 48mm;
            margin: 0 auto;
            padding: 4px 0;
            /* Consolas or Arial Bold burns much thicker lines than Courier New */
            font-family: 'Consolas', 'Lucida Console', 'Arial Black', sans-serif;
            font-size: 12px;
            font-weight: 800;
            line-height: 1.25;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            font-weight: 700;
        }
        .divider {
            border-top: 2px solid #000;
            margin: 4px 0;
        }
    </style>
</head>
<body>
    <div class="text-center title">SMART ICCT</div>
    <div class="text-center">QUEUED VEHICLE SLIP</div>
    <div class="divider"></div>

    <table>
        <tr>
            <td width="35%">Ref:</td>
            <td width="65%" class="text-right">{{ $reference_no }}</td>
        </tr>
        <tr>
            <td>Date:</td>
            <td class="text-right">{{ $date }}</td>
        </tr>
        <tr>
            <td>Operator:</td>
            <td class="text-right">{{ $operator_name }}</td>
        </tr>
        <tr>
            <td>Driver:</td>
            <td class="text-right">{{ $driver_name ?: 'N/A' }}</td>
        </tr>
        <tr>
            <td>Plate:</td>
            <td class="text-right bold">{{ $plate_number }}</td>
        </tr>
        <tr>
            <td>Type:</td>
            <td class="text-right">{{ $vehicle_type }}</td>
        </tr>
        <tr>
            <td>Route:</td>
            <td class="text-right">Iriga &rarr; {{ $destination }}</td>
        </tr>
    </table>

    <div class="divider"></div>

    <table>
        <tr class="bold">
            <td>Queue Fee:</td>
            <td class="text-right">PHP {{ number_format($fee, 2) }}</td>
        </tr>
        @if($is_cash)
            <tr>
                <td>Paid:</td>
                <td class="text-right">PHP {{ number_format($amount_received, 2) }}</td>
            </tr>
            <tr>
                <td>Change:</td>
                <td class="text-right">PHP {{ number_format($change, 2) }}</td>
            </tr>
        @endif
    </table>

    <div class="divider"></div>
    <div class="text-center" style="margin-top: 4px;">KEEP THIS TICKET</div>
</body>
</html>