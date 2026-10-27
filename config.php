<?php
// Global configuration for the MIC System.
// Update the Firebase URL and admin credentials for the administrator module.

return [
    // Fill in your Firebase Realtime Database URL and auth token to enable
    // the existing collection-based system operations.
    'firebase_database_url' => 'https://webcool-bf70e-default-rtdb.asia-southeast1.firebasedatabase.app/',
    'firebase_auth_token' => 'SdDgigI6JOo0eQH2RHV3sWBM8vKTKDtrosnNkvnL',
    // Set this to the verified sender address used by the server mail transport.
    'password_reset_from_email' => '',
    'firebase_web' => [
        'api_key' => 'AIzaSyBoxr4ZAXrLSjkxGZImIbyUebvCivO0zsU',
        'auth_domain' => 'cool-app-20c5a.firebaseapp.com',
        'project_id' => 'cool-app-20c5a',
        'storage_bucket' => 'cool-app-20c5a.firebasestorage.app',
        'messaging_sender_id' => '707813358336',
        'app_id' => '1:707813358336:web:9a18356aa4db90372611e5',
    ],
    'firestore_service_account' => '',
    'firestore_project_id' => '',
    'admin_user' => [
        'username' => 'admin',
        'password_hash' => '$2y$10$2vgoVx76eg2/UA9V/VJEguOZuUYT3oefVYaPrEzV.u0DISNx91mTi',
    ],
    'inspector_user' => [
        'username' => 'inspector',
        'password_hash' => '$2y$10$IlLoltqvFYDIUus4g2oDNuwEFPJRdyGGI/aA/DP21776h.vsr8OG2',
    ],
];
