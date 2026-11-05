<?php
/**
 * Terms pages taken from the project file "Contract Agreement.docx".
 * They print after the one page written in Add Contractor Agreement.
 */

function cncSopContractDocPath()
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Contract Agreement.docx';
}

function cncSopTermsCss()
{
    return <<<'CSS'
.sop-terms {
    font-family: "Noto Sans Gujarati", "Nirmala UI", sans-serif;
    font-size: 15px;
    line-height: 1.55;
    font-weight: 700;
    color: #111;
    min-height: 220mm;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.sop-terms p { margin: 0; }
.sop-terms .is-title {
    text-align: center;
    font-size: 18px;
    font-weight: 800;
    margin: 0;
}
.sop-terms .is-head {
    font-size: 16px;
    font-weight: 800;
    margin-top: 2px;
}
.sop-terms-page { margin-top: 16px; }
@media print {
    .sop-terms-page { margin-top: 0; page-break-before: always; }
}
CSS;
}

function cncSopParagraphHtml(DOMXPath $xp, DOMElement $p, $isTitle)
{
    $html = '';
    $runs = $xp->query('.//w:r', $p);
    foreach ($runs as $run) {
        $bold = $xp->query('./w:rPr/w:b', $run)->length > 0;
        $chunk = '';
        foreach ($run->childNodes as $child) {
            $name = $child->localName;
            if ($name === 'br' || $name === 'cr') {
                $chunk .= '<br>';
            } elseif ($name === 't' || $name === 'tab') {
                $chunk .= $name === 'tab'
                    ? ' '
                    : htmlspecialchars($child->textContent, ENT_QUOTES, 'UTF-8');
            }
        }
        if ($chunk === '') {
            continue;
        }
        $html .= $bold ? '<strong>' . $chunk . '</strong>' : $chunk;
    }
    $plain = trim(preg_replace('/\s+/u', ' ', strip_tags(str_replace('<br>', ' ', $html))));
    if ($plain === '' || mb_strpos($plain, 'શું તમે') === 0) {
        return '';
    }
    $class = 'sop-term-p';
    if ($isTitle) {
        $class .= ' is-title';
    } elseif (preg_match('/^\d+\./u', $plain)) {
        $class .= ' is-head';
    }
    return '<p class="' . $class . '">' . $html . '</p>';
}

function cncSopTermsPages()
{
    $path = cncSopContractDocPath();
    if (!is_file($path) || !class_exists('ZipArchive')) {
        return [];
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return [];
    }
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    if ($xml === false || $xml === '') {
        return [];
    }
    $dom = new DOMDocument();
    if (!@$dom->loadXML($xml)) {
        return [];
    }
    $xp = new DOMXPath($dom);
    $xp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    $paras = $xp->query('/w:document/w:body/w:p');
    if (!$paras) {
        return [];
    }

    $pages = [''];
    $pageIndex = 0;
    $firstOnPage = true;
    foreach ($paras as $p) {
        if ($xp->query('.//w:lastRenderedPageBreak', $p)->length > 0 && $pages[$pageIndex] !== '') {
            $pageIndex++;
            $pages[$pageIndex] = '';
            $firstOnPage = true;
        }
        $piece = cncSopParagraphHtml($xp, $p, $firstOnPage && $pageIndex === 0);
        if ($piece === '') {
            continue;
        }
        $pages[$pageIndex] .= $piece;
        $firstOnPage = false;
    }
    return array_values(array_filter($pages, static function ($html) {
        return trim($html) !== '';
    }));
}

function cncSopTermsSheetsHtml(array $brand)
{
    if (!function_exists('companyDocSheetClass')) {
        return '';
    }
    $pages = cncSopTermsPages();
    $html = '';
    foreach ($pages as $body) {
        $class = companyDocSheetClass($brand, 'sop-sheet sop-terms-page');
        $html .= '<div class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '">';
        if (function_exists('companyDocRenderWatermark')) {
            $html .= companyDocRenderWatermark($brand);
        }
        $html .= '<div class="cdoc-inner"><div class="sop-terms">' . $body . '</div>';
        if (function_exists('companyDocRenderFooter')) {
            $html .= companyDocRenderFooter($brand);
        }
        $html .= '</div></div>';
    }
    return $html;
}
