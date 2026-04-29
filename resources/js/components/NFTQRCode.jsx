import { QRCodeSVG } from "qrcode.react"
import { QrCode, ShieldCheck } from "@phosphor-icons/react"
import { Card, CardHeader, CardTitle, CardContent } from "@/components/ui/card"

export default function NFTQRCode({ tradeHash, tokenId, amountUsdc, verifyUrl: customUrl }) {
  // Sell flow passes a custom URL (e.g. /sell/trade/{hash}). Buy flow defaults
  // to /verify/{hash} which renders the existing buy verify page.
  const verifyUrl = customUrl || `${window.location.origin}/verify/${tradeHash}`

  return (
    <Card className="border-border/50">
      <CardHeader>
        <CardTitle className="flex items-center gap-2 text-base">
          <QrCode weight="duotone" size={20} className="text-primary" />
          Trade Verification QR
        </CardTitle>
      </CardHeader>
      <CardContent>
        <div className="flex flex-col items-center gap-4">
          <div className="flex size-52 items-center justify-center rounded-2xl border-2 border-border/50 bg-white p-3">
            <QRCodeSVG
              value={verifyUrl}
              size={180}
              level="H"
              includeMargin={false}
            />
          </div>
          <div className="text-center space-y-2">
            <div className="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1">
              <ShieldCheck weight="fill" size={14} className="text-primary" />
              <span className="text-xs font-medium text-primary">On-chain verified</span>
            </div>
            <p className="font-mono text-sm text-muted-foreground">
              Trade: <span className="font-semibold text-foreground">{tradeHash ? `${tradeHash.slice(0, 10)}...${tradeHash.slice(-8)}` : "N/A"}</span>
            </p>
            <p className="text-sm text-muted-foreground">
              Merchant scans this to verify the trade before releasing funds
            </p>
          </div>
        </div>
      </CardContent>
    </Card>
  )
}
