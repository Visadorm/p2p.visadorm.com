<?php

return [

    // Page Titles
    'general' => 'General Settings',
    'blockchain' => 'Blockchain Settings',
    'trade' => 'Trade Settings',
    'fees' => 'Fee Settings',
    'notifications' => 'Notification Settings',

    // General Settings
    'branding' => [
        'title' => 'Branding',
        'site_name' => 'Site Name',
        'site_description' => 'Site Description',
        'support_email' => 'Support Email',
        'logo' => 'Logo',
        'favicon' => 'Favicon',
    ],

    'regional' => [
        'title' => 'Regional',
        'default_currency' => 'Default Currency',
        'default_country' => 'Default Country',
    ],

    'features' => [
        'title' => 'Features',
        'merchant_registration_enabled' => 'Merchant Registration',
        'p2p_trading_enabled' => 'P2P Trading',
        'cash_meetings_enabled' => 'Cash Meetings',
    ],

    // Blockchain Settings
    'network' => [
        'title' => 'Network Configuration',
        'network' => 'Network',
        'rpc_url' => 'RPC URL',
        'chain_id' => 'Chain ID',
        'base_sepolia' => 'Base Sepolia (Testnet)',
        'base_mainnet' => 'Base Mainnet',
    ],

    'contracts' => [
        'title' => 'Contract Addresses',
        'trade_escrow_address' => 'Trade Escrow Contract',
        'soulbound_nft_address' => 'Soulbound NFT Contract',
        'usdc_address' => 'USDC Token Contract',
    ],

    'gas' => [
        'title' => 'Gas Wallet',
        'gas_wallet_address' => 'Gas Wallet Address',
        'min_gas_balance' => 'Minimum Gas Balance (ETH)',
    ],

    'multisig' => [
        'title' => 'Multisig Wallets',
        'fee_wallet_address' => 'Fee Wallet Address',
        'admin_multisig_address' => 'Admin Multisig Address',
    ],

    // Trade Settings
    'trade_defaults' => [
        'title' => 'Trade Defaults',
        'default_trade_timer_minutes' => 'Default Trade Timer (minutes)',
        'max_trade_timer_minutes' => 'Max Trade Timer (minutes)',
        'stake_amount' => 'Stake Amount (USDC)',
        'global_min_trade' => 'Global Minimum Trade (USDC)',
        'global_max_trade' => 'Global Maximum Trade (USDC)',
    ],

    'escrow' => [
        'title' => 'Escrow & Badges',
        'liquidity_badge_threshold' => 'Liquidity Badge Threshold (USDC)',
        'fast_responder_minutes' => 'Fast Responder Threshold (minutes)',
    ],

    'dispute' => [
        'title' => 'Dispute',
        'dispute_window_hours' => 'Dispute Window (hours)',
        'trade_expiry_cleanup_minutes' => 'Trade Expiry Cleanup Interval (minutes)',
    ],

    'buyer_verification' => [
        'title' => 'Buyer Verification',
        'default_buyer_verification' => 'Default Buyer Verification',
        'none' => 'None',
        'optional' => 'Optional',
        'required' => 'Required',
    ],

    // Fee Settings
    'p2p_fees' => [
        'title' => 'P2P Trading Fees',
        'p2p_fee_percent' => 'P2P Fee (%)',
        'note' => 'Display reference only. Actual fees are hardcoded in the smart contract.',
    ],

    'lock_period' => [
        'title' => 'Lock Period',
        'fund_lock_hours' => 'Fund Lock Duration (hours)',
    ],

    // Notification Settings
    'alerts' => [
        'title' => 'Admin Alerts',
        'alert_new_dispute' => 'Alert on New Dispute',
        'alert_kyc_pending' => 'Alert on Pending KYC',
        'alert_low_gas' => 'Alert on Low Gas Balance',
        'alert_large_trade' => 'Alert on Large Trade',
        'large_trade_threshold' => 'Large Trade Threshold (USDC)',
    ],

    'email' => [
        'title' => 'Email Configuration',
        'admin_email' => 'Admin Email Address',
        'email_notifications_enabled' => 'Email Notifications',
    ],

    // Admin Roles
    'roles' => [
        'super_admin' => 'Super Admin',
        'kyc_reviewer' => 'KYC Reviewer',
        'dispute_manager' => 'Dispute Manager',
    ],

    // Email Template Settings
    'email_template' => 'Email Template',
    'email_template_branding' => [
        'title' => 'Branding',
        'logo' => 'Email Logo',
        'header_image' => 'Header Image',
    ],
    'email_template_colors' => [
        'title' => 'Colors',
        'primary_color' => 'Primary Color',
        'secondary_color' => 'Secondary Color',
    ],
    'email_template_footer' => [
        'title' => 'Footer',
        'footer_text' => 'Footer Text (supports HTML)',
    ],
    'email_template_preview' => [
        'button' => 'Preview Email',
    ],

    // Profile Cluster
    'profile' => [
        'title' => 'Profile',
    ],

    // Change Password
    'password' => [
        'title' => 'Change Password',
        'section' => 'Update Password',
        'current_password' => 'Current Password',
        'new_password' => 'New Password',
        'confirm_password' => 'Confirm Password',
        'save' => 'Update Password',
        'updated' => 'Password updated successfully.',
    ],

    // Two-Factor Authentication
    'two_factor' => [
        'title' => 'Two-Factor Authentication',
        'section' => 'Authenticator App',
        'description' => 'Add an extra layer of security using a TOTP authenticator app like Google Authenticator or Authy.',
    ],

    // Common
    'saved' => 'Settings saved successfully.',
    'save' => 'Save Settings',

];
