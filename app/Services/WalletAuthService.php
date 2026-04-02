<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use kornrunner\Keccak;
use Mdanter\Ecc\Curves\CurveFactory;
use Mdanter\Ecc\Curves\SecgCurve;
use Mdanter\Ecc\Math\ConstantTimeMath;

class WalletAuthService
{
    /**
     * Verify that a signature was produced by the claimed wallet address.
     */
    public function verifySignature(string $message, string $signature, string $expectedAddress): bool
    {
        $recoveredAddress = $this->recoverAddress($message, $signature);

        $verified = strtolower($recoveredAddress) === strtolower($expectedAddress);

        if (! $verified) {
            Log::warning('Wallet signature verification failed', [
                'wallet' => $expectedAddress,
                'recovered' => $recoveredAddress,
            ]);
        }

        return $verified;
    }

    /**
     * Recover the signer address from a signed message using EC public key recovery.
     * Q = r^-1 * (s*R + (n-h)*G)
     */
    public function recoverAddress(string $message, string $signature): string
    {
        $messageHash = $this->hashMessage($message);
        $sig = $this->parseSignature($signature);

        $generator = CurveFactory::getGeneratorByName(SecgCurve::NAME_SECP_256K1);
        $curve     = $generator->getCurve();
        $n         = $generator->getOrder();

        $r = gmp_init($sig['r'], 16);
        $s = gmp_init($sig['s'], 16);
        $v = $sig['v'];                        // 0 or 1
        $h = gmp_init($messageHash, 16);

        // Recover the ephemeral point R from r and the parity bit v
        $x      = $r;
        $wasOdd = ($v === 1);
        $y      = $curve->recoverYfromX($wasOdd, $x);
        $R      = $curve->getPoint($x, $y, $n);

        // Q = r^-1 * (s*R + (n-h)*G)  — avoids negation operator
        $rInv  = gmp_invert($r, $n);
        $nMinH = gmp_mod(gmp_sub($n, gmp_mod($h, $n)), $n);

        $sR   = $R->mul($s);
        $hG   = $generator->mul($nMinH);
        $Q    = $sR->add($hG)->mul($rInv);

        // Encode the uncompressed public key (no 04 prefix) and derive address
        $xHex = str_pad(gmp_strval($Q->getX(), 16), 64, '0', STR_PAD_LEFT);
        $yHex = str_pad(gmp_strval($Q->getY(), 16), 64, '0', STR_PAD_LEFT);

        $addressHash = Keccak::hash(hex2bin($xHex . $yHex), 256);

        return '0x' . substr($addressHash, -40);
    }

    /**
     * Hash a message with the Ethereum signed message prefix (EIP-191).
     */
    public function hashMessage(string $message): string
    {
        $prefix = "\x19Ethereum Signed Message:\n" . strlen($message);

        return Keccak::hash($prefix . $message, 256);
    }

    /**
     * Generate a unique nonce for wallet authentication.
     */
    public function generateNonce(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Build the message that the wallet will sign.
     */
    public function buildSignMessage(string $nonce): string
    {
        return "Sign the nonce to login.\nNonce: {$nonce}";
    }

    /**
     * Parse a hex signature string into r, s, v components.
     */
    private function parseSignature(string $signature): array
    {
        $signature = str_starts_with($signature, '0x') ? substr($signature, 2) : $signature;

        $r = substr($signature, 0, 64);
        $s = substr($signature, 64, 64);
        $v = hexdec(substr($signature, 128, 2));

        // EIP-155 normalization: v is 27/28 in Ethereum, normalize to 0/1
        if ($v >= 27) {
            $v -= 27;
        }

        return compact('r', 's', 'v');
    }
}
