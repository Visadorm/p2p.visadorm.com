const hre = require("hardhat");
const { ethers } = hre;
const fs = require("fs");
const path = require("path");

async function main() {
  const [deployer] = await ethers.getSigners();
  const network = hre.network.name;

  const usdcAddress = required("USDC_ADDRESS");
  const nftAddress = required("NFT_ADDRESS");
  const feeWallet = process.env.FEE_WALLET || deployer.address;
  const adminAddress = process.env.ADMIN_ADDRESS || deployer.address;
  const operatorAddress = process.env.OPERATOR_ADDRESS || deployer.address;

  console.log("=".repeat(60));
  console.log("Visadorm P2P — TradeEscrowContract Redeploy");
  console.log("=".repeat(60));
  console.log(`Network:   ${network}`);
  console.log(`Deployer:  ${deployer.address}`);
  console.log(`Balance:   ${ethers.formatEther(await ethers.provider.getBalance(deployer.address))} ETH`);
  console.log(`USDC (reused):     ${usdcAddress}`);
  console.log(`NFT  (reused):     ${nftAddress}`);
  console.log(`Fee Wallet:        ${feeWallet}`);
  console.log(`Admin (multisig):  ${adminAddress}`);
  console.log(`Operator (gas):    ${operatorAddress}`);
  console.log("=".repeat(60));

  console.log("\n[1/2] Deploying new TradeEscrowContract...");
  const escrow = await ethers.deployContract("TradeEscrowContract", [
    usdcAddress, feeWallet, nftAddress, adminAddress, operatorAddress,
  ]);
  await escrow.waitForDeployment();
  const escrowAddress = await escrow.getAddress();
  console.log(`  Deployed at: ${escrowAddress}`);

  console.log("\n[2/2] Granting MINTER_ROLE on existing NFT to new escrow...");
  console.log("  NOTE: requires deployer to hold MINTER_ROLE admin on the NFT.");
  console.log("  If NFT admin is the multisig, this step must be done via multisig instead.");
  try {
    const nft = await ethers.getContractAt("SoulboundTradeNFT", nftAddress);
    const MINTER_ROLE = await nft.MINTER_ROLE();
    const tx = await nft.grantRole(MINTER_ROLE, escrowAddress);
    await tx.wait();
    console.log("  MINTER_ROLE granted.");
  } catch (err) {
    console.log(`  [SKIP] grant failed: ${err.shortMessage || err.message}`);
    console.log("         Run grant via multisig before cash trades work.");
  }

  const addresses = {
    network,
    deployer: deployer.address,
    usdc: usdcAddress,
    soulboundTradeNFT: nftAddress,
    tradeEscrowContract: escrowAddress,
    feeWallet,
    admin: adminAddress,
    operator: operatorAddress,
    note: "Escrow redeploy — reused existing USDC + NFT.",
  };

  console.log("\n" + "=".repeat(60));
  console.log("DEPLOYMENT COMPLETE");
  console.log("=".repeat(60));
  console.log(JSON.stringify(addresses, null, 2));

  const outputDir = path.join(__dirname, "..", "deployments");
  if (!fs.existsSync(outputDir)) {
    fs.mkdirSync(outputDir, { recursive: true });
  }
  const timestamp = new Date().toISOString().replace(/[:.]/g, "-");
  const filename = `${network}-redeploy-${timestamp}.json`;
  const filepath = path.join(outputDir, filename);
  fs.writeFileSync(filepath, JSON.stringify(addresses, null, 2));
  console.log(`\nSaved: ${filepath}`);

  console.log("\nNext steps:");
  console.log("  1. Update Admin > Blockchain Settings > Trade Escrow Address to:");
  console.log(`     ${escrowAddress}`);
  console.log("  2. Verify on BaseScan with:");
  console.log(`     npx hardhat verify --network ${network} ${escrowAddress} ${usdcAddress} ${feeWallet} ${nftAddress} ${adminAddress} ${operatorAddress}`);
}

function required(envKey) {
  const v = process.env[envKey];
  if (!v) {
    throw new Error(`Missing env: ${envKey} (set to deployed address before running)`);
  }
  return v;
}

main()
  .then(() => process.exit(0))
  .catch((err) => {
    console.error(err);
    process.exit(1);
  });
