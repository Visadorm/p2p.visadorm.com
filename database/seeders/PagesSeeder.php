<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PageStatus;
use App\Models\Page;
use Illuminate\Database\Seeder;

class PagesSeeder extends Seeder
{
    public function run(): void
    {
        Page::updateOrCreate(
            ['slug' => 'terms'],
            [
                'title' => 'Terms of Service',
                'body' => $this->termsBody(),
                'excerpt' => 'The rules and obligations governing your use of the Visadorm P2P platform.',
                'status' => PageStatus::Published,
                'show_in_header' => false,
                'show_in_footer' => true,
                'sort_order' => 10,
                'meta_title' => 'Terms of Service — Visadorm P2P',
                'meta_description' => 'Read the Visadorm P2P Terms of Service covering account use, trades, fees, disputes, and liability.',
                'published_at' => now(),
            ]
        );

        Page::updateOrCreate(
            ['slug' => 'privacy'],
            [
                'title' => 'Privacy Policy',
                'body' => $this->privacyBody(),
                'excerpt' => 'How Visadorm P2P collects, uses, and protects your personal data.',
                'status' => PageStatus::Published,
                'show_in_header' => false,
                'show_in_footer' => true,
                'sort_order' => 20,
                'meta_title' => 'Privacy Policy — Visadorm P2P',
                'meta_description' => 'Learn how Visadorm P2P handles personal information, KYC data, and your privacy rights.',
                'published_at' => now(),
            ]
        );
    }

    private function termsBody(): string
    {
        return <<<'MD'
# Terms of Service

_Last updated: {date}_

Welcome to **Visadorm P2P**. By accessing or using our peer-to-peer USDC trading platform, you agree to these Terms of Service. Please read them carefully.

## 1. Eligibility

You must be at least 18 years of age and legally able to enter into binding contracts in your jurisdiction. You are responsible for ensuring that your use of the platform does not violate the laws of your country.

## 2. Your Account

You access the platform using a self-custodied Web3 wallet. You are solely responsible for:

- Safeguarding your wallet private key and recovery phrase.
- All activity that occurs through your wallet address.
- The accuracy of any profile, contact, or KYC information you provide.

We do not store your private keys and cannot recover them if lost.

## 3. Trades and Escrow

- USDC funds are locked in a non-custodial smart contract escrow during each trade.
- Once a trade reaches the Completed state, it is terminal and cannot be reversed or disputed.
- Disputes are only possible while funds remain in escrow (EscrowLocked or PaymentSent state).
- Payment arrangements between buyer and seller (bank transfer, cash meeting, etc.) occur off-chain. Visadorm P2P is not a party to those payments.

## 4. Fees

Platform fees are set by the smart contract and disclosed before a trade is initiated. Network gas fees, exchange-rate spreads, and third-party payment-processor fees are your responsibility.

## 5. KYC and Compliance

For certain activities — including higher-volume trading and withdrawals — we may require identity verification. Submitting false or misleading KYC documents may result in account suspension and reporting to competent authorities.

## 6. Prohibited Activities

You agree not to use the platform to:

- Launder the proceeds of crime or finance terrorism.
- Trade on behalf of a sanctioned person, entity, or jurisdiction.
- Impersonate another person, entity, or merchant.
- Circumvent the platform's dispute, fee, or KYC controls.

## 7. Disclaimer and Limitation of Liability

The platform is provided "as is", without warranty of any kind. To the fullest extent permitted by law, Visadorm P2P, its contributors, and its operators are not liable for:

- Losses caused by smart-contract bugs, network congestion, or chain reorganizations.
- Losses caused by counterparty fraud or failure to deliver off-chain payment.
- Losses caused by your failure to secure your wallet or verify trade details.

## 8. Changes

We may update these Terms from time to time. Material changes will be announced on the platform. Continued use after changes take effect constitutes acceptance.

## 9. Contact

Questions about these Terms should be sent to the support contact listed on the site.
MD;
    }

    private function privacyBody(): string
    {
        return <<<'MD'
# Privacy Policy

_Last updated: {date}_

This Privacy Policy explains how **Visadorm P2P** collects, uses, and protects personal information in connection with the peer-to-peer USDC trading platform.

## 1. Information We Collect

We may collect:

- **Wallet data** — public wallet addresses and on-chain transaction history.
- **Profile data** — optional username, bio, email, phone number, and country.
- **KYC data** — government-issued ID, selfie, and related documents, collected only when a higher verification level is required.
- **Trade data** — escrow state, dispute evidence, and merchant ratings.
- **Technical data** — IP address, device/browser type, and aggregate usage analytics.

## 2. How We Use Information

We use personal information to:

- Operate and secure the platform.
- Process trades, disputes, and merchant payouts.
- Meet anti-money-laundering (AML) and counter-terrorism-financing (CTF) obligations.
- Notify you about trades, disputes, and platform updates.
- Improve reliability, performance, and user experience.

## 3. Legal Bases

We process personal data under the following legal bases (where applicable):

- **Contract** — to deliver the service you have requested.
- **Legal obligation** — to comply with KYC, AML, and tax obligations.
- **Legitimate interest** — to secure the platform against fraud and abuse.
- **Consent** — for optional marketing communications (you can withdraw consent at any time).

## 4. Sharing

We share personal data only with:

- Authorised service providers (cloud hosting, KYC vendors, email/SMS gateways) under confidentiality agreements.
- Competent authorities when legally required.
- Successors in the event of a merger, acquisition, or asset transfer.

We do not sell personal data.

## 5. Retention

KYC records are retained for the period required by applicable AML regulations. Other personal data is retained only as long as necessary to deliver the service or meet legal obligations.

## 6. Your Rights

Depending on your jurisdiction, you may have the right to:

- Access, correct, or delete your personal data.
- Object to or restrict certain processing.
- Request data portability.
- Lodge a complaint with your local data-protection authority.

Requests can be sent to the support contact listed on the site.

## 7. Security

We apply industry-standard safeguards — including encryption in transit, restricted access controls, and audit logging — to protect personal data. No system is perfectly secure; please report any suspected incident immediately.

## 8. Changes

We may update this Privacy Policy from time to time. Material changes will be announced on the platform.
MD;
    }
}
