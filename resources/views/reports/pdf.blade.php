<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }}</title>
    <style>
        * { font-family: hindsiliguri, sans-serif; }
        body { font-size: 10px; color: #1f2937; margin: 0; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        .meta { color: #6b7280; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f3f4f6; text-align: left; font-weight: bold; padding: 5px 4px; border-bottom: 1px solid #d1d5db; }
        td { padding: 4px; border-bottom: 1px solid #eef0f3; }
        .num { text-align: right; }
        tfoot td { font-weight: bold; background: #f9fafb; border-top: 1px solid #9ca3af; }
    </style>
</head>
<body>
    <h1>{{ config('app.name') }} — {{ $report['title'] }}</h1>
    <div class="meta">
        সময়কাল {{ \App\Services\Bangla::digits($report['filters']['from']) }} থেকে {{ \App\Services\Bangla::digits($report['filters']['to']) }} (ঢাকা সময়)। তৈরি হয়েছে {{ \App\Services\Bangla::digits($generatedAt->format('Y-m-d H:i')) }}। সব পরিমাণ পিস হিসেবে।
    </div>
    <table>
        <thead>
            <tr>
                @foreach ($report['columns'] as $column)
                    <th class="{{ $column['type'] === 'number' ? 'num' : '' }}">{{ $column['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($report['rows'] as $row)
                <tr>
                    @foreach ($report['columns'] as $column)
                        <td class="{{ $column['type'] === 'number' ? 'num' : '' }}">
                            {{ $column['type'] === 'number' ? \App\Services\Bangla::number($row[$column['key']] ?? 0) : ($row[$column['key']] ?? '—') }}
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($report['columns']) }}">এই সময়ের কোনো তথ্য নেই।</td></tr>
            @endforelse
        </tbody>
        @if ($report['totals'])
            <tfoot>
                <tr>
                    @foreach ($report['columns'] as $index => $column)
                        <td class="{{ $column['type'] === 'number' ? 'num' : '' }}">
                            @if ($index === 0) মোট @elseif (array_key_exists($column['key'], $report['totals'])) {{ \App\Services\Bangla::number($report['totals'][$column['key']]) }} @endif
                        </td>
                    @endforeach
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
