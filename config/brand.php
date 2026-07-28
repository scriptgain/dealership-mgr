<?php

// Branding. Rename the whole product from one place. These defaults can be
// overridden by env, and by DB settings applied at boot (the Branding settings
// screen) — matching the DB-driven config pattern.
return [
    'name' => env('BRAND_NAME', env('APP_NAME', 'DealershipMGR')),
    'tagline' => env('BRAND_TAGLINE', 'Car Dealership Management'),
    // Accent hex; overrides the brand ramp at runtime. Settable in the UI.
    // Slate reads premium and automotive, and is the one tone no other -MGR
    // has taken: the rest of the fleet is saturated hues, and garage
    // (#c2410c), realestate (#b45309) and guard (#ea580c) already crowd the
    // warm end. The matching ramp lives in resources/css/app.css.
    'accent' => env('BRAND_ACCENT', '#334155'),
    // Logo/favicon glyph (an x-icon name). Distinct per product.
    'icon' => env('BRAND_ICON', 'car'),
];
