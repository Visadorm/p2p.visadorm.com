<?php

return [

    'trade' => 'Trade',
    'trades' => 'Trades',
    'trade_hash' => 'Trade Hash',
    'merchant' => 'Merchant',
    'merchant_wallet' => 'Merchant Wallet',
    'buyer' => 'Buyer',
    'buyer_wallet' => 'Buyer Wallet',
    'amount' => 'Amount',
    'payment_method' => 'Payment Method',
    'timeline' => 'Timeline',
    'evidence' => 'Evidence',
    'trading_link' => 'Trading Link',

    // Trade Statuses
    'status' => [
        'pending' => 'Pending',
        'escrow_locked' => 'Escrow Locked',
        'payment_sent' => 'Payment Sent',
        'payment_confirmed' => 'Payment Confirmed',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'disputed' => 'Disputed',
        'expired' => 'Expired',
    ],

    // Trade Types
    'type' => [
        'buy' => 'Buy',
        'sell' => 'Sell',
    ],

    // Dispute Statuses
    'dispute_status' => [
        'open' => 'Open',
        'resolved_buyer' => 'Resolved for Buyer',
        'resolved_merchant' => 'Resolved for Merchant',
        'cancelled' => 'Cancelled',
    ],

    // Stake Paid By
    'stake_paid_by' => [
        'buyer' => 'Buyer',
        'merchant' => 'Merchant',
    ],

    // Bank Proof Status
    'bank_proof_status' => [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ],

    // Trade Fields
    'amount_usdc' => 'Amount (USDC)',
    'amount_fiat' => 'Amount (Fiat)',
    'currency_code' => 'Currency',
    'exchange_rate' => 'Exchange Rate',
    'type_label' => 'Type',
    'fee_amount' => 'Platform Fee',
    'stake_amount' => 'Stake Amount',
    'stake_paid_by_label' => 'Stake Paid By',

    // Verification
    'bank_proof' => 'Bank Proof',
    'buyer_id' => 'Buyer ID',
    'bank_proof_file' => 'Bank Proof File',
    'buyer_id_file' => 'Buyer ID File',
    'not_uploaded' => 'Not Uploaded',

    // Blockchain
    'escrow_tx' => 'Escrow TX Hash',
    'release_tx' => 'Release TX Hash',
    'nft_token_id' => 'NFT Token ID',
    'resolution_tx' => 'Resolution TX Hash',

    // Meeting
    'meeting_location' => 'Meeting Location',
    'meeting_lat' => 'Latitude',
    'meeting_lng' => 'Longitude',

    // Timestamps
    'disputed_at' => 'Disputed At',
    'completed_at' => 'Completed At',
    'expires_at' => 'Expires At',

    // Dispute
    'dispute' => 'Dispute',
    'trade_status' => 'Trade Status',
    'opened_by' => 'Opened By',
    'reason' => 'Reason',
    'resolution_notes' => 'Resolution Notes',
    'resolved_by' => 'Resolved By',
    'resolve_dispute' => 'Resolve Dispute',
    'resolve_for_buyer' => 'Resolve for Buyer',
    'resolve_for_merchant' => 'Resolve for Merchant',

    // Errors
    'error' => [
        'insufficient_escrow' => 'Insufficient USDC balance in escrow contract.',
        'unsupported_currency' => 'This merchant does not support the currency: :currency.',
        'unsupported_payment_method' => 'This merchant does not offer the selected payment method.',
        'buyer_verification_required' => 'This merchant requires ID verification. Please upload and get your KYC approved before trading.',
        'trade_expired' => 'This trade has expired and can no longer be confirmed.',
        'cannot_self_trade' => 'You cannot trade with your own merchant account.',
        'active_trade_exists' => 'You already have an active trade with this merchant. Please complete or cancel it first.',
    ],

    // Sections
    'section_trade_info' => 'Trade Information',
    'section_parties' => 'Parties',
    'section_amounts' => 'Amounts & Fees',
    'section_verification' => 'Verification',
    'section_blockchain' => 'Blockchain',
    'section_meeting' => 'Cash Meeting',
    'section_timestamps' => 'Timestamps',

];
