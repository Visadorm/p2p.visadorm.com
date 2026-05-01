/**
 * redeploy-escrow.js
 *
 * Redeploys ONLY the TradeEscrowContract (B1 user-signed wrappers added).
 * Reuses existing MockUSDC + SoulboundTradeNFT from previous deployment.
 *
 * Usage:
 *   npx hardhat run contracts/scripts/redeploy-escrow.js --network baseSepolia
 */

const hre = require("hardhat");
const { ethers } = hre;
const fs = require("fs");
const path = require("path");

async function main() {
  const network = hre.network.name;
  const [deployer] = await ethers.getSigners();

  console.log("=".repeat(60));
  console.log("Visadorm P2P — TradeEscrowContract Redeploy (B1)");
  console.log("=".repeat(60));
  console.log(`Network:  ${network}`);
  console.log(`Deployer: ${deployer.address}`);
  console.log(`Balance:  ${ethers.formatEther(await ethers.provider.getBalance(deployer.address))} ETH`);

  // ─── Load previous deployment to reuse infra contracts ───
  const DEPLOYMENTS_DIR = path.join(__dirname, "..", "deployments");
  const previous = fs
    .readdirSync(DEPLOYMENTS_DIR)
    .filter((f) => f.startsWith(`${network}-`) && f.endsWith(".json"))
    .sort()
    .reverse()[0];

  if (!previous) {
    throw new Error(`No previous ${network} deployment found in ${DEPLOYMENTS_DIR}`);
  }

  const prev = JSON.parse(fs.readFileSync(path.join(DEPLOYMENTS_DIR, previous), "utf-8"));
  console.log("\nReusing previous deployment:", previous);
  console.log(`  USDC:     ${prev.usdc}`);
  console.log(`  NFT:      ${prev.soulboundTradeNFT}`);
  console.log(`  Old Escrow (will be deprecated): ${prev.tradeEscrowContract}`);

  const FEE_WALLET = process.env.FEE_WALLET || prev.feeWallet || deployer.address;
  const ADMIN_ADDRESS = process.env.ADMIN_ADDRESS || prev.admin || deployer.address;
  const OPERATOR_ADDRESS = process.env.OPERATOR_ADDRESS || prev.operator || deployer.address;

  console.log("\nNew escrow constructor args:");
  console.log(`  USDC:     ${prev.usdc}`);
  console.log(`  Fee:      ${FEE_WALLET}`);
  console.log(`  NFT:      ${prev.soulboundTradeNFT}`);
  console.log(`  Admin:    ${ADMIN_ADDRESS}`);
  console.log(`  Operator: ${OPERATOR_ADDRESS}`);
  console.log("=".repeat(60));

  // ─── Deploy new TradeEscrowContract ───
  console.log("\n[1/2] Deploying new TradeEscrowContract...");
  const escrow = await ethers.deployContract("TradeEscrowContract", [
    prev.usdc,
    FEE_WALLET,
    prev.soulboundTradeNFT,
    ADMIN_ADDRESS,
    OPERATOR_ADDRESS,
  ]);
  await escrow.waitForDeployment();
  const escrowAddress = await escrow.getAddress();
  console.log(`  New TradeEscrowContract: ${escrowAddress}`);

  // ─── Grant MINTER_ROLE on existing NFT ───
  console.log("\n[2/2] Granting MINTER_ROLE on existing NFT...");
  const nft = await ethers.getContractAt("SoulboundTradeNFT", prev.soulboundTradeNFT);
  const MINTER_ROLE = await nft.MINTER_ROLE();
  const tx = await nft.grantRole(MINTER_ROLE, escrowAddress);
  await tx.wait();
  console.log(`  MINTER_ROLE granted to ${escrowAddress}`);

  // ─── Save deployment record ───
  const addresses = {
    network,
    deployer: deployer.address,
    usdc: prev.usdc,
    soulboundTradeNFT: prev.soulboundTradeNFT,
    tradeEscrowContract: escrowAddress,
    previousTradeEscrowContract: prev.tradeEscrowContract,
    feeWallet: FEE_WALLET,
    admin: ADMIN_ADDRESS,
    operator: OPERATOR_ADDRESS,
    note: "B1 user-signed wrappers (markPaymentSentByBuyer, confirmPaymentByMerchant, cancelTradeByMerchant)",
  };

  console.log("\n" + "=".repeat(60));
  console.log("DEPLOYMENT COMPLETE");
  console.log("=".repeat(60));
  console.log(JSON.stringify(addresses, null, 2));

  const timestamp = new Date().toISOString().replace(/[:.]/g, "-");
  const filename = `${network}-${timestamp}.json`;
  const filepath = path.join(DEPLOYMENTS_DIR, filename);
  fs.writeFileSync(filepath, JSON.stringify(addresses, null, 2));
  console.log(`\nAddresses saved: ${filepath}`);

  // Update env file
  const envContent = [
    `# Visadorm P2P — Deployed Addresses (${network}) — B1 redeploy`,
    `# Deployed: ${new Date().toISOString()}`,
    `NEXT_PUBLIC_USDC_ADDRESS=${prev.usdc}`,
    `NEXT_PUBLIC_NFT_ADDRESS=${prev.soulboundTradeNFT}`,
    `NEXT_PUBLIC_ESCROW_ADDRESS=${escrowAddress}`,
    `NEXT_PUBLIC_FEE_WALLET=${FEE_WALLET}`,
    `NEXT_PUBLIC_CHAIN_ID=${hre.network.config.chainId || 84532}`,
    "",
  ].join("\n");

  fs.writeFileSync(path.join(DEPLOYMENTS_DIR, `${network}.env`), envContent);
  console.log(`Env file updated: ${path.join(DEPLOYMENTS_DIR, `${network}.env`)}`);

  console.log("\nNext steps:");
  console.log("  1. cd .. && node contracts/scripts/update-laravel-abi.js");
  console.log("  2. Update Filament > Blockchain Settings > Trade Escrow Address");
  console.log(`     New: ${escrowAddress}`);
  console.log("  3. (Optional) Verify on BaseScan:");
  console.log(`     npx hardhat verify --network ${network} ${escrowAddress} ${prev.usdc} ${FEE_WALLET} ${prev.soulboundTradeNFT} ${ADMIN_ADDRESS} ${OPERATOR_ADDRESS}`);
}

main()
  .then(() => process.exit(0))
  .catch((error) => {
    console.error(error);
    process.exit(1);
  });
