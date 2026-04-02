<?php

return [

    // Navigation
    'nav' => [
        'dashboard' => 'Dashboard',
        'p2p_trading' => 'P2P Trading',
        'verification' => 'Verification',
        'settings' => 'Settings',
    ],

    // Resources
    'merchants' => 'Merchants',
    'trades' => 'Trades',
    'disputes' => 'Disputes',
    'reviews' => 'Reviews',
    'kyc_documents' => 'KYC Documents',
    'merchant_ranks' => 'Merchant Ranks',

    // Resource Labels
    'review' => 'Review',
    'rating' => 'Rating',
    'comment' => 'Comment',
    'kyc_document' => 'KYC Document',
    'merchant_rank' => 'Merchant Rank',
    'message_template' => 'Message Template',
    'message_templates' => 'Message Templates',

    // Message Template Fields
    'msg_tpl' => [
        'label' => 'Label',
        'type' => 'Type',
        'email_subject' => 'Email Subject',
        'email_body' => 'Email Body',
        'sms_text' => 'SMS Text',
        'sms_preview' => 'SMS Preview',
        'variables_guide' => 'Available Variables',
        'section_type' => 'Notification Type',
        'section_email' => 'Email',
        'section_sms' => 'SMS',
        'helper_variable' => 'Use :variable placeholders (e.g. :merchant_name, :amount).',
        'helper_sms_length' => 'Aim for 160 characters or fewer per SMS segment.',
    ],

    // Common Labels
    'wallet_address' => 'Wallet Address',
    'status' => 'Status',
    'created_at' => 'Created At',
    'updated_at' => 'Updated At',
    'actions' => 'Actions',
    'amount' => 'Amount',
    'date' => 'Date',
    'details' => 'Details',
    'search' => 'Search',
    'filter' => 'Filter',
    'export' => 'Export',
    'view' => 'View',
    'edit' => 'Edit',
    'delete' => 'Delete',
    'save' => 'Save',
    'cancel' => 'Cancel',
    'confirm' => 'Confirm',
    'active' => 'Active',
    'inactive' => 'Inactive',
    'enabled' => 'Enabled',
    'disabled' => 'Disabled',
    'yes' => 'Yes',
    'no' => 'No',

    // Dashboard Widgets
    'trades_today' => 'Trades Today',
    'trades_this_week' => 'Trades This Week',
    'trades_this_month' => 'Trades This Month',
    'total_volume' => 'Total Volume',
    'active_disputes' => 'Active Disputes',
    'pending_kyc' => 'Pending KYC',
    'completed_trades' => 'Completed Trades',
    'trade_volume_chart' => 'Trade Volume (30 Days)',
    'trades_by_status' => 'Trades by Status',
    'top_merchants' => 'Top Merchants by Volume',
    'gas_wallet' => 'Gas Wallet',
    'min_balance' => 'Min Balance',
    'network' => 'Network',
    'chain_id' => 'Chain ID',
    'not_configured' => 'Not Configured',

    // Widget Details
    'widgets' => [
        'active_disputes' => [
            'open' => 'Open Disputes',
            'resolved_this_month' => 'Resolved This Month',
            'total' => 'Total Disputes',
        ],
        'pending_kyc' => [
            'pending' => 'Pending KYC',
            'approved_this_month' => 'Approved This Month',
            'rejected' => 'Rejected',
        ],
        'fee_revenue' => [
            'title' => 'Fee Revenue (30 days)',
            'series_name' => 'Platform Fees (USDC)',
        ],
        'gas_wallet' => [
            'title' => 'Gas Wallet',
            'series_name' => 'ETH Balance',
        ],
    ],

    // API Auth
    'unauthorized' => 'Unauthorized.',
    'merchant_not_found' => 'Merchant not found or inactive.',
    'nonce_generated' => 'Nonce generated successfully.',
    'nonce_invalid' => 'Invalid or expired nonce.',
    'signature_invalid' => 'Signature verification failed.',
    'login_success' => 'Authenticated successfully.',
    'logout_success' => 'Logged out successfully.',
    'profile_loaded' => 'Profile loaded successfully.',

    // API Merchant
    'merchant_updated' => 'Merchant profile updated successfully.',
    'avatar_updated' => 'Profile picture updated successfully.',
    'merchant_profile_loaded' => 'Merchant profile loaded.',
    'dashboard_loaded' => 'Dashboard loaded successfully.',
    'merchant_not_active' => 'This merchant is not active.',

    // API Trading Links
    'trading_link_created' => 'Trading link created successfully.',
    'trading_link_updated' => 'Trading link updated successfully.',
    'trading_link_deleted' => 'Trading link deleted successfully.',
    'trading_link_not_found' => 'Trading link not found.',
    'trading_links_loaded' => 'Trading links loaded successfully.',

    // API Payment Methods
    'payment_method_created' => 'Payment method created successfully.',
    'payment_method_updated' => 'Payment method updated successfully.',
    'payment_method_deleted' => 'Payment method deleted successfully.',
    'payment_methods_loaded' => 'Payment methods loaded successfully.',

    // API Currencies
    'currency_created' => 'Currency created successfully.',
    'currency_updated' => 'Currency updated successfully.',
    'currency_deleted' => 'Currency deleted successfully.',
    'currencies_loaded' => 'Currencies loaded successfully.',

    // API Exchange Rates
    'exchange_rates_loaded' => 'Exchange rates loaded.',

    // API Trade
    'trade_link_loaded' => 'Trading link details loaded.',
    'trade_initiated' => 'Trade initiated successfully.',
    'trade_status_loaded' => 'Trade status loaded.',
    'trade_marked_paid' => 'Payment marked as sent.',
    'trade_bank_proof_uploaded' => 'Bank proof uploaded successfully.',
    'trade_buyer_id_uploaded' => 'Buyer ID uploaded successfully.',
    'trade_cancelled' => 'Trade cancelled successfully.',
    'trade_confirmed' => 'Payment confirmed and escrow released.',
    'trade_not_found' => 'Trade not found.',
    'trade_not_authorized' => 'You are not authorized for this trade.',
    'trade_invalid_status' => 'Trade is not in a valid status for this action.',
    'trades_loaded' => 'Trades loaded successfully.',

    // API Reviews
    'review_created' => 'Review submitted successfully.',
    'review_already_exists' => 'You have already reviewed this trade.',
    'review_not_buyer' => 'Only the buyer can leave a review.',
    'review_trade_not_completed' => 'Trade must be completed to leave a review.',

    // API KYC
    'kyc_documents_loaded' => 'KYC documents loaded successfully.',
    'kyc_uploaded' => 'KYC document uploaded successfully.',
    'kyc_deleted' => 'KYC document deleted successfully.',
    'kyc_not_pending' => 'Only pending documents can be deleted.',

    // API Disputes
    'dispute_created' => 'Dispute opened successfully.',
    'dispute_loaded' => 'Dispute details loaded.',
    'dispute_evidence_uploaded' => 'Evidence uploaded successfully.',
    'dispute_not_found' => 'Dispute not found.',
    'dispute_not_authorized' => 'You are not authorized for this dispute.',
    'dispute_already_exists' => 'A dispute already exists for this trade.',
    'dispute_not_open' => 'Dispute is not open for new evidence.',
    'dispute_winner_must_be_party' => 'Winner must be the merchant or buyer in this trade.',
    'dispute_resolved'             => 'Dispute resolved and funds released on-chain.',

    // API Notifications
    'notifications_loaded' => 'Notifications loaded successfully.',
    'notification_marked_read' => 'Notification marked as read.',
    'notifications_all_read' => 'All notifications marked as read.',
    'unread_count_loaded' => 'Unread count loaded.',
    'notification_not_authorized' => 'You are not authorized for this notification.',

    // API Errors
    'forbidden' => 'Access denied.',
    'not_found' => 'Resource not found.',
    'server_error' => 'An error occurred. Please try again.',
    'blockchain_error' => 'Blockchain error: :error',
    'blockchain_sync_delayed' => 'Trade updated but blockchain sync may be delayed. Your trade is safe.',

    // Generate Wallets Command
    'generate_wallets' => [
        'header'          => 'Visadorm P2P — Wallet Generation',
        'separator'       => '============================================================',
        'deployer_label'  => 'DEPLOYER WALLET',
        'operator_label'  => 'OPERATOR / GAS WALLET',
        'fee_label'       => 'FEE WALLET',
        'admin_label'     => 'ADMIN WALLET',
        'address'         => 'Address:',
        'private_key'     => 'Private Key:',
        'deployer_hint'   => '→ Fund with Base Sepolia ETH before deploying.',
        'deployer_env'    => '→ contracts/.env → DEPLOYER_PRIVATE_KEY',
        'operator_hint'   => '→ Fund with Base Sepolia ETH (ongoing gas). Signs all trade transactions.',
        'operator_env'    => '→ .env → OPERATOR_PRIVATE_KEY',
        'fee_hint'        => '→ Receives 0.2% platform fee. No private key needed in system.',
        'fee_env'         => '→ contracts/.env → FEE_WALLET (address only)',
        'admin_hint'      => '→ Signs resolveDispute only. On mainnet: replace with Gnosis 2-of-3 multisig.',
        'admin_env'       => '→ .env → ADMIN_PRIVATE_KEY + contracts/.env → ADMIN_ADDRESS',
        'security_notice' => 'SECURITY: Save these keys NOW. They will NOT be stored.',
    ],

    // API Escrow
    'insufficient_usdc_allowance' => 'USDC allowance insufficient — approve the escrow contract first.',
    'insufficient_escrow_balance' => 'Insufficient available escrow balance.',
    'escrow_deposit_submitted'    => 'Deposit transaction submitted.',
    'escrow_withdraw_submitted'   => 'Withdrawal transaction submitted.',

];
