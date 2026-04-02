<?php return array (
  'Illuminate\\Foundation\\Support\\Providers\\EventServiceProvider' => 
  array (
    'App\\Events\\BuyerIdSubmitted' => 
    array (
      0 => 'App\\Listeners\\NotifyMerchantOnBuyerId@handle',
    ),
    'App\\Events\\TradeCompleted' => 
    array (
      0 => 'App\\Listeners\\RecalculateMerchantRank@handle',
      1 => 'App\\Listeners\\UpdateMerchantStatsOnTradeComplete@handle',
      2 => 'App\\Listeners\\NotifyAdminOnTradeCompleted@handle',
      3 => 'App\\Listeners\\NotifyMerchantOnTradeCompleted@handle',
    ),
    'App\\Events\\TradeCancelled' => 
    array (
      0 => 'App\\Listeners\\NotifyMerchantOnTradeCancelled@handle',
    ),
    'App\\Events\\BankProofUploaded' => 
    array (
      0 => 'App\\Listeners\\NotifyMerchantOnBankProof@handle',
    ),
    'App\\Events\\KycDocumentSubmitted' => 
    array (
      0 => 'App\\Listeners\\NotifyAdminOnKycSubmitted@handle',
    ),
    'App\\Events\\PaymentMarked' => 
    array (
      0 => 'App\\Listeners\\NotifyMerchantOnPaymentMarked@handle',
    ),
    'App\\Events\\KycDocumentReviewed' => 
    array (
      0 => 'App\\Listeners\\NotifyMerchantOnKycReviewed@handle',
      1 => 'App\\Listeners\\UpdateMerchantBadgesOnKycReview@handle',
    ),
    'App\\Events\\DisputeOpened' => 
    array (
      0 => 'App\\Listeners\\NotifyMerchantOnDispute@handle',
      1 => 'App\\Listeners\\NotifyAdminOnDispute@handle',
    ),
    'App\\Events\\TradeInitiated' => 
    array (
      0 => 'App\\Listeners\\NotifyAdminOnLargeTrade@handle',
      1 => 'App\\Listeners\\NotifyMerchantOnTradeInitiated@handle',
    ),
  ),
);