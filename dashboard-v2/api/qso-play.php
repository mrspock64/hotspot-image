<?php
// Pure pass-through to production's own dashboard/qsolog/play.php --
// that file is already a complete, self-contained streaming endpoint
// (filename validation, Range support for <audio> seeking/fast-start,
// see its own header comment for why Range mattered live) with nothing
// v1-specific in it beyond its own __DIR__-relative require of
// qso_recorder.php, which still resolves correctly from here. Reused
// as-is rather than reimplemented -- same module contract every other
// dashboard-v2 api/*.php file follows.
require __DIR__ . '/../../dashboard/qsolog/play.php';
