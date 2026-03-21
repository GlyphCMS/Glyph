<?php

declare(strict_types=1);

use Glyph\services\content\ContentInput;
use Glyph\services\content\ContentValidator;
use Glyph\services\content\SlugManager;

$validator = new ContentValidator(new SlugManager());

$validInput = new ContentInput(
    type: 'post',
    title: 'My First Post',
    slug: 'my-first-post',
    status: 'draft',
    excerpt: 'A short summary.',
    bodyHtml: '<p>Hello world</p>',
    featuredImage: 'uploads/images/2026/03/example.webp',
    parentId: null,
    seoTitle: 'My First Post',
    seoDescription: 'A short SEO description.',
    seoImage: 'uploads/images/2026/03/seo.webp',
);

if (!$validator->validate($validInput)->isValid()) {
    return false;
}

$invalidInput = new ContentInput(
    type: 'weird',
    title: '',
    slug: 'admin',
    status: 'broken',
    excerpt: str_repeat('x', 600),
    bodyHtml: '',
    featuredImage: '../bad.png',
    parentId: null,
    seoTitle: str_repeat('x', 250),
    seoDescription: str_repeat('x', 400),
    seoImage: '../seo-bad.png',
);

$invalidResult = $validator->validate($invalidInput);

if ($invalidResult->isValid()) {
    return false;
}

$expectedFields = [
    'type',
    'title',
    'slug',
    'status',
    'excerpt',
    'body_html',
    'featured_image',
    'seo_title',
    'seo_description',
    'seo_image',
];

foreach ($expectedFields as $field) {
    if ($invalidResult->firstError($field) === null) {
        return false;
    }
}

return true;
