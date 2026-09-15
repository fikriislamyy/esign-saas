<?php

return [
    'certificate' => env('PDF_SIGN_CERT'),
    'private_key' => env('PDF_SIGN_KEY'),
    'password' => env('PDF_SIGN_KEY_PASSWORD', ''),
];
