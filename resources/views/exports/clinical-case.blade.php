<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Educational Case #{{ $header['case_number'] }}</title>
    <style>
        @page { margin: 22mm 18mm 20mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9.5pt; color: #1e293b; line-height: 1.4; }
        h1 { font-size: 15pt; color: #0b2942; margin: 0 0 2mm; }
        h2 { font-size: 11pt; color: #0b2942; border-bottom: 0.6pt solid #cbd5e1; padding-bottom: 1mm; margin: 6mm 0 2mm; page-break-after: avoid; }
        h3 { font-size: 9.5pt; margin: 3mm 0 1mm; color: #334155; page-break-after: avoid; }
        .notice { font-size: 8.5pt; color: #475569; margin-bottom: 4mm; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; padding: 1mm 2mm 1mm 0; }
        td.label { width: 34%; color: #64748b; }
        .meta td { padding: 0.8mm 2mm 0.8mm 0; }
        .block { margin-left: 2mm; page-break-inside: avoid; }
        .empty { color: #64748b; font-style: italic; }
        .legacy { font-size: 8pt; color: #92400e; margin-top: 1mm; }
        footer { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 7.5pt; color: #64748b; }
    </style>
</head>
<body>
<footer>Pharmalab educational record · de-identified · generated {{ $generatedAt }}</footer>

<h1>Educational Case #{{ $header['case_number'] }}</h1>
<p class="notice">De-identified clinical learning record. It contains no patient name, hospital number, address, contact details or other direct identifier.</p>

<table class="meta">
    <tr><td class="label">Educational Case ID</td><td>#{{ $header['case_number'] }}</td></tr>
    <tr><td class="label">Approved version</td><td>Version {{ $header['version_number'] }}</td></tr>
    <tr><td class="label">Record form</td><td>{{ $header['form_version'] ?? '—' }}</td></tr>
    <tr><td class="label">Student</td><td>{{ $header['student_name'] ?? '—' }}</td></tr>
    <tr><td class="label">Rotation</td><td>{{ $header['rotation_name'] ?? '—' }}</td></tr>
    <tr><td class="label">Site / ward</td><td>{{ $header['clinical_site_name'] ?? '—' }}@if ($header['ward_name']) / {{ $header['ward_name'] }}@endif</td></tr>
    <tr><td class="label">Submitted</td><td>{{ $header['submitted_at'] ?? '—' }}</td></tr>
    <tr><td class="label">Approved</td><td>{{ $header['approved_at'] ?? '—' }}</td></tr>
    <tr><td class="label">Approved by</td><td>{{ $header['approver_name'] ?? '—' }}</td></tr>
</table>
@if ($legacyMetadata)
    <p class="legacy">This version was submitted before rotation, site and ward names were recorded at submission; the names shown are the current ones.</p>
@endif

@foreach ($sections as $section)
    <h2>{{ $section['title'] }}</h2>
    @if ($section['empty'])
        <p class="empty">{{ $section['empty'] }}</p>
    @endif
    @if ($section['rows'])
        <table>
            @foreach ($section['rows'] as [$label, $value])
                <tr><td class="label">{{ $label }}</td><td>{{ $value }}</td></tr>
            @endforeach
        </table>
    @endif
    @foreach ($section['blocks'] as $block)
        <div class="block">
            <h3>{{ $block['title'] }}</h3>
            <table>
                @foreach ($block['rows'] as [$label, $value])
                    <tr><td class="label">{{ $label }}</td><td>{{ $value }}</td></tr>
                @endforeach
            </table>
        </div>
    @endforeach
@endforeach
</body>
</html>
