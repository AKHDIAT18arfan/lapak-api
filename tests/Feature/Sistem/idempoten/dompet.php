<?php

return [
    'berkunci' => [
        'POST api/v1/dompet/pencairan',
        'POST api/v1/keuangan/pencairan/{kode}/setujui',
        'POST api/v1/keuangan/pencairan/{kode}/tolak',
    ],
    'tanpa_kunci' => [
        'POST api/v1/webhook/pembayaran' => 'Webhook luar dengan tanda tangan HMAC dan id_peristiwa unik.',
    ],
];
