const hre = require("hardhat");

async function main() {
  const network = hre.network.name;

  // ─── Configuration ───
  const USDC_ADDRESS = process.env.USDC_ADDRESS;
  const NFT_ADDRESS = process.env.NFT_ADDRESS;
  const ESCROW_ADDRESS = process.env.ESCROW_ADDRESS;
  const FEE_WALLET = process.env.FEE_WALLET;
  const ADMIN_ADDRESS = process.env.ADMIN_ADDRESS;
  const OPERATOR_ADDRESS = process.env.OPERATOR_ADDRESS;

  if (
    !USDC_ADDRESS ||
    !NFT_ADDRESS ||
    !ESCROW_ADDRESS ||
    !FEE_WALLET ||
    !ADMIN_ADDRESS ||
    !OPERATOR_ADDRESS
  ) {
    console.error("Missing required env vars.");
    console.error(
      "Required: USDC_ADDRESS, NFT_ADDRESS, ESCROW_ADDRESS, FEE_WALLET, ADMIN_ADDRESS, OPERATOR_ADDRESS"
    );
    console.error("\nExample:");
    console.error(
      "  USDC_ADDRESS=0x... NFT_ADDRESS=0x... ESCROW_ADDRESS=0x... FEE_WALLET=0x... ADMIN_ADDRESS=0x... OPERATOR_ADDRESS=0x... npm run verify:sepolia"
    );
    process.exit(1);
  }

  console.log("=".repeat(60));
  console.log("Visadorm P2P — Contract Verification on BaseScan");
  console.log("=".repeat(60));
  console.log(`Network: ${network}`);
  console.log("=".repeat(60));

  // ─── Verify MockERC20 (only on testnet) ───
  if (network !== "base") {
    console.log("\n[1/3] Verifying MockERC20...");
    try {
      await hre.run("verify:verify", {
        address: USDC_ADDRESS,
        constructorArguments: ["USD Coin", "USDC", 6],
      });
      console.log("  MockERC20 verified.");
    } catch (err) {
      if (err.message.includes("Already Verified")) {
        console.log("  MockERC20 already verified.");
      } else {
        console.error("  MockERC20 verification failed:", err.message);
      }
    }
  } else {
    console.log("\n[1/3] Skipping MockERC20 (mainnet uses real USDC).");
  }

  // ─── Verify SoulboundTradeNFT ───
  console.log("\n[2/3] Verifying SoulboundTradeNFT...");
  try {
    await hre.run("verify:verify", {
      address: NFT_ADDRESS,
      constructorArguments: [],
    });
    console.log("  SoulboundTradeNFT verified.");
  } catch (err) {
    if (err.message.includes("Already Verified")) {
      console.log("  SoulboundTradeNFT already verified.");
    } else {
      console.error("  SoulboundTradeNFT verification failed:", err.message);
    }
  }

  // ─── Verify TradeEscrowContract ───
  console.log("\n[3/3] Verifying TradeEscrowContract...");
  try {
    await hre.run("verify:verify", {
      address: ESCROW_ADDRESS,
      constructorArguments: [
        USDC_ADDRESS,
        FEE_WALLET,
        NFT_ADDRESS,
        ADMIN_ADDRESS,
        OPERATOR_ADDRESS,
      ],
    });
    console.log("  TradeEscrowContract verified.");
  } catch (err) {
    if (err.message.includes("Already Verified")) {
      console.log("  TradeEscrowContract already verified.");
    } else {
      console.error("  TradeEscrowContract verification failed:", err.message);
    }
  }

  console.log("\n" + "=".repeat(60));
  console.log("VERIFICATION COMPLETE");
  console.log("=".repeat(60));
}

main()
  .then(() => process.exit(0))
  .catch((error) => {
    console.error(error);
    process.exit(1);
  });
