<?php
/**
 * Shared print / PDF document shell:
 * Approved letterhead (full page) OR Logo header + Header Details | Body | Footer Details + logo watermark
 */

require_once __DIR__ . '/settings.php';

/**
 * Branding payload for print documents
 */
function getCompanyDocumentBranding()
{
    $company = trim((string) getCompanyName());
    if ($company === '') {
        $company = 'Armor Fire';
    }

    $logo = getLoginLogo();
    if (!$logo && function_exists('getCompanyLogo')) {
        $logo = getCompanyLogo();
    }

    $logoSrc = $logo;
    if ($logoSrc && strpos($logoSrc, 'http') !== 0 && strpos($logoSrc, '/') !== 0) {
        if (function_exists('app_url')) {
            $logoSrc = app_url($logoSrc);
        } else {
            $logoSrc = '/' . ltrim($logoSrc, '/');
        }
    }

    $letterhead = trim((string) getCompanyLetterheadImage());
    $letterheadSrc = '';
    if ($letterhead !== '') {
        if (strpos($letterhead, 'http') === 0 || strpos($letterhead, '/') === 0) {
            $letterheadSrc = $letterhead;
        } elseif (function_exists('app_url')) {
            $letterheadSrc = app_url($letterhead);
        } else {
            $letterheadSrc = '/' . ltrim($letterhead, '/');
        }
    }

    return [
        'company_name'       => $company,
        'logo'               => $logo,
        'logo_src'           => $logoSrc,
        'header_details'     => getCompanyHeaderDetails(),
        'footer_details'     => getCompanyFooterDetails(),
        'letterhead_image'   => $letterhead,
        'letterhead_src'     => $letterheadSrc,
        'has_letterhead'     => $letterheadSrc !== '',
    ];
}

/**
 * Extra class for sheets when approved letterhead is active
 */
function companyDocSheetClass(array $brand = null, $extra = '')
{
    $brand = $brand ?: getCompanyDocumentBranding();
    $parts = ['cdoc-sheet'];
    if (!empty($brand['has_letterhead'])) {
        $parts[] = 'has-letterhead';
    }
    $extra = trim((string) $extra);
    if ($extra !== '') {
        $parts[] = $extra;
    }
    return implode(' ', $parts);
}

/**
 * Shared CSS for print documents (letterhead / header / footer / watermark)
 */
function companyDocPrintCss()
{
    return <<<'CSS'
.cdoc-sheet {
    position: relative;
    background: #fff;
    overflow: hidden;
}
/* Default: letterhead fills each discrete A4 page box (employees / letters) */
.cdoc-letterhead {
    position: absolute;
    inset: 0;
    z-index: 0;
    pointer-events: none;
    background-repeat: no-repeat;
    background-position: center top;
    background-size: 100% 100%;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}
.cdoc-letterhead img {
    display: none !important;
}
.cdoc-sheet.has-letterhead,
.cdoc-sheet:has(.cdoc-letterhead) {
    padding: 0 !important;
    border: 0 !important;
    box-shadow: none !important;
    outline: none !important;
}
.cdoc-sheet.has-letterhead > .cdoc-inner,
.cdoc-sheet.has-letterhead .cdoc-inner,
.cdoc-sheet:has(.cdoc-letterhead) > .cdoc-inner,
.cdoc-sheet:has(.cdoc-letterhead) .cdoc-inner {
    position: relative !important;
    z-index: 1;
    box-sizing: border-box !important;
    padding: 42mm 14mm 32mm 14mm !important;
}
.cdoc-sheet.has-letterhead .cdoc-watermark,
.cdoc-sheet.has-letterhead .cdoc-header,
.cdoc-sheet.has-letterhead .cdoc-footer,
.cdoc-sheet:has(.cdoc-letterhead) .cdoc-watermark,
.cdoc-sheet:has(.cdoc-letterhead) .cdoc-header,
.cdoc-sheet:has(.cdoc-letterhead) .cdoc-footer {
    display: none !important;
}
/* Flow docs (long forms): header band on screen; fixed LH + @page on print */
.cdoc-sheet.cdoc-flow {
    overflow: visible !important;
    height: auto !important;
    max-height: none !important;
}
.cdoc-sheet.cdoc-flow .cdoc-letterhead {
    inset: auto;
    top: 0;
    left: 0;
    right: 0;
    height: 42mm;
    background-size: 210mm 297mm;
    background-position: top center;
    overflow: hidden;
}
.cdoc-sheet.cdoc-flow.has-letterhead > .cdoc-inner,
.cdoc-sheet.cdoc-flow.has-letterhead .cdoc-inner,
.cdoc-sheet.cdoc-flow:has(.cdoc-letterhead) > .cdoc-inner,
.cdoc-sheet.cdoc-flow:has(.cdoc-letterhead) .cdoc-inner {
    padding: 46mm 14mm 16mm 14mm !important;
    max-height: none !important;
    height: auto !important;
    overflow: visible !important;
}
.cdoc-watermark {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
    z-index: 0;
}
.cdoc-watermark img {
    width: min(62%, 420px);
    max-height: 420px;
    object-fit: contain;
    opacity: 0.08;
}
.cdoc-inner {
    position: relative;
    z-index: 1;
}
.cdoc-header {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding-bottom: 12px;
    border-bottom: 3px solid #d2232a;
    margin-bottom: 14px;
}
.cdoc-header-logo {
    width: 72px;
    height: 72px;
    object-fit: contain;
    flex-shrink: 0;
    border: 1px solid #e5e7eb;
    padding: 4px;
    background: #fff;
}
.cdoc-header-text {
    flex: 1;
    min-width: 0;
}
.cdoc-header-text .cdoc-company {
    margin: 0 0 6px;
    font-size: 22px;
    color: #b91c1c;
    font-family: Calibri, Candara, Segoe UI, Arial, Helvetica, sans-serif;
    font-weight: 800;
    line-height: 1.2;
    letter-spacing: .01em;
}
.cdoc-header-text .cdoc-header-details {
    margin: 0;
    font-size: 13px;
    line-height: 1.5;
    color: #1e293b;
    font-family: Calibri, Candara, Segoe UI, Arial, Helvetica, sans-serif;
    font-weight: 700;
    white-space: pre-wrap;
}
.cdoc-footer {
    margin-top: 22px;
    padding-top: 10px;
    border-top: 2px solid #d2232a;
    font-size: 10.5px;
    line-height: 1.45;
    color: #475569;
    font-family: Calibri, Candara, Segoe UI, Arial, Helvetica, sans-serif;
    white-space: pre-wrap;
    text-align: center;
}
@media print {
    /* Discrete pages: letterhead stays inside each page box */
    .cdoc-sheet.has-letterhead:not(.cdoc-flow) .cdoc-letterhead,
    .cdoc-sheet:has(.cdoc-letterhead):not(.cdoc-flow) .cdoc-letterhead {
        position: absolute !important;
        inset: 0 !important;
        background-size: 100% 100% !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    /* Flow docs: letterhead on every printed page; content fills then continues */
    .cdoc-sheet.cdoc-flow.has-letterhead .cdoc-letterhead,
    .cdoc-sheet.cdoc-flow:has(.cdoc-letterhead) .cdoc-letterhead {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        right: auto !important;
        width: 210mm !important;
        height: 297mm !important;
        background-size: 210mm 297mm !important;
        background-position: top center !important;
        background-repeat: no-repeat !important;
        overflow: visible !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .cdoc-sheet.has-letterhead,
    .cdoc-sheet:has(.cdoc-letterhead) {
        padding: 0 !important;
        border: 0 !important;
        box-shadow: none !important;
        outline: none !important;
    }
    .cdoc-sheet.cdoc-flow.has-letterhead,
    .cdoc-sheet.cdoc-flow:has(.cdoc-letterhead) {
        overflow: visible !important;
    }
    .cdoc-sheet.cdoc-flow.has-letterhead > .cdoc-inner,
    .cdoc-sheet.cdoc-flow.has-letterhead .cdoc-inner,
    .cdoc-sheet.cdoc-flow:has(.cdoc-letterhead) > .cdoc-inner,
    .cdoc-sheet.cdoc-flow:has(.cdoc-letterhead) .cdoc-inner {
        padding: 0 2mm !important;
    }
}
CSS;
}

/**
 * @page CSS. Pass $flow=true for long forms (content fills page, then next).
 */
function companyDocLetterheadPageCss(array $brand = null, $flow = false)
{
    $brand = $brand ?: getCompanyDocumentBranding();
    if ($flow && !empty($brand['has_letterhead'])) {
        return '@page { size: A4 portrait; margin: 42mm 12mm 34mm 12mm; }';
    }
    return '@page { size: A4 portrait; margin: 0; }';
}

/**
 * Full-page approved letterhead (when set in Company Setup)
 */
function companyDocRenderLetterhead(array $brand = null)
{
    $brand = $brand ?: getCompanyDocumentBranding();
    $src = trim((string) ($brand['letterhead_src'] ?? ''));
    if ($src === '') {
        return '';
    }
    return '<div class="cdoc-letterhead" style="background-image:url(\''
        . htmlspecialchars($src, ENT_QUOTES, 'UTF-8')
        . '\')" aria-hidden="true"></div>';
}

/**
 * Render logo watermark OR letterhead background
 */
function companyDocRenderWatermark(array $brand = null)
{
    $brand = $brand ?: getCompanyDocumentBranding();
    $lh = companyDocRenderLetterhead($brand);
    if ($lh !== '') {
        return $lh;
    }
    $src = trim((string) ($brand['logo_src'] ?? ''));
    if ($src === '') {
        return '';
    }
    return '<div class="cdoc-watermark" aria-hidden="true"><img src="'
        . htmlspecialchars($src, ENT_QUOTES, 'UTF-8')
        . '" alt=""></div>';
}

/**
 * Render document header (logo + company name + header details).
 * Skipped when approved letterhead image is active (branding is on the image).
 *
 * @param array|null $brand
 * @param string $subtitle optional line under company name when header_details empty
 */
function companyDocRenderHeader(array $brand = null, $subtitle = '')
{
    $brand = $brand ?: getCompanyDocumentBranding();
    if (!empty($brand['has_letterhead'])) {
        return '';
    }

    $company = (string) ($brand['company_name'] ?? '');
    $src = trim((string) ($brand['logo_src'] ?? ''));
    $header = trim((string) ($brand['header_details'] ?? ''));
    $subtitle = trim((string) $subtitle);

    $html = '<header class="cdoc-header">';
    if ($src !== '') {
        $html .= '<img class="cdoc-header-logo" src="'
            . htmlspecialchars($src, ENT_QUOTES, 'UTF-8')
            . '" alt="' . htmlspecialchars($company, ENT_QUOTES, 'UTF-8') . '">';
    }
    $html .= '<div class="cdoc-header-text">';
    $html .= '<p class="cdoc-company">' . htmlspecialchars($company, ENT_QUOTES, 'UTF-8') . '</p>';
    if ($header !== '') {
        $html .= '<div class="cdoc-header-details">'
            . nl2br(htmlspecialchars($header, ENT_QUOTES, 'UTF-8'))
            . '</div>';
    } elseif ($subtitle !== '') {
        $html .= '<div class="cdoc-header-details">'
            . htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8')
            . '</div>';
    }
    $html .= '</div></header>';
    return $html;
}

/**
 * Render document footer from company settings.
 * Skipped when approved letterhead image is active (footer is on the image).
 */
function companyDocRenderFooter(array $brand = null)
{
    $brand = $brand ?: getCompanyDocumentBranding();
    if (!empty($brand['has_letterhead'])) {
        return '';
    }
    $footer = trim((string) ($brand['footer_details'] ?? ''));
    if ($footer === '') {
        return '';
    }
    return '<footer class="cdoc-footer">'
        . nl2br(htmlspecialchars($footer, ENT_QUOTES, 'UTF-8'))
        . '</footer>';
}
