<?php
/**
 * CNC SOP letterhead markup (type on the letter, or read-only after save).
 */

function cncSopLetterCss()
{
    return <<<'CSS'
.sop-sheet-wrap { max-width: 210mm; margin: 0 auto; }
.sop-sheet {
    min-height: 260mm;
    background: #fff;
    box-shadow: 0 8px 28px rgba(15, 23, 42, .12);
}
.sop-kicker {
    margin: 0 0 4px;
    text-align: center;
    font-size: 12px;
    letter-spacing: .16em;
    font-weight: 800;
    color: #9f1239;
}
.sop-doc-title {
    margin: 0 0 14px;
    text-align: center;
    font-size: 22px;
    font-weight: 800;
    letter-spacing: .04em;
    color: #111827;
    text-transform: uppercase;
}
.sop-field { margin: 0 0 10px; }
.sop-field label, .sop-meta-label {
    display: block;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: #64748b;
    margin-bottom: 2px;
}
.sop-line, .sop-body, .sop-read {
    width: 100%;
    box-sizing: border-box;
    border: 0;
    border-bottom: 1px solid #cbd5e1;
    background: transparent;
    font-family: Calibri, Candara, "Segoe UI", Arial, sans-serif;
    font-size: 15px;
    font-weight: 700;
    color: #0f172a;
    padding: 4px 2px;
}
.sop-line:focus, .sop-body:focus { outline: none; border-bottom-color: #d2232a; }
.sop-meta {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px 24px;
    margin-bottom: 8px;
}
.sop-read { border-bottom: 0; padding: 0; white-space: pre-wrap; }
.sop-body {
    min-height: 220px;
    resize: vertical;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 10px 12px;
    line-height: 1.55;
    font-weight: 650;
}
.sop-body-read {
    min-height: 120px;
    white-space: pre-wrap;
    font-size: 15px;
    line-height: 1.6;
    font-weight: 700;
    color: #0f172a;
}
.sop-sign-row {
    margin-top: 28px;
    display: flex;
    justify-content: flex-end;
}
.sop-sign-box {
    width: 280px;
    text-align: center;
}
.sop-sign-box .sop-meta-label { text-align: center; margin-bottom: 8px; color: #111827; letter-spacing: .06em; }
.sop-pad-wrap {
    border: 1.5px dashed #94a3b8;
    border-radius: 8px;
    background: rgba(255,255,255,.72);
    height: 120px;
    position: relative;
}
.sop-pad-wrap canvas {
    width: 100%;
    height: 120px;
    display: block;
    touch-action: none;
    cursor: crosshair;
}
.sop-sign-img {
    max-width: 260px;
    max-height: 110px;
    display: block;
    margin: 0 auto 6px;
}
.sop-sign-line {
    margin-top: 8px;
    border-top: 1.5px solid #0f172a;
    padding-top: 6px;
    font-size: 13px;
    font-weight: 800;
    color: #0f172a;
}
.sop-sign-empty { height: 72px; }
.sop-pad-actions { margin-top: 6px; display: flex; justify-content: center; }
.sop-clear-btn {
    border: 0;
    background: transparent;
    color: #b91c1c;
    font-weight: 700;
    cursor: pointer;
    font-size: 12px;
}
.sop-toolbar {
    max-width: 210mm;
    margin: 0 auto 12px;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
}
.sop-toolbar button, .sop-toolbar a {
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #0f172a;
    border-radius: 8px;
    padding: 8px 12px;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
    font-size: 13px;
}
.sop-toolbar .is-primary { background: #d2232a; color: #fff; border-color: #d2232a; }
@media print {
    .sop-toolbar, .sop-pad-actions, .no-print { display: none !important; }
    .sop-sheet { box-shadow: none; min-height: 0; }
    .sop-body, .sop-line { border: 0 !important; }
}
CSS;
}

function cncSopH($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function cncSopLetterInner(array $row, $editing = false)
{
    $title = (string) ($row['title'] ?? '');
    $date = (string) ($row['sop_date'] ?? '');
    $subject = (string) ($row['subject'] ?? '');
    $deptName = (string) ($row['department_name'] ?? '');
    $body = (string) ($row['body'] ?? '');
    $signer = (string) ($row['signer_name'] ?? '');
    $signature = trim((string) ($row['signature_data'] ?? ''));
    $dateShow = function_exists('formatDateDisplay') ? formatDateDisplay($date) : $date;
    if ($editing && $dateShow === '' && $date !== '') {
        $dateShow = $date;
    }

    $html = '<p class="sop-kicker">ARMOR STEEL INDUSTRIES</p>';
    $html .= '<h2 class="sop-doc-title">Standard Operating Procedure</h2>';

    if ($editing) {
        $html .= '<div class="sop-field"><label for="sopTitle">SOP Title</label>';
        $html .= '<input class="sop-line" id="sopTitle" name="title" required maxlength="255" value="' . cncSopH($title) . '" placeholder="SOP title"></div>';
        $html .= '<div class="sop-meta">';
        $html .= '<div class="sop-field"><label for="sopDate">Date</label>';
        $html .= '<input class="sop-line" id="sopDate" name="sop_date" required maxlength="10" value="' . cncSopH($dateShow) . '" placeholder="DD-MM-YYYY"></div>';
        $html .= '<div class="sop-field"><label for="sopDept">Department Name</label>';
        $html .= '<input class="sop-line" id="sopDept" name="department_name" required maxlength="150" value="' . cncSopH($deptName) . '"></div>';
        $html .= '</div>';
        $html .= '<div class="sop-field"><label for="sopSubject">Subject</label>';
        $html .= '<input class="sop-line" id="sopSubject" name="subject" required maxlength="500" value="' . cncSopH($subject) . '" placeholder="Subject"></div>';
        $html .= '<div class="sop-field"><label for="sopBody">Body</label>';
        $html .= '<textarea class="sop-body" id="sopBody" name="body" required placeholder="Write the SOP description here. Add as much as needed.">' . cncSopH($body) . '</textarea></div>';
    } else {
        $html .= '<div class="sop-field"><div class="sop-meta-label">SOP Title</div><div class="sop-read" style="font-size:18px;">' . cncSopH($title) . '</div></div>';
        $html .= '<div class="sop-meta">';
        $html .= '<div class="sop-field"><div class="sop-meta-label">Date</div><div class="sop-read">' . cncSopH($dateShow) . '</div></div>';
        $html .= '<div class="sop-field"><div class="sop-meta-label">Department Name</div><div class="sop-read">' . cncSopH($deptName) . '</div></div>';
        $html .= '</div>';
        $html .= '<div class="sop-field"><div class="sop-meta-label">Subject</div><div class="sop-read">' . cncSopH($subject) . '</div></div>';
        $html .= '<div class="sop-field"><div class="sop-meta-label">Body</div><div class="sop-body-read">' . cncSopH($body) . '</div></div>';
    }

    $html .= '<div class="sop-sign-row"><div class="sop-sign-box">';
    $html .= '<div class="sop-meta-label">Authorized Signature</div>';
    if ($editing) {
        $html .= '<div class="sop-pad-wrap"><canvas id="sopPad" width="640" height="240"></canvas></div>';
        $html .= '<div class="sop-pad-actions"><button type="button" class="sop-clear-btn" id="sopPadClear">Clear signature</button></div>';
        $html .= '<input class="sop-line" name="signer_name" maxlength="150" value="' . cncSopH($signer) . '" placeholder="Authorized person name" style="text-align:center;margin-top:8px;">';
        $html .= '<input type="hidden" name="signature_data" id="signatureData" value="">';
        $html .= '<input type="hidden" name="clear_signature" id="clearSignature" value="0">';
    } else {
        if ($signature !== '' && preg_match('#^data:image/png;base64,#', $signature)) {
            $html .= '<img class="sop-sign-img" src="' . cncSopH($signature) . '" alt="Authorized signature">';
        } else {
            $html .= '<div class="sop-sign-empty"></div>';
        }
        $html .= '<div class="sop-sign-line">' . ($signer !== '' ? cncSopH($signer) : 'Authorized Signatory') . '</div>';
    }
    $html .= '</div></div>';
    return $html;
}

function cncSopLetterSheet(array $row, array $brand, $editing = false)
{
    $class = function_exists('companyDocSheetClass')
        ? companyDocSheetClass($brand, 'sop-sheet cdoc-flow')
        : 'cdoc-sheet sop-sheet cdoc-flow';
    $html = '<div class="' . cncSopH($class) . '">';
    if (function_exists('companyDocRenderWatermark')) {
        $html .= companyDocRenderWatermark($brand);
    }
    $html .= '<div class="cdoc-inner">';
    if (function_exists('companyDocRenderHeader')) {
        $html .= companyDocRenderHeader($brand, 'CNC Department');
    }
    $html .= cncSopLetterInner($row, $editing);
    if (function_exists('companyDocRenderFooter')) {
        $html .= companyDocRenderFooter($brand);
    }
    $html .= '</div></div>';
    return $html;
}
