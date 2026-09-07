<?php

namespace App\Services;

/**
 * Central place for the export-PDF letterhead: physical page dimensions and
 * which background artwork to use for a given paper size + orientation.
 *
 * Background files live in public/images and follow the naming convention:
 *   pdf-bg-{paper}-{orientation}.jpg   e.g. pdf-bg-legal-portrait.jpg
 *
 * Only the Legal artwork exists today. Until Letter/A4 artwork is added,
 * every paper size quietly falls back to the Legal version for that
 * orientation, so exports never break — drop in a correctly named file
 * later (e.g. pdf-bg-a4-landscape.jpg) and it will be picked up
 * automatically, no code changes required.
 */
class PdfLetterhead
{
    public const PAPERS = ['letter', 'legal', 'a4'];
    public const ORIENTATIONS = ['portrait', 'landscape'];

    // Physical page dimensions in inches, portrait orientation.
    private const SIZES = [
        'letter' => [8.5, 11],
        'legal'  => [8.5, 14],
        'a4'     => [8.27, 11.69],
    ];

    private const FALLBACK_PAPER = 'legal';

    /**
     * [width, height] in inches for the given paper + orientation.
     */
    public static function pageSize(?string $paper, ?string $orientation): array
    {
        $paper = self::normalizePaper($paper);
        $orientation = self::normalizeOrientation($orientation);

        [$width, $height] = self::SIZES[$paper];

        return $orientation === 'landscape' ? [$height, $width] : [$width, $height];
    }

    /**
     * Absolute filesystem path to the best-available background image for
     * the given paper + orientation, falling back to the Legal artwork for
     * that orientation if a paper-specific file hasn't been added yet.
     */
    public static function background(?string $paper, ?string $orientation): string
    {
        $paper = self::normalizePaper($paper);
        $orientation = self::normalizeOrientation($orientation);

        $preferred = public_path("images/pdf-bg-{$paper}-{$orientation}.jpg");

        if (file_exists($preferred)) {
            return $preferred;
        }

        return public_path('images/pdf-bg-' . self::FALLBACK_PAPER . "-{$orientation}.jpg");
    }

    public static function normalizePaper(?string $paper): string
    {
        return in_array($paper, self::PAPERS, true) ? $paper : self::FALLBACK_PAPER;
    }

    public static function normalizeOrientation(?string $orientation): string
    {
        return $orientation === 'landscape' ? 'landscape' : 'portrait';
    }
}
