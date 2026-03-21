<?php

declare(strict_types=1);

use Glyph\services\content\SlugManager;

$manager = new SlugManager();

if ($manager->normalize('blog/post') !== '/blog/post') {
    return false;
}

if ($manager->normalize('/blog//post/') !== '/blog/post') {
    return false;
}

if ($manager->normalize('/Lorem Ipsum') !== '/lorem-ipsum') {
    return false;
}

if (!$manager->isValid('/blog/post-1')) {
    return false;
}

if (!$manager->isValid('/Blog/Post')) {
    return false;
}

if (!$manager->isValid('/Lorem Ipsum')) {
    return false;
}

if (!$manager->isReserved('/admin/settings')) {
    return false;
}

if (!$manager->isReserved('/Admin/Settings')) {
    return false;
}

if ($manager->isReserved('/blog/post')) {
    return false;
}

return true;
