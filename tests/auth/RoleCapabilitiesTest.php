<?php

declare(strict_types=1);

use Glyph\domain\auth\RoleCapabilities;

if (RoleCapabilities::hasCapability('reader', RoleCapabilities::ADMIN_ACCESS)) {
    return false;
}

if (!RoleCapabilities::hasCapability('contributor', RoleCapabilities::CONTENT_CREATE)) {
    return false;
}

if (RoleCapabilities::hasCapability('contributor', RoleCapabilities::CONTENT_PUBLISH)) {
    return false;
}

if (!RoleCapabilities::hasCapability('author', RoleCapabilities::CONTENT_PUBLISH)) {
    return false;
}

if (RoleCapabilities::hasCapability('contributor', RoleCapabilities::CATEGORY_MANAGE)) {
    return false;
}

if (!RoleCapabilities::hasCapability('editor', RoleCapabilities::CATEGORY_MANAGE)) {
    return false;
}

if (RoleCapabilities::hasCapability('editor', RoleCapabilities::SETTINGS_MANAGE)) {
    return false;
}

if (!RoleCapabilities::hasCapability('editor', RoleCapabilities::USER_MANAGE)) {
    return false;
}

if (!RoleCapabilities::hasCapability('administrator', RoleCapabilities::SETTINGS_MANAGE)) {
    return false;
}

if (!RoleCapabilities::hasCapability('administrator', RoleCapabilities::SITE_OWN)) {
    return false;
}

if (!RoleCapabilities::hasCapability('owner', RoleCapabilities::SITE_OWN)) {
    return false;
}

if (RoleCapabilities::hasCapability('unknown-role', RoleCapabilities::ADMIN_ACCESS)) {
    return false;
}

return true;
