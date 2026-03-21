<?php

declare(strict_types=1);

use Glyph\adapters\security\HtmlSanitizer;

$sanitizer = new HtmlSanitizer();

$input = <<<HTML
<div class="hero" style="color: red; padding: 1rem;" data-kind="callout"><em>wrapped</em></div>
<span style="background-image: url(javascript:alert(1)); color: blue; behavior: url(#default#VML);" onclick="alert(1)">Styled</span>
<a href="javascript:alert(1)">bad</a>
<a href="mailto:team@example.com" target="_blank">good</a>
<img src="/uploads/images/test.webp" srcset="/uploads/images/test.webp 1x, javascript:alert(1) 2x" onload="alert(1)" alt="x">
<iframe src="https://example.com/embed" loading="lazy" allowfullscreen srcdoc="<script>alert(1)</script>"></iframe>
<form action="/contact" method="post"><button formaction="javascript:alert(1)">Send</button></form>
<style>.x { color: red; }</style>
<svg><script>alert(1)</script></svg>
HTML;

$output = $sanitizer->sanitize($input);

if (str_contains($output, '<script')) {
    return false;
}

if (str_contains($output, '<style')) {
    return false;
}

if (str_contains($output, '<svg')) {
    return false;
}

if (str_contains($output, 'onclick=')) {
    return false;
}

if (str_contains($output, 'onload=')) {
    return false;
}

if (str_contains($output, 'javascript:')) {
    return false;
}

if (str_contains($output, 'srcdoc=')) {
    return false;
}

if (!str_contains($output, 'class="hero"')) {
    return false;
}

if (!str_contains($output, 'data-kind="callout"')) {
    return false;
}

if (!str_contains($output, 'style="color: red; padding: 1rem;"')) {
    return false;
}

if (!str_contains($output, 'style="color: blue;"')) {
    return false;
}

if (!str_contains($output, '<em>wrapped</em>')) {
    return false;
}

if (!str_contains($output, 'href="mailto:team@example.com"')) {
    return false;
}

if (!str_contains($output, 'rel="noopener noreferrer"') && !str_contains($output, 'rel="noreferrer noopener"')) {
    return false;
}

if (!str_contains($output, '<iframe src="https://example.com/embed"')) {
    return false;
}

if (!str_contains($output, '<form action="/contact" method="post">')) {
    return false;
}

if (str_contains($output, 'formaction=')) {
    return false;
}

if (!str_contains($output, 'srcset="/uploads/images/test.webp 1x"')) {
    return false;
}

return true;
