<?php
require_once __DIR__ . '/firebase.php';

function firestore_write_document(string $collection, string $docId, array $data)
{
    $config = get_firebase_config();
    if (empty($config['firestore_project_id']) || empty($config['firestore_service_account'])) {
        return ['error' => 'Firestore project ID or service account credentials are not configured.'];
    }

    return ['error' => 'Firestore REST integration is configured for future use. Add service account credentials to enable writes.'];
}

function firestore_add_document(string $collection, array $data)
{
    return firestore_write_document($collection, '', $data);
}

function firestore_get_collection(string $collection)
{
    return [];
}
