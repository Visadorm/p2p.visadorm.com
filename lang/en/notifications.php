<?php

return [

    // Notification Types
    'type' => [
        'new_dispute' => 'New Dispute',
        'kyc_pending' => 'KYC Pending',
        'low_gas' => 'Low Gas Balance',
        'large_trade' => 'Large Trade',
        'trade_completed' => 'Trade Completed',
        'trade_cancelled' => 'Trade Cancelled',
        'trade_expired' => 'Trade Expired',
        'dispute_resolved' => 'Dispute Resolved',
        'kyc_approved' => 'KYC Approved',
        'kyc_rejected' => 'KYC Rejected',
        'merchant_registered' => 'Merchant Registered',
        'rank_upgraded' => 'Rank Upgraded',
        'trade_initiated' => 'Trade Initiated',
        'payment_marked' => 'Payment Marked',
        'bank_proof_uploaded' => 'Bank Proof Uploaded',
        'buyer_id_submitted' => 'Buyer ID Submitted',
        'dispute_opened' => 'Dispute Opened',
        'kyc_reviewed' => 'KYC Reviewed',
    ],

    // Notification Titles
    'title' => [
        'new_dispute' => 'A new dispute has been opened',
        'kyc_pending' => 'A KYC document is awaiting review',
        'low_gas' => 'Gas wallet balance is below threshold',
        'large_trade' => 'A large trade has been initiated',
        'trade_completed' => 'A trade has been completed',
        'trade_cancelled' => 'A trade has been cancelled',
        'trade_expired' => 'A trade has expired',
        'dispute_resolved' => 'A dispute has been resolved',
        'kyc_approved' => 'KYC document has been approved',
        'kyc_rejected' => 'KYC document has been rejected',
        'merchant_registered' => 'A new merchant has registered',
        'rank_upgraded' => 'A merchant rank has been upgraded',
        'trade_initiated' => 'A new trade has been started',
        'payment_marked' => 'Buyer has marked payment as sent',
        'bank_proof_uploaded' => 'Buyer has uploaded bank proof',
        'buyer_id_submitted' => 'Buyer has submitted their ID',
        'dispute_opened' => 'A dispute has been opened',
        'kyc_reviewed' => 'Your KYC document has been reviewed',
    ],

    // Notification Bodies
    'body' => [
        'trade_initiated' => 'Trade #:hash has been initiated for :amount USDC.',
        'payment_marked' => 'Trade #:hash: Buyer marked payment as sent.',
        'bank_proof_uploaded' => 'Trade #:hash: Buyer uploaded bank proof.',
        'buyer_id_submitted' => 'Trade #:hash: Buyer submitted their ID.',
        'new_dispute' => 'Trade #:hash: Dispute opened — :reason',
        'dispute_opened' => 'Trade #:hash: Dispute opened — :reason',
        'trade_cancelled' => 'Trade #:hash has been cancelled.',
        'trade_completed' => 'Trade #:hash completed. :amount USDC released.',
        'trade_expired' => 'Trade #:hash has expired. Escrow unlocked.',
        'kyc_reviewed' => 'Your :document_type document has been :status.',
    ],

    // Action Button Text
    'action' => [
        'view_trade' => 'View Trade',
        'review_release' => 'Review & Release',
        'view_proof' => 'View Proof',
        'view_trades' => 'View Trades',
        'view_kyc' => 'View KYC',
        'accept_sell_trade' => 'Accept Sell Trade',
    ],

];
