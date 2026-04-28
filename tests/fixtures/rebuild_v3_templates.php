<?php

declare(strict_types=1);

/**
 * Rebuild the "V3" target templates from the "final" V2 originals in
 * /wwwroot/hhff/word-files/*.docx.
 *
 * Why this script and not editing in Word:
 *   - Word splits {{placeholder}} across multiple <w:r> runs (often 2-6 runs
 *     per placeholder, depending on autocorrect history). Renaming by hand in
 *     Word is error-prone.
 *   - We want the V3 build to be reproducible and diffable.
 *
 * What it does:
 *   1. Unzip each source DOCX into a temp dir.
 *   2. For every <w:p> paragraph that contains a {{...}} we want to rewrite:
 *      a. Concatenate all <w:t> inner texts inside the paragraph.
 *      b. Apply the rename map (OLD → NEW).
 *      c. Put the rewritten text entirely into the FIRST <w:t>, empty the
 *         rest. Paragraphs that contain placeholders are always uniformly
 *         styled (the AI doesn't bold a placeholder name inline), so we don't
 *         lose visible formatting.
 *   3. Repack the DOCX into /wwwroot/hhff/word-files/v3/.
 *
 * Usage:
 *   php tests/fixtures/rebuild_v3_templates.php
 *
 * The script is idempotent; re-run to regenerate v3 files from scratch.
 */

$SOURCES = [
    [
        'src' => '/wwwroot/hhff/word-files/Profil hhff Deutsch Ralf final.docx',
        'dst' => '/wwwroot/hhff/word-files/v3/Profil hhff DE v3.docx',
        'variant' => 'hhff',
    ],
    [
        'src' => '/wwwroot/hhff/word-files/Profile hhff englisch Ralf final.docx',
        'dst' => '/wwwroot/hhff/word-files/v3/Profil hhff EN v3.docx',
        'variant' => 'hhff',
    ],
    [
        'src' => '/wwwroot/hhff/word-files/Profil (Needle  Haystack) Deutsch Ralf final.docx',
        'dst' => '/wwwroot/hhff/word-files/v3/Profil NeedleHaystack DE v3.docx',
        'variant' => 'nh',
    ],
    [
        'src' => '/wwwroot/hhff/word-files/Profil (Needle  Haystack) English Ralf final.docx',
        'dst' => '/wwwroot/hhff/word-files/v3/Profil NeedleHaystack EN v3.docx',
        'variant' => 'nh',
    ],
];

/**
 * Every list variable must live in a canonical bullet paragraph so the generator's
 * list-expansion pass produces proper bullets instead of stacked prose or — worse —
 * cloned heading lines. We fix that at build time by copying the `<w:pPr>` from
 * the template's own known-good `{{benefits}}` paragraph onto every other list
 * placeholder's host paragraph. Benefits is chosen because both V2 originals got
 * it right (N&H uses `pStyle=Bulletpoints`, hhff uses `Listenabsatz` + numPr).
 *
 * Keys are the V3 variable names (post-rename).
 */
$LIST_KEYS = [
    'benefits',
    'languages',
    'other_skills',
    'relevant_positions',
    'relevant_positions_for_target',
    'education',
];

/**
 * Placeholder rename map applied to BOTH variants.
 *
 * Order matters: longer keys first so `stations.positions.N` is rewritten
 * before a generic `positions` key could collide (none today, but future-proof).
 *
 * The map uses raw placeholder names (without braces). We add braces at
 * substitution time.
 */
$COMMON_MAP = [
    // Stations rename (N&H only; harmless no-op on hhff)
    'stations.positions.N'       => 'stations.position.N',

    // List suffix cleanup
    'relevantfortargetposlist'   => 'relevant_positions_for_target',
    'relevantposlist'            => 'relevant_positions',
    'otherskillslist'            => 'other_skills',
    'languageslist'              => 'languages',
    'benefitslist'               => 'benefits',

    // Naming cleanups
    'currentansalary'            => 'current_annual_salary',
    'currentposition'            => 'current_position',
    'noticeperiod'               => 'notice_period',
    'workinghours'               => 'working_hours',
    'target-position'            => 'target_position',

    // Address reshape
    'address1'                   => 'street',
    'address2'                   => 'city',

    // Renames
    'number'                     => 'phone',
    'month'                      => 'generated_month',
    'year'                       => 'generated_year',

    // hhff-only "travelorcommute" → "commute" (we drop the "travel" side from hhff
    // because the original text only had one combined value; ANALYSIS-v3.md
    // documents this as a lossy rename).
    'travelorcommute'            => 'commute',
];

/**
 * Checkbox labels per variant and per language. The rewrite pass produces
 * three rows: "<label> <yes/no glyph pair>" for moving / commute / travel.
 * Values are chosen to match the wording Ralf uses in the real customer
 * profiles (Findeisen DE uses "Umzugsbereitschaft / Pendelbereitschaft /
 * Reisebereitschaft"; Fabri EN uses "Willingness to relocate / Willingness
 * to commute / Willingness to travel").
 */
$CHECKBOX_LABELS = [
    'hhff' => [
        'de' => [
            'moving'  => 'Umzugsbereitschaft',
            'commute' => 'Pendelbereitschaft',
            'travel'  => 'Reisebereitschaft',
            'yes'     => 'Ja',
            'no'      => 'Nein',
        ],
        'en' => [
            'moving'  => 'Willingness to relocate',
            'commute' => 'Willingness to commute',
            'travel'  => 'Willingness to travel',
            'yes'     => 'Yes',
            'no'      => 'No',
        ],
    ],
];

foreach ($SOURCES as $job) {
    rebuildTemplate(
        $job['src'],
        $job['dst'],
        $COMMON_MAP,
        $LIST_KEYS,
        $job['variant'],
        detectLangFromPath($job['dst']),
        $CHECKBOX_LABELS,
    );
}

fprintf(STDOUT, "\nV3 build complete.\n");

// -------------------------------------------------------------------------

function detectLangFromPath(string $path): string
{
    $base = strtolower(basename($path));
    // Filenames are "… DE v3.docx" / "… EN v3.docx".
    if (strpos($base, ' de v3') !== false || strpos($base, 'deutsch') !== false) {
        return 'de';
    }
    if (strpos($base, ' en v3') !== false || strpos($base, 'english') !== false || strpos($base, 'englisch') !== false) {
        return 'en';
    }
    return 'de';
}

function rebuildTemplate(
    string $srcPath,
    string $dstPath,
    array $renameMap,
    array $listKeys,
    string $variant = 'hhff',
    string $lang = 'de',
    array $checkboxLabels = [],
): void {
    if (!is_file($srcPath)) {
        fprintf(STDERR, "SKIP %s (not found)\n", $srcPath);
        return;
    }

    $tmpDir = sys_get_temp_dir() . '/tx-v3-' . bin2hex(random_bytes(4));
    mkdir($tmpDir, 0o777, true);

    $zip = new ZipArchive();
    if ($zip->open($srcPath) !== true) {
        fprintf(STDERR, "FAIL to open %s\n", $srcPath);
        return;
    }
    $zip->extractTo($tmpDir);
    $zip->close();

    $docXml = $tmpDir . '/word/document.xml';
    if (!is_file($docXml)) {
        fprintf(STDERR, "FAIL: no word/document.xml in %s\n", $srcPath);
        return;
    }

    $xml = file_get_contents($docXml);
    [$newXml, $stats] = rewritePlaceholders($xml, $renameMap);
    [$newXml, $listStats] = normalizeListParagraphs($newXml, $listKeys);

    $cbStats = ['rewritten' => 0, 'skipped' => 'not a candidate variant'];
    if ($variant === 'hhff' && !empty($checkboxLabels['hhff'][$lang])) {
        [$newXml, $cbStats] = rewriteHhffCheckboxParagraphs($newXml, $checkboxLabels['hhff'][$lang]);
    }

    file_put_contents($docXml, $newXml);
    $stats['list_paragraphs_normalised'] = $listStats['normalised'];
    $stats['list_pPr_source'] = $listStats['source'];
    $stats['checkbox_rows_written'] = $cbStats['rewritten'] ?? 0;

    if (!is_dir(dirname($dstPath))) {
        mkdir(dirname($dstPath), 0o777, true);
    }
    if (file_exists($dstPath)) {
        unlink($dstPath);
    }

    $out = new ZipArchive();
    if ($out->open($dstPath, ZipArchive::CREATE) !== true) {
        fprintf(STDERR, "FAIL to write %s\n", $dstPath);
        return;
    }
    $rii = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($tmpDir, RecursiveDirectoryIterator::SKIP_DOTS),
    );
    foreach ($rii as $file) {
        if ($file->isDir()) {
            continue;
        }
        $rel = substr($file->getPathname(), strlen($tmpDir) + 1);
        $rel = str_replace('\\', '/', $rel);
        $out->addFile($file->getPathname(), $rel);
    }
    $out->close();

    // Cleanup
    removeDir($tmpDir);

    $name = basename($dstPath);
    fprintf(
        STDOUT,
        "OK  %-38s  renamed=%d paragraphs=%d list_paras_normalised=%d cb_rows=%d (pPr from %s)\n",
        $name,
        $stats['placeholders_renamed'],
        $stats['paragraphs_rewritten'],
        $stats['list_paragraphs_normalised'],
        $stats['checkbox_rows_written'],
        $stats['list_pPr_source'] ?? 'none',
    );
}

/**
 * Rewrite the hhff-variant "Umzugsbereitschaft / Pendel-/Reisebereitschaft"
 * block.
 *
 * The V2 hhff template carries two paragraphs here:
 *   1) label paragraph:  "Umzugsbereitschaft<tab×N>Pendel-/Reisebereitschaft"
 *   2) values paragraph: "{{moving}}{{commute}}" (no {{travel}} — a V2 loss
 *      noted in ANALYSIS-v3.md).
 *
 * Those two paragraphs become three rows after this rewrite, each matching
 * the paired-glyph pattern the real customer profiles (Findeisen) use:
 *
 *   <label>  {{checkb.X.yes}} <yes>  {{checkb.X.no}} <no>
 *
 * where X ∈ {moving, commute, travel}. The plugin's `processCheckboxes`
 * swaps the two placeholders to `☒` / `☐` glyphs at generation time.
 *
 * We keep the replacement tightly scoped: only paragraphs whose flat text is
 * exactly "UmzugsbereitschaftPendel-/Reisebereitschaft" (labels) or starts
 * with "{{moving}}{{commute}}" (values) are touched. Other paragraphs pass
 * through untouched so the pass is safe to re-run.
 *
 * @param string                $xml    full document.xml (post placeholder rename)
 * @param array<string, string> $labels { moving, commute, travel, yes, no }
 * @return array{0: string, 1: array{rewritten: int}}
 */
function rewriteHhffCheckboxParagraphs(string $xml, array $labels): array
{
    $paraPattern = '~<w:p\b[^>]*>.*?</w:p>~s';

    // Find a reference paragraph to reuse its <w:pPr> and run <w:rPr> so the
    // new paragraphs blend into the template's Arial/24pt/black styling.
    // We use the paragraph that hosts "{{moving}}{{commute}}" because it
    // already carries the correct non-bold run properties.
    $refPPr = null;
    $refRPr = null;

    if (preg_match_all($paraPattern, $xml, $paras, PREG_OFFSET_CAPTURE)) {
        foreach ($paras[0] as [$paraXml]) {
            $flat = '';
            if (preg_match_all('~<w:t[^>]*>([^<]*)</w:t>~', $paraXml, $tm)) {
                $flat = implode('', $tm[1]);
            }
            if ($flat === '' || strpos($flat, '{{moving}}{{commute}}') !== 0) {
                continue;
            }
            if (preg_match('~<w:pPr\b[^>]*>.*?</w:pPr>~s', $paraXml, $pm)) {
                $refPPr = $pm[0];
            }
            if (preg_match('~<w:r\b[^>]*>\s*<w:rPr\b[^>]*>.*?</w:rPr>~s', $paraXml, $rm)
                && preg_match('~<w:rPr\b[^>]*>.*?</w:rPr>~s', $rm[0], $rpr)) {
                $refRPr = $rpr[0];
            }
            break;
        }
    }

    // Fallback minimal styles if the reference paragraph can't be located —
    // keeps Word from choking on unstyled runs.
    if ($refPPr === null) {
        $refPPr = '<w:pPr><w:jc w:val="both"/></w:pPr>';
    }
    if ($refRPr === null) {
        $refRPr = '<w:rPr><w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial"/><w:sz w:val="24"/><w:szCs w:val="24"/></w:rPr>';
    }

    // Build a paragraph for one checkbox row. Underlined bold label at the
    // start (matching how the real customer profiles render it), then a few
    // tabs, then the yes/no glyph-pair placeholders separated by spaces.
    $boldUnderlineRPr = '<w:rPr><w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial"/><w:b/><w:sz w:val="24"/><w:szCs w:val="24"/><w:u w:val="single"/><w:lang w:val="de-DE"/></w:rPr>';

    $buildRow = static function (string $label, string $key, string $yes, string $no) use ($refPPr, $refRPr, $boldUnderlineRPr): string {
        $escLabel = htmlspecialchars($label, ENT_XML1 | ENT_QUOTES);
        $escYes = htmlspecialchars($yes, ENT_XML1 | ENT_QUOTES);
        $escNo = htmlspecialchars($no, ENT_XML1 | ENT_QUOTES);

        return '<w:p>' . $refPPr
            . '<w:r>' . $boldUnderlineRPr . '<w:t xml:space="preserve">' . $escLabel . '</w:t></w:r>'
            . '<w:r>' . $refRPr . '<w:tab/><w:tab/><w:tab/></w:r>'
            . '<w:r>' . $refRPr . '<w:t xml:space="preserve">{{checkb.' . $key . '.yes}} ' . $escYes . '     {{checkb.' . $key . '.no}} ' . $escNo . '</w:t></w:r>'
            . '</w:p>';
    };

    $newRows =
        $buildRow($labels['moving'],  'moving',  $labels['yes'], $labels['no'])
      . $buildRow($labels['commute'], 'commute', $labels['yes'], $labels['no'])
      . $buildRow($labels['travel'],  'travel',  $labels['yes'], $labels['no']);

    $rewritten = 0;

    // The V2 label paragraph differs per language and per revision. Rather
    // than hard-coding exact strings, match any paragraph whose flat text
    // contains BOTH a word starting with "Umzug…" or "Willingness to
    // relocate" AND one of the right-column phrases ("Pendel", "commute",
    // "travel", "Regional flexibility"). That covers the current V2 shapes
    // in the private hhff repo plus any small future wording tweaks.
    $labelLeftNeedles  = ['Umzugsbereitschaft', 'Willingness to relocate'];
    $labelRightNeedles = ['Pendelbereitschaft', 'Pendel-/Reisebereitschaft', 'Regional flexibility', 'Willingness to travel', 'Willingness to commute'];

    $xml = preg_replace_callback(
        $paraPattern,
        function (array $m) use (&$rewritten, $newRows, $labelLeftNeedles, $labelRightNeedles): string {
            $paraXml = $m[0];
            $flat = '';
            if (preg_match_all('~<w:t[^>]*>([^<]*)</w:t>~', $paraXml, $tm)) {
                $flat = implode('', $tm[1]);
            }
            // Skip paragraphs that contain other content (e.g. adjacent text
            // runs with addresses, salary numbers); we only want the two-
            // labels-only paragraph.
            if (strlen($flat) > 120 || strpos($flat, '{{') !== false) {
                return $paraXml;
            }
            $leftHit = false;
            foreach ($labelLeftNeedles as $n) {
                if (strpos($flat, $n) !== false) { $leftHit = true; break; }
            }
            if (!$leftHit) {
                return $paraXml;
            }
            $rightHit = false;
            foreach ($labelRightNeedles as $n) {
                if (strpos($flat, $n) !== false) { $rightHit = true; break; }
            }
            if (!$rightHit) {
                return $paraXml;
            }
            $rewritten++;
            return $newRows;
        },
        $xml,
    );

    // Pass 2: delete any paragraph whose flat text is essentially just
    // "{{moving}}{{commute}}" (with optional whitespace / separators) — the
    // new rows inserted above already carry those placeholders, so leaving
    // this paragraph in place would render them a second time.
    $xml = preg_replace_callback(
        $paraPattern,
        function (array $m): string {
            $paraXml = $m[0];
            $flat = '';
            if (preg_match_all('~<w:t[^>]*>([^<]*)</w:t>~', $paraXml, $tm)) {
                $flat = implode('', $tm[1]);
            }
            if (strpos($flat, '{{moving}}') === false || strpos($flat, '{{commute}}') === false) {
                return $paraXml;
            }
            $stripped = preg_replace('~\{\{moving\}\}|\{\{commute\}\}|\s~u', '', $flat);
            if ($stripped !== '' && $stripped !== null) {
                // Paragraph carries other text beyond whitespace + those two
                // placeholders — leave it alone, it's not the V2 values
                // paragraph.
                return $paraXml;
            }
            return '';
        },
        $xml,
    );

    return [$xml, ['rewritten' => $rewritten]];
}

/**
 * Normalise every list placeholder's host paragraph to use a canonical bullet
 * paragraph style: clone the `<w:pPr>` from the paragraph that hosts `{{benefits}}`
 * (both V2 originals ship benefits as a correctly-styled bullet paragraph) and
 * drop that pPr onto every other list placeholder's paragraph.
 *
 * Why this matters: the generator's Phase A pre-pass (expandListParagraphs)
 * clones the host paragraph once per list item. If the host isn't a bullet
 * paragraph — plain prose or a Heading/Titel style — every item renders without
 * a bullet (or as a huge title). Forcing a known-good pPr here gives us a
 * consistent list rendering across all list variables without depending on the
 * original template author's discipline.
 *
 * @param string       $xml      full document.xml (already placeholder-renamed)
 * @param list<string> $listKeys V3 list variable names (without braces)
 * @return array{0: string, 1: array{normalised: int, source: string}}
 */
function normalizeListParagraphs(string $xml, array $listKeys): array
{
    // 1. Find the benefits paragraph and extract its <w:pPr>.
    //    The placeholder may be split across runs at this point too, so we
    //    use the same per-paragraph flat-text scan that the controller uses.
    $benefitsPPr = null;
    $paraPattern = '~<w:p\b[^>]*>.*?</w:p>~s';
    if (preg_match_all($paraPattern, $xml, $paras, PREG_OFFSET_CAPTURE)) {
        foreach ($paras[0] as [$paraXml, $paraOff]) {
            $flat = '';
            if (preg_match_all('~<w:t[^>]*>([^<]*)</w:t>~', $paraXml, $tm)) {
                $flat = implode('', $tm[1]);
            }
            if (strpos($flat, '{{benefits}}') !== false) {
                if (preg_match('~<w:pPr\b[^>]*>.*?</w:pPr>~s', $paraXml, $pprMatch)) {
                    $benefitsPPr = $pprMatch[0];
                }
                break;
            }
        }
    }

    if ($benefitsPPr === null) {
        return [$xml, ['normalised' => 0, 'source' => 'none (no {{benefits}} paragraph found)']];
    }

    // 2. For every list key that isn't benefits, locate its host paragraph and
    //    replace that paragraph's <w:pPr> with the canonical one. If the host
    //    has no <w:pPr> at all, inject one right after <w:p …>.
    $normalised = 0;
    $needles = [];
    foreach ($listKeys as $k) {
        if ($k === 'benefits') {
            continue;
        }
        $needles[$k] = '{{' . $k . '}}';
    }

    $xml = preg_replace_callback(
        $paraPattern,
        function (array $m) use ($needles, $benefitsPPr, &$normalised): string {
            $paraXml = $m[0];

            $flat = '';
            if (preg_match_all('~<w:t[^>]*>([^<]*)</w:t>~', $paraXml, $tm)) {
                $flat = implode('', $tm[1]);
            }
            $hit = null;
            foreach ($needles as $k => $needle) {
                if (strpos($flat, $needle) !== false) {
                    $hit = $k;
                    break;
                }
            }
            if ($hit === null) {
                return $paraXml;
            }

            // Rewrite or inject <w:pPr>.
            if (preg_match('~<w:pPr\b[^>]*>.*?</w:pPr>~s', $paraXml)) {
                $rewritten = preg_replace(
                    '~<w:pPr\b[^>]*>.*?</w:pPr>~s',
                    addcslashes($benefitsPPr, '\\$'),
                    $paraXml,
                    1,
                );
            } else {
                $rewritten = preg_replace(
                    '~(<w:p\b[^>]*>)~',
                    '$1' . addcslashes($benefitsPPr, '\\$'),
                    $paraXml,
                    1,
                );
            }
            if (is_string($rewritten) && $rewritten !== $paraXml) {
                $normalised++;
                return $rewritten;
            }
            return $paraXml;
        },
        $xml,
    );

    return [$xml, ['normalised' => $normalised, 'source' => '{{benefits}} host paragraph']];
}

/**
 * @param string                $xml       full document.xml
 * @param array<string, string> $renameMap OLD key → NEW key (without braces)
 * @return array{0: string, 1: array{paragraphs_rewritten: int, placeholders_renamed: int}}
 */
function rewritePlaceholders(string $xml, array $renameMap): array
{
    $paragraphsRewritten = 0;
    $renameCount = 0;

    // Iterate <w:p>...</w:p> blocks. Regex is sufficient because they don't
    // legally nest inside each other in a flowing document.
    $xml = preg_replace_callback(
        '~<w:p\b[^>]*>.*?</w:p>~s',
        function (array $m) use ($renameMap, &$paragraphsRewritten, &$renameCount): string {
            $paragraph = $m[0];

            // Gather <w:t>...</w:t> nodes in document order.
            if (!preg_match_all('~<w:t(?P<attrs>[^>]*)>(?P<text>[^<]*)</w:t>~', $paragraph, $tMatches, PREG_OFFSET_CAPTURE)) {
                return $paragraph;
            }

            $runs = [];
            $flat = '';
            foreach ($tMatches[0] as $i => [$full, $off]) {
                $text = $tMatches['text'][$i][0];
                $attrs = $tMatches['attrs'][$i][0];
                $runs[] = [
                    'full'        => $full,
                    'offset'      => $off,
                    'length'      => strlen($full),
                    'attrs'       => $attrs,
                    'inner'       => $text,
                    'flat_start'  => strlen($flat),
                    'flat_length' => strlen($text),
                ];
                $flat .= $text;
            }

            // Quick skip: no placeholder in this paragraph at all.
            if (strpos($flat, '{{') === false) {
                return $paragraph;
            }

            // Apply rename map to flat text.
            $newFlat = $flat;
            $touched = false;
            foreach ($renameMap as $old => $new) {
                $needle = '{{' . $old . '}}';
                $replacement = '{{' . $new . '}}';
                $count = 0;
                $newFlat = str_replace($needle, $replacement, $newFlat, $count);
                if ($count > 0) {
                    $touched = true;
                    $renameCount += $count;
                }
            }

            if (!$touched) {
                return $paragraph;
            }
            $paragraphsRewritten++;

            // Drop the whole rewritten text into the FIRST <w:t>, empty the rest.
            $replaced = [];
            foreach ($runs as $i => $r) {
                if ($i === 0) {
                    // Always keep xml:space="preserve" so trailing/leading spaces survive.
                    $attrs = $r['attrs'];
                    if (!preg_match('~\sxml:space\s*=~', $attrs)) {
                        $attrs .= ' xml:space="preserve"';
                    }
                    $replaced[] = '<w:t' . $attrs . '>' . htmlspecialchars($newFlat, ENT_XML1 | ENT_QUOTES) . '</w:t>';
                } else {
                    $replaced[] = '<w:t' . $r['attrs'] . '></w:t>';
                }
            }

            // Rebuild paragraph by replacing each original <w:t> with its new version.
            // Walk from end so offsets in $runs stay valid.
            $newParagraph = $paragraph;
            for ($i = count($runs) - 1; $i >= 0; $i--) {
                $r = $runs[$i];
                $newParagraph = substr($newParagraph, 0, $r['offset']) . $replaced[$i] . substr($newParagraph, $r['offset'] + $r['length']);
            }

            return $newParagraph;
        },
        $xml,
    );

    return [$xml, ['paragraphs_rewritten' => $paragraphsRewritten, 'placeholders_renamed' => $renameCount]];
}

function removeDir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($dir);
}
