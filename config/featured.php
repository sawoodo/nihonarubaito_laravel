<?php

return [
    // Areas with enough hand-cash inventory (8+ published) to sustain
    // a dedicated page through normal churn. Reviewed quarterly.
    'hand_cash_areas' => [
        'kita-ku-osaka',            // 21 jobs
        'chiyoda-ku',               // 17 jobs
        'chuo-ku-osaka',            // 15 jobs
        'minato-ku',                // 15 jobs
        'shinjuku-ward',            // 14 jobs
        'hakata-ku-fukuoka-city',   // 14 jobs
        'chuo-ku-fukuoka',          // 10 jobs
        'shibuya-ward',             //  9 jobs
        'taito',                    //  9 jobs
        'nishi-ku-osaka',           //  9 jobs
        'setagaya',                 //  9 jobs
        'kyoto-shimogyo',           //  8 jobs
    ],

    // Areas with enough daily-payment inventory (8+ published) to sustain
    // a dedicated page through normal churn. Inventory churns faster than
    // hand-cash (dispatch agencies). Review quarterly.
    'daily_payment_areas' => [
        'minato-ku',                // 27 jobs
        'higashi-osaka-city',       // 27 jobs
        'shinagawa',                // 24 jobs
        'aoba-ku-sendai',           // 22 jobs
        'koto',                     // 21 jobs
        'funabashi',                // 19 jobs
        'chuo-ku-osaka',            // 16 jobs
        'taito',                    // 15 jobs
        'ota-ku',                   // 14 jobs
        'matsudo',                  // 14 jobs
        'utsunomiya',               // 14 jobs
        // 'nakano' removed: slug collision (Tokyo ID 14 vs Nagano ID 941)
        'setagaya',                 // 13 jobs
        'ichikawa',                 // 13 jobs
        'ibaraki',                  // 13 jobs
        'misato-city',              // 12 jobs
        'shinjuku-ward',            // 12 jobs
        'shibuya-ward',             // 12 jobs
        'edogawa',                  // 11 jobs
        'kawasaki-ku',              // 11 jobs
    ],
];
