<?php
// usage: php pcf_bisect.php REPORT_JSON WORKDIR PRISTINE_DIR LIST_OF_BROKEN_RELATIVE_PATHS_FILE
[$script, $reportFile, $work, $pristine, $listFile] = $argv;
$report = json_decode(file_get_contents($reportFile), true);
$applied = [];
foreach ($report['files'] ?? [] as $f) { $applied[$f['name']] = $f['appliedFixers'] ?? []; }
foreach (file($listFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $rel) {
    $fixers = $applied[$rel] ?? [];
    echo "FILE $rel\n  applied: ", implode(', ', $fixers), "\n";
    $culprits = [];
    foreach ($fixers as $fixer) {
        $tmp = sys_get_temp_dir().'/pcfb_'.getmypid().'.php';
        copy("$pristine/$rel", $tmp);
        $out = [];
        exec(sprintf('php php-cs-fixer fix %s --rules=%s --allow-risky=yes --using-cache=no --no-interaction -q 2>&1', escapeshellarg($tmp), escapeshellarg(json_encode([$fixer => true]))), $out, $rc);
        exec('php -l '.escapeshellarg($tmp).' 2>&1', $lint, $lrc);
        $lint = [];
        exec('php -l '.escapeshellarg($tmp).' 2>&1', $lint, $lrc);
        if (0 !== $lrc) { $culprits[] = $fixer.' ('.trim($lint[0] ?? '').')'; }
        @unlink($tmp);
    }
    echo '  culprits: ', $culprits ? implode('; ', $culprits) : '(none alone: interaction between fixers)', "\n";
}
