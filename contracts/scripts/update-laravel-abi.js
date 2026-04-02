/**
 * update-laravel-abi.js
 *
 * Reads compiled Hardhat ABI artifacts and copies the ABI arrays
 * into the Laravel storage directory for use by BlockchainService.
 *
 * Also reads the latest deployment file (if any) and prints the
 * contract addresses that should be configured in admin settings.
 *
 * Usage:
 *   node contracts/scripts/update-laravel-abi.js
 */

const fs = require("fs");
const path = require("path");

const PROJECT_ROOT = path.resolve(__dirname, "..", "..");
const ARTIFACTS_DIR = path.join(
  PROJECT_ROOT,
  "contracts",
  "artifacts",
  "contracts"
);
const LARAVEL_CONTRACTS_DIR = path.join(
  PROJECT_ROOT,
  "storage",
  "app",
  "contracts"
);
const DEPLOYMENTS_DIR = path.join(PROJECT_ROOT, "contracts", "deployments");

const CONTRACTS = [
  {
    artifact: "TradeEscrowContract.sol/TradeEscrowContract.json",
    output: "TradeEscrowContract.abi.json",
  },
  {
    artifact: "SoulboundTradeNFT.sol/SoulboundTradeNFT.json",
    output: "SoulboundTradeNFT.abi.json",
  },
];

function main() {
  console.log("=".repeat(60));
  console.log("Visadorm P2P — Update Laravel ABI Files");
  console.log("=".repeat(60));

  // Ensure output directory exists
  if (!fs.existsSync(LARAVEL_CONTRACTS_DIR)) {
    fs.mkdirSync(LARAVEL_CONTRACTS_DIR, { recursive: true });
    console.log(`\nCreated directory: ${LARAVEL_CONTRACTS_DIR}`);
  }

  // Extract and copy ABIs
  for (const contract of CONTRACTS) {
    const artifactPath = path.join(ARTIFACTS_DIR, contract.artifact);
    const outputPath = path.join(LARAVEL_CONTRACTS_DIR, contract.output);

    if (!fs.existsSync(artifactPath)) {
      console.error(`\n  [SKIP] Artifact not found: ${artifactPath}`);
      console.error("         Run 'npx hardhat compile' first.");
      continue;
    }

    const artifact = JSON.parse(fs.readFileSync(artifactPath, "utf-8"));
    const abi = artifact.abi;

    fs.writeFileSync(outputPath, JSON.stringify(abi, null, 2));
    console.log(
      `\n  [OK] ${contract.output} — ${abi.length} entries extracted`
    );
    console.log(`       -> ${outputPath}`);
  }

  // Find and display latest deployment addresses
  console.log("\n" + "=".repeat(60));
  console.log("Deployment Addresses");
  console.log("=".repeat(60));

  if (!fs.existsSync(DEPLOYMENTS_DIR)) {
    console.log(
      "\n  No deployments directory found. Run the deploy script first:"
    );
    console.log(
      "  npx hardhat run contracts/scripts/deploy.js --network <network>"
    );
    console.log("\n  Then update these settings in Admin > Blockchain Settings:");
    console.log("    - Trade Escrow Address");
    console.log("    - Soulbound NFT Address");
    console.log("    - USDC Address");
    console.log("    - Gas Wallet Address");
    return;
  }

  // Get the most recent deployment file
  const deployFiles = fs
    .readdirSync(DEPLOYMENTS_DIR)
    .filter((f) => f.endsWith(".json"))
    .sort()
    .reverse();

  if (deployFiles.length === 0) {
    console.log("\n  No deployment JSON files found in:", DEPLOYMENTS_DIR);
    return;
  }

  const latestFile = deployFiles[0];
  const deployment = JSON.parse(
    fs.readFileSync(path.join(DEPLOYMENTS_DIR, latestFile), "utf-8")
  );

  console.log(`\n  Latest deployment: ${latestFile}`);
  console.log(`  Network: ${deployment.network}`);
  console.log("");
  console.log("  Update these in Admin > Blockchain Settings:");
  console.log("  ─────────────────────────────────────────────");
  console.log(`  Trade Escrow Address:   ${deployment.tradeEscrowContract}`);
  console.log(`  Soulbound NFT Address:  ${deployment.soulboundTradeNFT}`);
  console.log(`  USDC Address:           ${deployment.usdc}`);
  console.log(`  Fee Wallet Address:     ${deployment.feeWallet}`);
  console.log(`  Gas Wallet Address:     ${deployment.operator}`);
  console.log(`  Admin Multisig Address: ${deployment.admin}`);
  console.log("");
  console.log("  Or set via .env (from deployment):");

  const envPath = path.join(
    DEPLOYMENTS_DIR,
    `${deployment.network}.env`
  );
  if (fs.existsSync(envPath)) {
    console.log(`  ${envPath}`);
  }

  console.log("\n" + "=".repeat(60));
  console.log("Done. BlockchainService will use the new ABIs on next request.");
  console.log("=".repeat(60));
}

main();
