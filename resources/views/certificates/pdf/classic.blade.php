{{--
    "Classic" certificate layout — ivory background, gold rule borders. Rendered by
    both dompdf (CertificateRenderer::renderPdf) and the admin live preview
    (renderPreviewHtml) from the exact same markup. dompdf-safe: inline CSS only, no
    external fonts (Georgia/Helvetica are dompdf's bundled web-safe substitutes — the
    same reasoning as the Section 1 e-mail theme), every image a base64 data URI.

    Expected variables: $studentName, $courseTitle, $completionDate, $serial,
    $verifyUrl, $accentColor, $signatories (array of {name,title,signatureDataUri}),
    $gradeLine (nullable string), $logoDataUri, $motifDataUri, $qrDataUri.
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
        background: #FFFDF6;
    }
    .sheet {
        position: relative;
        width: 297mm;
        height: 210mm;
        background: #FFFDF6;
        overflow: hidden;
    }
    .border-outer {
        position: absolute;
        top: 8mm; left: 8mm; right: 8mm; bottom: 8mm;
        border: 2.6pt solid {{ $accentColor }};
    }
    .border-inner {
        position: absolute;
        top: 10.5mm; left: 10.5mm; right: 10.5mm; bottom: 10.5mm;
        border: 0.75pt solid {{ $accentColor }};
    }
    .watermark {
        position: absolute;
        top: 50%; left: 50%;
        width: 140mm; height: 140mm;
        margin-top: -70mm; margin-left: -70mm;
    }
    .content {
        position: relative;
        text-align: center;
        padding: 16mm 22mm 0 22mm;
    }
    .logo { height: 20mm; }
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
        color: #1C1917;
        border-bottom: 1pt solid {{ $accentColor }};
        display: inline-block;
        padding: 0 6mm 3mm 6mm;
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
    /* Absolute (not table) positioning for the signature/footer row — dompdf's
       support for display:table nested inside a position:absolute box is unreliable,
       so each element is placed independently for predictable output. */
    .signatures {
        position: absolute;
        bottom: 32mm;
        left: 22mm;
        right: 22mm;
        height: 22mm;
    }
    .signature {
        position: absolute;
        bottom: 0;
        width: 95mm;
        text-align: center;
    }
    .signature-0 { left: 0; }
    .signature-1 { right: 0; }
    .signature-solo { left: 50%; margin-left: -47.5mm; }
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
        bottom: 11mm;
        left: 22mm;
        right: 22mm;
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
        <div class="border-outer"></div>
        <div class="border-inner"></div>
        @if ($motifDataUri)
            <img class="watermark" src="{{ $motifDataUri }}" alt="">
        @endif

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
