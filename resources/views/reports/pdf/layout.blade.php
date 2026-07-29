@php
    /**
     * The one branded report layout — shared by all four report-centre PDFs.
     * $title, $headings (array), $rows (array of positional value arrays),
     * $filters (label => value echo) and $generatedAt are supplied by ReportPdf.
     */
    $logoPath = config('brand.logos.color');
    $logoData = $logoPath && file_exists(public_path($logoPath))
        ? 'data:image/png;base64,'.base64_encode(file_get_contents(public_path($logoPath)))
        : null;
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 150px 32px 70px 32px; }
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #1C1917; font-size: 10px; margin: 0; }

        header {
            position: fixed; top: -120px; left: 0; right: 0; height: 100px;
            border-bottom: 3px solid #C8102E; padding-bottom: 10px;
        }
        header .logo { height: 42px; }
        header .brand { font-size: 15px; font-weight: bold; color: #1C1917; }
        header .motto { font-size: 9px; color: #8A6A12; letter-spacing: 0.5px; }
        header .doc-title { font-size: 18px; color: #C8102E; font-weight: bold; margin-top: 8px; }

        footer {
            position: fixed; bottom: -50px; left: 0; right: 0; height: 40px;
            border-top: 1px solid #E7E5E4; padding-top: 6px;
            font-size: 8px; color: #78716C;
        }
        footer .page:before { content: counter(page); }
        footer .pages:before { content: counter(pages); }

        .filters { margin: 0 0 10px; font-size: 9px; color: #57534E; }
        .filters span { display: inline-block; margin-right: 14px; }
        .filters b { color: #1C1917; }

        table { width: 100%; border-collapse: collapse; }
        thead th {
            background: #C8102E; color: #fff; text-align: left; font-size: 9px;
            padding: 6px 7px; border: 1px solid #C8102E;
        }
        tbody td { padding: 5px 7px; border: 1px solid #E7E5E4; font-size: 9px; }
        tbody tr:nth-child(even) td { background: #FAF9F6; }
        .empty { padding: 24px; text-align: center; color: #78716C; }
    </style>
</head>
<body>
    <header>
        <table style="width:100%; border:0;">
            <tr>
                <td style="border:0; vertical-align:middle;">
                    @if ($logoData)
                        <img class="logo" src="{{ $logoData }}" alt="{{ config('brand.university') }}">
                    @else
                        <div class="brand">{{ config('brand.name') }}</div>
                    @endif
                </td>
                <td style="border:0; text-align:right; vertical-align:middle;">
                    <div class="brand">{{ config('brand.university') }}</div>
                    <div class="motto">{{ config('brand.motto') }}</div>
                </td>
            </tr>
        </table>
        <div class="doc-title">{{ $title }}</div>
    </header>

    <footer>
        <table style="width:100%; border:0;">
            <tr>
                <td style="border:0;">{{ config('brand.name') }} · Generated {{ $generatedAt->format('d M Y, H:i') }}</td>
                <td style="border:0; text-align:right;">Page <span class="page"></span> of <span class="pages"></span></td>
            </tr>
        </table>
    </footer>

    <main>
        @if (! empty($filters))
            <div class="filters">
                @foreach ($filters as $label => $value)
                    <span><b>{{ $label }}:</b> {{ $value }}</span>
                @endforeach
            </div>
        @endif

        <table>
            <thead>
                <tr>
                    @foreach ($headings as $heading)
                        <th>{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        @foreach ($row as $cell)
                            <td>{{ $cell }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td class="empty" colspan="{{ count($headings) }}">No rows match the selected filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </main>
</body>
</html>
