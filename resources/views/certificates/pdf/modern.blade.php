{{--
    "Modern" certificate layout — white background, crimson diagonal motif echoing the
    brochure cover. Same variable contract and dompdf-safety notes as classic.blade.php
    (see its header comment) — this is the second of the two shipped designs.
--}}
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 0; }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        margin: 0;
        padding: 0;
        font-family: "DejaVu Serif", Georgia, serif;
        color: #1C1917;
        background: #FFFFFF;
    }
    .sheet {
        position: relative;
        width: 297mm;
        height: 210mm;
        background: #FFFFFF;
        overflow: hidden;
    }
    .motif {
        position: absolute;
        top: 0; right: 0;
        width: 130mm; height: 97.5mm;
    }
    .rule {
        position: absolute;
        left: 0; right: 0; bottom: 16mm;
        height: 2.6pt;
        background: {{ $accentColor }};
    }
    .content {
        position: relative;
        text-align: center;
        padding: 20mm 26mm 0 26mm;
    }
    .logo { height: 19mm; }
    .eyebrow {
        margin-top: 6mm;
        font-family: "DejaVu Sans", Helvetica, sans-serif;
        font-size: 10pt;
        letter-spacing: 3pt;
        text-transform: uppercase;
        color: {{ $accentColor }};
    }
    .title {
        margin: 4mm 0 0 0;
        font-size: 30pt;
        font-weight: bold;
        color: #1C1917;
    }
    .presented {
        margin-top: 9mm;
        font-size: 11pt;
        color: #57534E;
    }
    .student-name {
        margin: 4mm 0;
        font-size: 26pt;
        font-weight: bold;
        color: {{ $accentColor }};
    }
    .earned {
        margin-top: 6mm;
        font-size: 11pt;
        color: #57534E;
    }
    .course-title {
        margin: 3mm 10mm 0 10mm;
        font-size: 15pt;
        line-height: 1.25;
        font-weight: bold;
        color: #1C1917;
    }
    .grade-line {
        margin-top: 4mm;
        font-family: "DejaVu Sans", Helvetica, sans-serif;
        font-size: 11pt;
        letter-spacing: 0.5pt;
        color: {{ $accentColor }};
    }
    .date-line {
        margin-top: 5mm;
        font-size: 10.5pt;
        color: #57534E;
    }
    /* Absolute (not table) positioning — see classic.blade.php's comment. */
    .signatures {
        position: absolute;
        bottom: 34mm;
        left: 26mm;
        right: 26mm;
        height: 22mm;
    }
    .signature {
        position: absolute;
        bottom: 0;
        width: 92mm;
        text-align: center;
    }
    .signature-0 { left: 0; }
    .signature-1 { right: 0; }
    .signature-solo { left: 50%; margin-left: -46mm; }
    .signature img {
        height: 14mm;
        margin-bottom: -2mm;
    }
    .signature .rule {
        border-top: 0.75pt solid #57534E;
        width: 65mm;
        margin: 0 auto;
    }
    .signature .name {
        margin-top: 2mm;
        font-family: "DejaVu Sans", Helvetica, sans-serif;
        font-size: 9.5pt;
        font-weight: bold;
    }
    .signature .role {
        font-family: "DejaVu Sans", Helvetica, sans-serif;
        font-size: 8.5pt;
        color: #78716C;
    }
    .footer {
        position: absolute;
        bottom: 9mm;
        left: 26mm;
        right: 26mm;
        height: 18mm;
        font-family: "DejaVu Sans", Helvetica, sans-serif;
        font-size: 8pt;
        color: #78716C;
    }
    .footer .meta { position: absolute; left: 0; bottom: 0; text-align: left; }
    .footer .qr { position: absolute; right: 0; bottom: 0; width: 18mm; text-align: right; }
    .footer .qr img { width: 18mm; height: 18mm; }
</style>
</head>
<body>
    <div class="sheet">
        <img class="motif" src="{{ $motifDataUri }}" alt="">
        <div class="rule"></div>

        <div class="content">
            @if ($logoDataUri)
                <img class="logo" src="{{ $logoDataUri }}" alt="">
            @endif

            <p class="eyebrow">{{ config('brand.university') }}</p>
            <h1 class="title">Certificate of Completion</h1>

            <p class="presented">This certifies that</p>
            <div class="student-name">{{ $studentName }}</div>

            <p class="earned">has successfully completed the course</p>
            <p class="course-title">{{ $courseTitle }}</p>

            @if ($gradeLine)
                <p class="grade-line">Achieved: {{ $gradeLine }}</p>
            @endif

            <p class="date-line">Awarded on {{ $completionDate }}</p>
        </div>

        <div class="signatures">
            @foreach ($signatories as $i => $signatory)
                <div class="signature {{ count($signatories) === 1 ? 'signature-solo' : 'signature-'.$i }}">
                    @if ($signatory['signatureDataUri'])
                        <img src="{{ $signatory['signatureDataUri'] }}" alt="">
                    @endif
                    <div class="rule"></div>
                    <div class="name">{{ $signatory['name'] }}</div>
                    <div class="role">{{ $signatory['title'] }}</div>
                </div>
            @endforeach
        </div>

        <div class="footer">
            <div class="meta">
                Serial {{ $serial }}<br>
                Verify at {{ $verifyUrl }}
            </div>
            <div class="qr">
                <img src="{{ $qrDataUri }}" alt="">
            </div>
        </div>
    </div>
</body>
</html>
