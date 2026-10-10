{{-- Pre-filled B-BBEE sworn affidavit for small enterprises (S16). A template to print and sign before a
     commissioner of oaths - not a verified document. Wording to be checked against the latest official template. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>B-BBEE sworn affidavit - {{ $business->name }}</title>
    <style>
        @page { size: A4; margin: 18mm; }
        body { font-family: 'Liberation Sans', Arial, sans-serif; font-size: 10.5pt; line-height: 1.5; color: #111; }
        h1 { font-size: 15pt; margin: 0 0 2mm; }
        .note { border: 1px solid #999; padding: 3mm; font-size: 9pt; margin-bottom: 6mm; }
        .line { border-bottom: 1px solid #333; display: inline-block; min-width: 60mm; }
        .wide { min-width: 120mm; }
        ol li { margin-bottom: 3mm; }
        .sign { margin-top: 14mm; display: flex; gap: 20mm; }
        .sign div { flex: 1; border-top: 1px solid #333; padding-top: 2mm; font-size: 9pt; }
    </style>
</head>
<body>
    <h1>Sworn affidavit - B-BBEE Exempted Micro-Enterprise (EME)</h1>
    <div class="note">
        Prepared by KasiHub with the details you entered. Check it carefully, complete the blank parts by hand, and sign it
        <strong>in front of a commissioner of oaths</strong> (for example at a police station). Compare it with the latest official
        template published by the Department of Trade, Industry and Competition (the dtic) for your sector's B-BBEE codes.
    </div>

    <p>I, the undersigned, <span class="line wide">{{ $person->fullName() }}</span>,</p>
    <p>ID number <span class="line"></span>, declare under oath that:</p>
    <ol>
        <li>I am a member / director / owner of <strong>{{ $business->name }}</strong>
            @if ($business->legal_form === 'sole') (sole proprietor) @elseif ($business->legal_form === 'coop') (co-operative) @else (registration number <span class="line"></span>) @endif
            and am authorised to make this declaration.</li>
        <li>The business's total annual revenue for the most recent financial year was below the threshold for Exempted
            Micro-Enterprises in the B-BBEE Codes of Good Practice that apply to its sector.</li>
        <li>Black ownership of the business is <span class="line" style="min-width: 25mm"></span>% and black female ownership is
            <span class="line" style="min-width: 25mm"></span>%.</li>
        <li>Based on the above, the business's B-BBEE status level is <span class="line" style="min-width: 40mm"></span>
            (commonly: 100% black-owned = Level 1; at least 51% black-owned = Level 2; otherwise Level 4 - confirm against the codes).</li>
        <li>I know and understand the contents of this affidavit, I have no objection to taking the prescribed oath, and I consider
            the oath binding on my conscience.</li>
    </ol>

    <div class="sign">
        <div>Signature of deponent<br>Date: ____________</div>
        <div>Commissioner of oaths: signature, full name, designation, address and stamp<br>Date: ____________</div>
    </div>
</body>
</html>
