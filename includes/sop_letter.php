<?php
/**
 * CNC SOP letterhead markup (type on the letter, or read-only after save).
 */

function cncSopLetterCss()
{
    return <<<'CSS'
.sop-sheet-wrap { max-width: 210mm; margin: 0 auto; }
.sop-sheet {
    width: 210mm;
    max-width: 100%;
    min-height: 297mm;
    margin: 0 auto;
    background: #fff;
    position: relative;
    overflow: visible;
}
.sop-date {
    text-align: right;
    font-size: 16px;
    font-weight: 800;
    color: #111;
    margin: 0 0 14px;
}
.sop-subject {
    text-align: center;
    font-size: 20px;
    font-weight: 800;
    color: #111;
    text-decoration: underline;
    text-underline-offset: 4px;
    margin: 0 0 16px;
}
.sop-name {
    font-size: 16px;
    font-weight: 800;
    color: #111;
    margin: 0 0 12px;
}
.sop-body-box {
    flex: 1 1 auto;
    min-height: 70mm;
    border: 0;
    padding: 4px 2px 8px;
    white-space: pre-wrap;
    font-size: 16px;
    font-weight: 800;
    color: #111;
    line-height: 1.55;
}
.sop-signs {
    display: flex;
    justify-content: space-between;
    gap: 28px;
    margin-top: 16px;
}
.sop-sign-slot {
    width: 42%;
    min-height: 36mm;
    border: 1.5px solid #111;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-end;
    padding: 8px 10px 10px;
    text-align: center;
    font-size: 15px;
    font-weight: 800;
    color: #111;
}
.sop-sign-slot img {
    max-width: 100%;
    max-height: 24mm;
    object-fit: contain;
    margin-bottom: 6px;
}
.sop-sign-slot .who {
    font-size: 15px;
    font-weight: 800;
    color: #111;
    margin-bottom: 2px;
}
.sop-sheet .cdoc-inner {
    min-height: 297mm;
    display: flex;
    flex-direction: column;
    box-sizing: border-box;
}
.sop-toolbar {
    max-width: 210mm;
    margin: 0 auto 10px;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
    background: #fff;
    border: 1px solid #cbd5e1;
    padding: 8px 12px;
}
.sop-toolbar button, .sop-toolbar a {
    display: inline-block;
    background: #d2232a;
    color: #fff;
    border: 0;
    padding: 8px 12px;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    font-family: Calibri, Candara, "Segoe UI", Arial, sans-serif;
    font-size: 12px;
    border-radius: 0;
}
.sop-toolbar a.secondary, .sop-toolbar button.secondary { background: #475569; }
@media print {
    .sop-toolbar, .no-print { display: none !important; }
    .sop-sheet {
        width: 210mm;
        max-width: none;
        min-height: 285mm;
        margin: 0 auto;
        box-shadow: none;
    }
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

    $html = '';
    if ($editing) {
        $html .= '<div class="sop-field"><label for="sopTitle">Agreement Title</label>';
        $html .= '<input class="sop-line" id="sopTitle" name="title" required maxlength="255" value="' . cncSopH($title) . '" placeholder="Agreement title"></div>';
        $html .= '<div class="sop-meta">';
        $html .= '<div class="sop-field"><label for="sopDate">Date</label>';
        $html .= '<input class="sop-line" id="sopDate" name="sop_date" required maxlength="10" value="' . cncSopH($dateShow) . '" placeholder="DD-MM-YYYY"></div>';
        $html .= '<div class="sop-field"><label for="sopDept">Department Name</label>';
        $html .= '<input class="sop-line" id="sopDept" name="department_name" required maxlength="150" value="' . cncSopH($deptName) . '"></div>';
        $html .= '</div>';
        $html .= '<div class="sop-field"><label for="sopSubject">Subject</label>';
        $html .= '<input class="sop-line" id="sopSubject" name="subject" required maxlength="500" value="' . cncSopH($subject) . '" placeholder="Subject"></div>';
        $html .= '<div class="sop-field"><label for="sopBody">Body</label>';
        $html .= '<textarea class="sop-body" id="sopBody" name="body" required placeholder="Write the agreement description here. Add as much as needed.">' . cncSopH($body) . '</textarea></div>';
        return $html;
    }

    $html .= '<div class="sop-date">Date : ' . cncSopH($dateShow) . '</div>';
    $html .= '<div class="sop-subject">Subject : ' . cncSopH($subject) . '</div>';
    $html .= '<div class="sop-name">Name : ' . cncSopH($deptName) . '</div>';
    $html .= '<div class="sop-body-box">' . cncSopH($body) . '</div>';
    $html .= '<div class="sop-signs">';
    $html .= '<div class="sop-sign-slot"><span>Contractor Sign</span></div>';
    $html .= '<div class="sop-sign-slot">';
    if ($signature !== '' && preg_match('#^data:image/png;base64,#', $signature)) {
        $html .= '<img src="' . cncSopH($signature) . '" alt="Signature">';
    }
    if ($signer !== '') {
        $html .= '<div class="who">' . cncSopH($signer) . '</div>';
    }
    $html .= '<span>Signature</span></div>';
    $html .= '</div>';
    return $html;
}

function cncSopLetterSheet(array $row, array $brand, $editing = false)
{
    $class = function_exists('companyDocSheetClass')
        ? companyDocSheetClass($brand, 'sop-sheet')
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
