<?php

namespace App\Support\LessonPdf;

/**
 * Turns a PDF on disk into pages + figures + text.
 *
 * The interface exists so the test suite can bind a fake: extraction needs
 * python and PyMuPDF, while every other behaviour of the import (status
 * transitions, file placement, tenant binding, the AI refusal) must be
 * testable on a bare box.
 */
interface PdfExtractor
{
    /**
     * @param  string  $pdfAbsolutePath  absolute path to the source PDF (read-only)
     * @param  string  $pagesDir  absolute directory the page PNGs are written to
     * @param  string  $figuresDir  absolute directory the figure crops are written to
     */
    public function extract(string $pdfAbsolutePath, string $pagesDir, string $figuresDir): ExtractionResult;
}
