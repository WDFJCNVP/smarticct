{{--
    Shared letterhead sizing + background for every export PDF.

    Expects $paper ('letter'|'legal'|'a4') and $orientation
    ('portrait'|'landscape') to be in scope — controllers already pass
    these through, defaulting to 'legal' / 'portrait' when omitted.

    dompdf does NOT support background-image on @page (confirmed in
    dompdf's own source, vendor/dompdf/dompdf/src/Css/Stylesheet.php —
    background-color/-image on @page is explicitly listed as a
    "non-working property"). The supported way to get a repeating
    full-page letterhead is a `position: fixed` element: dompdf repaints
    anything position:fixed on every generated page, which is exactly the
    behavior we want here.

    @page still legitimately handles `size` and `margin` (those ARE on
    dompdf's working list), so the margin box below still exists to keep
    flowing content clear of the artwork.

    Background art comes from App\Services\PdfLetterhead, which resolves
    the right file for the paper size + orientation (falling back to the
    Legal artwork until Letter/A4 art is added — see that class).
--}}
@php
    use App\Services\PdfLetterhead;

    $paper = PdfLetterhead::normalizePaper($paper ?? null);
    $orientation = PdfLetterhead::normalizeOrientation($orientation ?? null);
    [$pageWidth, $pageHeight] = PdfLetterhead::pageSize($paper, $orientation);
    $letterheadBg = PdfLetterhead::background($paper, $orientation);
@endphp
@page {
    size: {{ $paper }} {{ $orientation }};
    margin: 1.55in 0.9in 1.65in 1.3in;
}

.letterhead-bg {
    position: fixed;
    top: -1.55in;
    left: -1.3in;
    width: {{ $pageWidth }}in;
    height: {{ $pageHeight }}in;
    z-index: -1;
    background-image: url('{{ $letterheadBg }}');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
}
