const hre = require("hardhat");
const { ethers } = hre;
const readline = require("readline");

function askConfirmation(question) {
  const rl = readline.createInterface({
    input: process.stdin,
    output: process.stdout,
  });
  return new Promise((resolve) => {
    rl.question(question, (answer) => {
      rl.close();
      resolve(answer.toLowerCase().trim());
    });
  });
}

async function main() {
  const [deployer] = await ethers.getSigners();
  const network = hre.network.name;

  // ─── Configuration ───
  const ESCROW_ADDRESS = process.env.ESCROW_ADDRESS;
  const NFT_ADDRESS = process.env.NFT_ADDRESS;
  const MULTISIG_ADDRESS = process.env.MULTISIG_ADDRESS;

  if (!ESCROW_ADDRESS || !NFT_ADDRESS || !MULTISIG_ADDRESS) {
    console.error(
      "Missing required env vars: ESCROW_ADDRESS, NFT_ADDRESS, MULTISIG_ADDRESS"
    );
    console.error("Example:");
    console.error(
      "  ESCROW_ADDRESS=0x... NFT_ADDRESS=0x... MULTISIG_ADDRESS=0x... npx hardhat run scripts/transfer-ownership.js --network base"
    );
    process.exit(1);
  }

  console.log("=".repeat(60));
  console.log("Visadorm P2P — Transfer Ownership to Multisig");
  console.log("=".repeat(60));
  console.log(`Network:     ${network}`);
  console.log(`Deployer:    ${deployer.address}`);
  console.log(`Escrow:      ${ESCROW_ADDRESS}`);
  console.log(`NFT:         ${NFT_ADDRESS}`);
  console.log(`Multisig:    ${MULTISIG_ADDRESS}`);
  console.log("=".repeat(60));

  console.log("\n*** WARNING: THIS ACTION IS IRREVERSIBLE ***");
  console.log(
    "This will transfer DEFAULT_ADMIN_ROLE and ADMIN_ROLE to the multisig,"
  );
  console.log("then renounce the deployer's admin roles.");
  console.log("After this, only the multisig can manage the contracts.\n");

  const answer = await askConfirmation(
    'Type "TRANSFER" to confirm (anything else cancels): '
  );

  if (answer !== "transfer") {
    console.log("Cancelled.");
    process.exit(0);
  }

  const escrow = await ethers.getContractAt("TradeEscrowContract", ESCROW_ADDRESS);
  const nft = await ethers.getContractAt("SoulboundTradeNFT", NFT_ADDRESS);

  const DEFAULT_ADMIN_ROLE = await escrow.DEFAULT_ADMIN_ROLE();
  const ADMIN_ROLE = await escrow.ADMIN_ROLE();

  // ─── Step 1: Grant roles to multisig on Escrow ───
  console.log("\n[1/6] Granting DEFAULT_ADMIN_ROLE to multisig on Escrow...");
  let tx = await escrow.grantRole(DEFAULT_ADMIN_ROLE, MULTISIG_ADDRESS);
  await tx.wait();
  console.log("  Done.");

  console.log("[2/6] Granting ADMIN_ROLE to multisig on Escrow...");
  tx = await escrow.grantRole(ADMIN_ROLE, MULTISIG_ADDRESS);
  await tx.wait();
  console.log("  Done.");

  // ─── Step 2: Grant roles to multisig on NFT ───
  console.log("[3/6] Granting DEFAULT_ADMIN_ROLE to multisig on NFT...");
  tx = await nft.grantRole(DEFAULT_ADMIN_ROLE, MULTISIG_ADDRESS);
  await tx.wait();
  console.log("  Done.");

  // ─── Step 3: Renounce deployer's roles ───
  console.log("[4/6] Renouncing ADMIN_ROLE from deployer on Escrow...");
  tx = await escrow.renounceRole(ADMIN_ROLE, deployer.address);
  await tx.wait();
  console.log("  Done.");

  console.log("[5/6] Renouncing DEFAULT_ADMIN_ROLE from deployer on Escrow...");
  tx = await escrow.renounceRole(DEFAULT_ADMIN_ROLE, deployer.address);
  await tx.wait();
  console.log("  Done.");

  console.log("[6/6] Renouncing DEFAULT_ADMIN_ROLE from deployer on NFT...");
  tx = await nft.renounceRole(DEFAULT_ADMIN_ROLE, deployer.address);
  await tx.wait();
  console.log("  Done.");

  // ─── Verification ───
  console.log("\n" + "=".repeat(60));
  console.log("VERIFICATION");
  console.log("=".repeat(60));

  const escrowAdminOk = await escrow.hasRole(ADMIN_ROLE, MULTISIG_ADDRESS);
  const escrowDefaultOk = await escrow.hasRole(DEFAULT_ADMIN_ROLE, MULTISIG_ADDRESS);
  const nftDefaultOk = await nft.hasRole(DEFAULT_ADMIN_ROLE, MULTISIG_ADDRESS);
  const deployerEscrowAdmin = await escrow.hasRole(ADMIN_ROLE, deployer.address);
  const deployerEscrowDefault = await escrow.hasRole(DEFAULT_ADMIN_ROLE, deployer.address);
  const deployerNftDefault = await nft.hasRole(DEFAULT_ADMIN_ROLE, deployer.address);

  console.log(`Multisig has ADMIN_ROLE on Escrow:          ${escrowAdminOk ? "YES" : "NO"}`);
  console.log(`Multisig has DEFAULT_ADMIN_ROLE on Escrow:  ${escrowDefaultOk ? "YES" : "NO"}`);
  console.log(`Multisig has DEFAULT_ADMIN_ROLE on NFT:     ${nftDefaultOk ? "YES" : "NO"}`);
  console.log(`Deployer has ADMIN_ROLE on Escrow:          ${deployerEscrowAdmin ? "YES (ERROR!)" : "NO (good)"}`);
  console.log(`Deployer has DEFAULT_ADMIN_ROLE on Escrow:  ${deployerEscrowDefault ? "YES (ERROR!)" : "NO (good)"}`);
  console.log(`Deployer has DEFAULT_ADMIN_ROLE on NFT:     ${deployerNftDefault ? "YES (ERROR!)" : "NO (good)"}`);

  if (
    escrowAdminOk &&
    escrowDefaultOk &&
    nftDefaultOk &&
    !deployerEscrowAdmin &&
    !deployerEscrowDefault &&
    !deployerNftDefault
  ) {
    console.log("\nOwnership transfer COMPLETE. Deployer fully renounced.");
  } else {
    console.error("\nWARNING: Some checks failed. Review manually.");
  }
}

main()
  .then(() => process.exit(0))
  .catch((error) => {
    console.error(error);
    process.exit(1);
  });
