<?php

return [

    'kyc' => 'KYC',
    'kyc_documents' => 'KYC Documents',
    'document' => 'Document',
    'merchant' => 'Merchant',
    'submitted_at' => 'Submitted At',
    'reviewed_at' => 'Reviewed At',
    'reviewed_by' => 'Reviewed By',
    'rejection_reason' => 'Rejection Reason',
    'document_type_label' => 'Document Type',

    // KYC Document Type labels (used by KycDocumentType enum)
    'type' => [
        'id_document' => 'ID Document',
        'bank_statement' => 'Bank Statement',
        'proof_of_residency' => 'Proof of Residency',
        'business_document' => 'Business Document',
    ],

    // Legacy Document Types
    'document_type' => [
        'passport' => 'Passport',
        'national_id' => 'National ID',
        'drivers_license' => 'Driver\'s License',
        'selfie' => 'Selfie',
        'proof_of_address' => 'Proof of Address',
        'utility_bill' => 'Utility Bill',
    ],

    // Bare label for field headers
    'status_label' => 'KYC Status',

    // Errors
    'error' => [
        'cannot_delete_reviewed' => 'Only pending documents can be deleted.',
    ],

    // Status Labels
    'status' => [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'expired' => 'Expired',
        'resubmitted' => 'Resubmitted',
    ],

    // Form Labels
    'upload_document' => 'Upload Document',
    'select_document_type' => 'Select Document Type',
    'approve' => 'Approve',
    'reject' => 'Reject',
    'reason' => 'Reason',
    'view_document' => 'View Document',
    'document_front' => 'Document Front',
    'document_back' => 'Document Back',
    'expiry_date' => 'Expiry Date',
    'document_number' => 'Document Number',

    // Infolist
    'original_name' => 'File Name',
    'file_path' => 'File Path',
    'document_preview' => 'Document Preview',
    'file_status' => 'File Status',
    'file_available' => 'File Available',
    'file_not_found' => 'File Not Found',
    'download_document' => 'Download Document',

];
