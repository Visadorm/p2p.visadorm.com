<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed default message templates so they're always present
 * on fresh deploys — no manual seeder run needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $templates = [
            [
                'type' => 'trade_initiated',
                'label' => 'Trade Initiated',
                'email_subject' => 'A new trade has been started',
                'email_body' => 'Trade #:hash has been initiated for :amount USDC.',
                'sms_text' => 'Visadorm: New trade for :amount USDC. Check dashboard.',
                'variables_guide' => ':hash, :amount, :currency, :merchant_name, :buyer_wallet',
            ],
            [
                'type' => 'payment_marked',
                'label' => 'Payment Marked',
                'email_subject' => 'Buyer marked payment sent',
                'email_body' => 'Trade #:hash: Buyer marked payment as sent for :amount USDC.',
                'sms_text' => 'Visadorm: Buyer paid :amount USDC. Verify and release.',
                'variables_guide' => ':hash, :amount, :merchant_name',
            ],
            [
                'type' => 'bank_proof_uploaded',
                'label' => 'Bank Proof Uploaded',
                'email_subject' => 'Bank proof uploaded',
                'email_body' => 'Trade #:hash: Buyer uploaded bank proof for :amount USDC.',
                'sms_text' => 'Visadorm: Buyer uploaded bank proof. Review now.',
                'variables_guide' => ':hash, :amount, :merchant_name',
            ],
            [
                'type' => 'buyer_id_submitted',
                'label' => 'Buyer ID Submitted',
                'email_subject' => 'Buyer ID submitted',
                'email_body' => 'Trade #:hash: Buyer submitted their ID for verification.',
                'sms_text' => 'Visadorm: Buyer submitted ID verification.',
                'variables_guide' => ':hash, :amount, :merchant_name',
            ],
            [
                'type' => 'trade_completed',
                'label' => 'Trade Completed',
                'email_subject' => 'Trade completed — :amount USDC',
                'email_body' => 'Trade #:hash completed. :amount USDC released. Fee: :fee USDC.',
                'sms_text' => 'Visadorm: Trade completed! :amount USDC released.',
                'variables_guide' => ':hash, :amount, :fee, :merchant_name',
            ],
            [
                'type' => 'trade_cancelled',
                'label' => 'Trade Cancelled',
                'email_subject' => 'Trade cancelled',
                'email_body' => 'Trade #:hash has been cancelled. Escrow funds unlocked.',
                'sms_text' => 'Visadorm: Trade cancelled. Escrow unlocked.',
                'variables_guide' => ':hash, :amount, :merchant_name',
            ],
            [
                'type' => 'trade_expired',
                'label' => 'Trade Expired',
                'email_subject' => 'Trade expired',
                'email_body' => 'Trade #:hash has expired. Escrow funds unlocked.',
                'sms_text' => 'Visadorm: Trade expired. Escrow unlocked.',
                'variables_guide' => ':hash, :amount, :merchant_name',
            ],
            [
                'type' => 'dispute_opened',
                'label' => 'Dispute Opened',
                'email_subject' => 'Dispute opened on trade',
                'email_body' => 'Trade #:hash: Dispute opened — :reason',
                'sms_text' => 'Visadorm: Dispute opened on your trade. Check dashboard.',
                'variables_guide' => ':hash, :amount, :reason, :merchant_name',
            ],
            [
                'type' => 'kyc_reviewed',
                'label' => 'KYC Reviewed',
                'email_subject' => 'KYC document :status',
                'email_body' => 'Your :document_type document has been :status.',
                'sms_text' => 'Visadorm: Your KYC document was :status.',
                'variables_guide' => ':document_type, :reason, :merchant_name, :status',
            ],
        ];

        foreach ($templates as $template) {
            DB::table('message_templates')->updateOrInsert(
                ['type' => $template['type']],
                array_merge($template, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]),
            );
        }
    }

    public function down(): void
    {
        DB::table('message_templates')->whereIn('type', [
            'trade_initiated', 'payment_marked', 'bank_proof_uploaded',
            'buyer_id_submitted', 'trade_completed', 'trade_cancelled',
            'trade_expired', 'dispute_opened', 'kyc_reviewed',
        ])->delete();
    }
};
