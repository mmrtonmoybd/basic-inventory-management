<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Business Timezone
    |--------------------------------------------------------------------------
    |
    | Timestamps are stored in UTC. Business dates (the "day" a movement belongs
    | to, dashboard "today", report ranges) are resolved in this timezone.
    |
    */

    'business_timezone' => env('INVENTORY_BUSINESS_TIMEZONE', 'Asia/Dhaka'),

];
