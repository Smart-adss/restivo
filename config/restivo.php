<?php

return [
    /*
    | Platform admins must complete TOTP before using /platform routes.
    | Disabled in phpunit.xml so existing feature tests stay focused;
    | Sprint17LaunchTest turns it back on for the TOTP cases.
    */
    'require_platform_totp' => (bool) env('REQUIRE_PLATFORM_TOTP', true),
];
