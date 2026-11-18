<?php

/*
| Rute POST domain D3 Keranjang dan Pesanan (LP-11).
| 'berkunci': wajib Idempotency-Key. 'tanpa_kunci': aman diulang, dengan sebab tertulis.
*/
return [
    'berkunci' => [
        'POST api/v1/checkout',
        'POST api/v1/pesanan/{nomor}/terima',
        'POST api/v1/pesanan/{nomor}/batal',
        'POST api/v1/checkout/{kode}/batal',
    ],
    'tanpa_kunci' => [
        'POST api/v1/simulasi-belanja' => 'Hanya menghitung, tidak menulis.',
    ],
];
