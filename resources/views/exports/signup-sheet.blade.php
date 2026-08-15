<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $event->name }} — Check-in Sheet</title>
    <style>
        @page {
            margin: 1.5cm;
        }
        body {
            font-family: sans-serif;
            font-size: 11pt;
            color: #000;
        }
        h1 {
            font-size: 16pt;
            margin: 0 0 2pt 0;
        }
        .subtitle {
            font-size: 9pt;
            color: #444;
            margin-bottom: 12pt;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border-bottom: 1px solid #999;
            padding: 6pt 4pt;
            text-align: left;
            vertical-align: middle;
        }
        th {
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: 0.5pt;
            color: #555;
            border-bottom: 2px solid #000;
        }
        tr {
            height: 28pt;
        }
        .checkbox-col {
            width: 22pt;
        }
        .checkbox {
            display: inline-block;
            width: 14pt;
            height: 14pt;
            border: 1.5pt solid #000;
        }
        .name-col {
            width: 26%;
        }
        .shirt-col {
            width: 45pt;
        }
        .notes-col {
            width: 28%;
        }
    </style>
</head>
<body>
    <h1>{{ $event->name }} — Check-in Sheet</h1>
    <div class="subtitle">
        {{ $event->starts_at->format('l, F j, Y') }} &middot; {{ $event->location }} &middot; {{ $orders->count() }} attendee{{ $orders->count() === 1 ? '' : 's' }}
    </div>

    <table>
        <thead>
            <tr>
                <th class="checkbox-col">In</th>
                <th>Name</th>
                <th>Kennel</th>
                <th class="shirt-col">Shirt</th>
                @if ($showShortBus)
                    <th class="shirt-col">Bus</th>
                @endif
                @if ($showOrderId)
                    <th>Order ID</th>
                @endif
                @if ($showComment)
                    <th>Comment</th>
                @endif
                <th class="notes-col">Notes</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($orders as $order)
                <tr>
                    <td><span class="checkbox"></span></td>
                    <td>{{ $order->user_name }}</td>
                    <td>{{ $order->kennel }}</td>
                    <td>{{ $order->shirt_size }}</td>
                    @if ($showShortBus)
                        <td>{{ in_array($order->short_bus, ['Y', '1', 1, true], true) ? 'Yes' : '' }}</td>
                    @endif
                    @if ($showOrderId)
                        <td>{{ $order->order_id }}</td>
                    @endif
                    @if ($showComment)
                        <td>{{ $order->comment }}</td>
                    @endif
                    <td></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
