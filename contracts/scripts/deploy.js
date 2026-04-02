const hre = require("hardhat");
const { ethers } = hre;
const fs = require("fs");
const path = require("path");

async function main() {
  const [deployer] = await ethers.getSigners();
  const network = hre.network.name;

  console.log("=".repeat(60));
  console.log("Visadorm P2P — Contract Deployment");
  console.log("=".repeat(60));
  console.log(`Network:  ${network}`);
  console.log(`Deployer: ${deployer.address}`);
  console.log(`Balance:  ${ethers.formatEther(await ethers.provider.getBalance(deployer.address))} ETH`);
  console.log("=".repeat(60));

  // ─── Configuration ───
  // On mainnet, use real USDC address. On testnet/hardhat, deploy a mock.
  const USDC_MAINNET = "0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913"; // Base USDC
  const FEE_WALLET = process.env.FEE_WALLET || deployer.address;
  const ADMIN_ADDRESS = process.env.ADMIN_ADDRESS || deployer.address;
  const OPERATOR_ADDRESS = process.env.OPERATOR_ADDRESS || deployer.address;

  let usdcAddress;

  // ─── Step 1: USDC ───
  if (network === "base") {
    // Mainnet — use real USDC
    usdcAddress = USDC_MAINNET;
    console.log("\n[1/4] Using Base mainnet USDC:", usdcAddress);
  } else {
    // Testnet or Hardhat — deploy MockERC20
    console.log("\n[1/4] Deploying MockERC20 (USDC)...");
    const usdc = await ethers.deployContract("MockERC20", [
      "USD Coin",
      "USDC",
      6,
    ]);
    await usdc.waitForDeployment();
    usdcAddress = await usdc.getAddress();
    console.log("  MockERC20 deployed:", usdcAddress);
  }

  // ─── Step 2: SoulboundTradeNFT ───
  console.log("\n[2/4] Deploying SoulboundTradeNFT...");
  const nft = await ethers.deployContract("SoulboundTradeNFT");
  await nft.waitForDeployment();
  const nftAddress = await nft.getAddress();
  console.log("  SoulboundTradeNFT deployed:", nftAddress);

  // ─── Step 3: TradeEscrowContract ───
  console.log("\n[3/4] Deploying TradeEscrowContract...");
  console.log("  USDC:     ", usdcAddress);
  console.log("  Fee Wallet:", FEE_WALLET);
  console.log("  NFT:      ", nftAddress);
  console.log("  Admin:    ", ADMIN_ADDRESS);
  console.log("  Operator: ", OPERATOR_ADDRESS);

  const escrow = await ethers.deployContract("TradeEscrowContract", [
    usdcAddress,
    FEE_WALLET,
    nftAddress,
    ADMIN_ADDRESS,
    OPERATOR_ADDRESS,
  ]);
  await escrow.waitForDeployment();
  const escrowAddress = await escrow.getAddress();
  console.log("  TradeEscrowContract deployed:", escrowAddress);

  // ─── Step 4: Grant MINTER_ROLE ───
  console.log("\n[4/4] Granting MINTER_ROLE to TradeEscrowContract on NFT...");
  const MINTER_ROLE = await nft.MINTER_ROLE();
  const tx = await nft.grantRole(MINTER_ROLE, escrowAddress);
  await tx.wait();
  console.log("  MINTER_ROLE granted.");

  // ─── Summary ───
  console.log("\n" + "=".repeat(60));
  console.log("DEPLOYMENT COMPLETE");
  console.log("=".repeat(60));

  const addresses = {
    network,
    deployer: deployer.address,
    usdc: usdcAddress,
    soulboundTradeNFT: nftAddress,
    tradeEscrowContract: escrowAddress,
    feeWallet: FEE_WALLET,
    admin: ADMIN_ADDRESS,
    operator: OPERATOR_ADDRESS,
  };

  console.log(JSON.stringify(addresses, null, 2));

  // ─── Save to file ───
  const outputDir = path.join(__dirname, "..", "deployments");
  if (!fs.existsSync(outputDir)) {
    fs.mkdirSync(outputDir, { recursive: true });
  }

  const timestamp = new Date().toISOString().replace(/[:.]/g, "-");
  const filename = `${network}-${timestamp}.json`;
  const filepath = path.join(outputDir, filename);
  fs.writeFileSync(filepath, JSON.stringify(addresses, null, 2));
  console.log(`\nAddresses saved to: ${filepath}`);

  // Also save a .env-style file for easy integration
  const envContent = [
    `# Visadorm P2P — Deployed Addresses (${network})`,
    `# Deployed at: ${new Date().toISOString()}`,
    `NEXT_PUBLIC_USDC_ADDRESS=${usdcAddress}`,
    `NEXT_PUBLIC_NFT_ADDRESS=${nftAddress}`,
    `NEXT_PUBLIC_ESCROW_ADDRESS=${escrowAddress}`,
    `NEXT_PUBLIC_FEE_WALLET=${FEE_WALLET}`,
    `NEXT_PUBLIC_CHAIN_ID=${hre.network.config.chainId || 31337}`,
    "",
  ].join("\n");

  const envPath = path.join(outputDir, `${network}.env`);
  fs.writeFileSync(envPath, envContent);
  console.log(`Env file saved to: ${envPath}`);
}

main()
  .then(() => process.exit(0))
  .catch((error) => {
    console.error(error);
    process.exit(1);
  });
