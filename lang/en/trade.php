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
        'confirming' => 'Confirming On-Chain',
        'completed' => 'Completed',
        'cancelling' => 'Cancelling On-Chain',
        'cancelled' => 'Cancelled',
        'disputed' => 'Disputed',
        'expired' => 'Expired',
        'resolved' => 'Resolved',
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

    // A3 Path C — dispute classification (fast-track buyer cancellation requests)
    'dispute_kind' => 'Kind',
    'dispute_kind_cancel_request' => 'Cancel Request',
    'dispute_kind_dispute' => 'Dispute',

    // Errors
    'error' => [
        'insufficient_escrow' => 'Insufficient USDC balance in escrow contract.',
        'unsupported_currency' => 'This merchant does not support the currency: :currency.',
        'unsupported_payment_method' => 'This merchant does not offer the selected payment method.',
        'buyer_verification_required' => 'This merchant requires ID verification. Please upload and get your KYC approved before trading.',
        'trade_expired' => 'This trade has expired and can no longer be confirmed.',
        'cannot_self_trade' => 'You cannot trade with your own merchant account.',
        'active_trade_exists' => 'You already have an active trade with this merchant. Please complete or cancel it first.',
        'active_sell_trade_exists' => 'You already have an active sell trade. Please complete, cancel, or resolve it before opening another.',
        'chat_locked' => 'Trade chat is locked because the trade has been completed, cancelled, or resolved.',
        'chat_empty' => 'Type a message or attach a file before sending.',
        'exchange_rate_unavailable' => 'Exchange rate is currently unavailable. Please try again later.',
        'merchant_insufficient_escrow' => 'This merchant does not have enough USDC in escrow to cover this trade. Try a smaller amount or contact the merchant.',
    ],

    // A2: Sell flow UI copy (mirrors resources/js/pages/Sell/TradeRoom.jsx).
    // JSX is the live source until i18n bootstrapped on the frontend.
    'sell' => [
        'badge' => [
            'pending' => 'Waiting for Buyer',
            'escrow_locked' => 'Buyer Paying',
            'payment_sent' => 'Verify Payment',
            'completed' => 'Completed',
            'disputed' => 'Disputed',
            'cancelled' => 'Cancelled',
            'expired' => 'Expired',
            'resolved' => 'Resolved',
        ],
        'seller' => [
            'pending' => 'Waiting for buyer to join. Buyer has been notified.',
            'cancel_btn' => 'Cancel trade (full refund)',
            'escrow_locked' => 'Waiting for buyer payment of :amount :currency via :method.',
            'payment_sent_title' => 'Buyer marked as paid',
            'payment_sent_body' => 'Verify the fiat landed in your account before releasing USDC. The wallet signature is your final confirmation.',
            'release_btn' => 'Confirm & Release USDC (:amount)',
            'release_warning' => 'You sign + pay gas. Once released, the trade is final and irreversible.',
            'completed' => 'Trade complete. USDC sent to buyer.',
            'cancelled' => 'Trade closed. Funds returned to your wallet.',
            'disputed_title' => 'Dispute under review',
            'disputed_body' => 'Mediator Council is reviewing this dispute. Funds remain locked in escrow. You will be notified when the multisig resolves it.',
            'resolved' => 'Dispute resolved by Mediator Council. Funds distributed on-chain. View Resolve tx for details.',
        ],
        'buyer' => [
            'join_btn' => 'Join Trade',
            'pay_title' => 'Send :amount :currency to seller',
            'pay_body' => 'Via your :method account. Use trade hash as reference.',
            'paid_btn' => 'I Paid',
            'cancel_btn' => 'Cancel trade (request mediator review)',
            'cancel_confirm' => 'Cancel this trade? This sends a cancellation request to the Mediator Council. If approved, the seller is refunded in full. Use only if you cannot complete the fiat payment.',
            'cancel_success' => 'Cancellation request sent to Mediator Council',
            'payment_sent' => 'Waiting for seller to verify and release. They have final say.',
            // A4 — payment proof upload
            'proof_upload_title' => 'Upload payment proof',
            'proof_reupload_title' => 'Re-upload payment proof',
            'proof_help' => 'Screenshot of bank transfer, receipt, or confirmation. Image or PDF, max 5MB. Helps the seller verify your payment.',
            'proof_upload_btn' => 'Upload proof',
            'proof_uploaded_label' => 'Already uploaded',
            'proof_seller_view_title' => 'Buyer uploaded payment proof',
            'proof_open_btn' => 'Open / download proof',
        ],
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
