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
        'support_url' => 'Support URL',
        'support_url_help' => 'External link for the "Support" menu entry (e.g. Telegram, Discord, help desk, contact form). Leave blank to use the support email.',
        'logo' => 'Logo',
        'favicon' => 'Favicon',
    ],


    'features' => [
        'title' => 'Features',
        'description' => 'Master kill-switches for the platform. Sell-flow specific flags (sell offer toggle, KYC threshold, max offers per wallet, etc.) live on the Trade Settings page.',
        'merchant_registration_enabled' => 'Allow new merchant registration',
        'merchant_registration_help' => 'When off, new wallets cannot register. Existing merchants can still sign in and trade.',
        'p2p_trading_enabled' => 'P2P trading enabled',
        'p2p_trading_help' => 'Master switch — when off, blocks all new buy + sell trade initiations. Existing trades can still settle.',
        'cash_meetings_enabled' => 'Cash meetings enabled',
        'cash_meetings_help' => 'Allow trades using cash_meeting payment methods. When off, only online payment methods (bank, Wise, PayPal, etc.) can be used.',
        'go_to_trade_settings' => 'Trade & Sell flow settings',
    ],

    'homepage' => [
        'title' => 'Homepage',
        'variant' => 'Active Homepage',
        'variant_help' => 'Switch which landing page visitors see at the site root.',
        'classic' => 'Classic',
        'dynamic' => 'Dynamic',
    ],

    'translation' => [
        'title' => 'Translation (Weglot)',
        'enabled' => 'Enable Weglot translation',
        'api_key' => 'Weglot API Key',
        'api_key_help' => 'Your Weglot project API key (starts with "wg_"). The script is injected on public pages only.',
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

    'cleanup' => [
        'title' => 'Trade Cleanup',
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
        'p2p_fee_percent' => 'Fee per trade',
        'note' => 'Display reference only. Actual fees are hardcoded in the smart contract.',
        'locked_help' => 'The trading fee is enforced on-chain at 0.2% of trade amount and routed to the platform multisig. To change it requires deploying a new escrow contract.',
        'contract_locked' => 'Locked at smart-contract level (FEE_BPS = 20). Read-only.',
    ],

    'lock_period' => [
        'title' => 'Sell Offer Expiry',
        'fund_lock_hours' => 'Default offer expiry (hours)',
        'fund_lock_help' => 'How long a sell offer stays open before auto-cancelling and refunding the seller. Min 1, max 720 (30 days).',
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

    // Sell flow
    'sell' => [
        'title' => 'Sell Flow',
        'enabled' => 'Enable Sell Flow',
        'enabled_help' => 'Master switch. When off, sell-flow API endpoints + frontend pages are blocked.',
        'cash_meeting_enabled' => 'Cash Meetings on Sell Trades',
        'cash_meeting_enabled_help' => 'Off at launch — only online payment methods supported for sell.',
        'cash_sell_coming_soon' => 'Coming soon. Sell-flow cash meetings require the in-person NFT-gated UX (QR scan, meeting flow). Out of scope for the current milestone — currently hardcoded off in service + frontend.',
        'max_offers_per_wallet' => 'Max Active Offers per Wallet',
        'max_outstanding_usdc' => 'Max Outstanding USDC per Wallet',
        'kyc_threshold_usdc' => 'KYC Required After (USDC sold)',
        'kyc_threshold_window_days' => 'KYC Threshold Window (days)',
        'default_offer_timer_minutes' => 'Default Offer Expiry (minutes)',
    ],

    // Common
    'saved' => 'Settings saved successfully.',
    'save' => 'Save Settings',

];
