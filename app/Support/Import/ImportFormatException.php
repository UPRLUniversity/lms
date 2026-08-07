<?php

namespace App\Support\Import;

/**
 * The uploaded file could not be read at all — wrong format, empty, or missing a
 * required column. Distinct from a per-row problem: there is nothing to preview, so the
 * human goes back to the upload screen with this message rather than to a preview table.
 */
class ImportFormatException extends \RuntimeException {}
