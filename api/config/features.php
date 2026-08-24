<?php

declare(strict_types=1);

return [

    // SRS §21: lets ops disable the freelance marketplace (projects,
    // proposals, contracts, milestones) per-environment without a deploy,
    // e.g. for a staged regional rollout. Defaults to enabled so local dev
    // and existing behavior are unchanged; set to false in an environment
    // that isn't ready for it yet.
    'freelance_marketplace' => env('FEATURE_FREELANCE_MARKETPLACE', true),

];
