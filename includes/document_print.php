<?php
/**
 * Shared print / PDF document shell:
 * Logo header + Header Details | Body | Footer Details + logo watermark
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

    return [
        'company_name'    => $company,
        'logo'            => $logo,
        'logo_src'        => $logoSrc,
        'header_details'  => getCompanyHeaderDetails(),
        'footer_details'  => getCompanyFooterDetails(),
    ];
}

/**
 * Shared CSS for print documents (header / footer / watermark)
 */
function companyDocPrintCss()
{
    return <<<'CSS'
.cdoc-sheet {
    position: relative;
    background: #fff;
    overflow: hidden;
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
    font-family: Arial, Helvetica, sans-serif;
    white-space: pre-wrap;
    text-align: center;
}
CSS;
}

/**
 * Render logo watermark HTML
 */
function companyDocRenderWatermark(array $brand = null)
{
    $brand = $brand ?: getCompanyDocumentBranding();
    $src = trim((string) ($brand['logo_src'] ?? ''));
    if ($src === '') {
        return '';
    }
    return '<div class="cdoc-watermark" aria-hidden="true"><img src="'
        . htmlspecialchars($src, ENT_QUOTES, 'UTF-8')
        . '" alt=""></div>';
}

/**
 * Render document header (logo + company name + header details)
 *
 * @param array|null $brand
 * @param string $subtitle optional line under company name when header_details empty
 */
function companyDocRenderHeader(array $brand = null, $subtitle = '')
{
    $brand = $brand ?: getCompanyDocumentBranding();
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
 * Render document footer from company settings
 */
function companyDocRenderFooter(array $brand = null)
{
    $brand = $brand ?: getCompanyDocumentBranding();
    $footer = trim((string) ($brand['footer_details'] ?? ''));
    if ($footer === '') {
        return '';
    }
    return '<footer class="cdoc-footer">'
        . nl2br(htmlspecialchars($footer, ENT_QUOTES, 'UTF-8'))
        . '</footer>';
}
