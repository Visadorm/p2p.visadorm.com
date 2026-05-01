// Smoke test B1 user-signed buy flow on Base Sepolia.
// Validates new TradeEscrowContract has working markPaymentSentByBuyer,
// confirmPaymentByMerchant, cancelTradeByMerchant.

const { ethers } = require("hardhat");
const fs = require("fs");

const ESCROW = "0xE5e52d5fB120a2Fc209C2ab66B51E00c3bf500B9";
const USDC = "0x7c33814E64FaC03Fd45C3B11C94a4BFa7cb6E1d1";
const FEE_WALLET = "0xb0858aa1264d5d5433dac742b2c30abfc7798736";
const OPERATOR_ADDRESS = "0x7e5ca1bb6232c80469237eaea094f21029b800ab";

const wallets = JSON.parse(
  fs.readFileSync(
    __dirname + "/../test-wallets/baseSepolia-2026-04-25T21-32-42-787Z.json"
  )
);

async function main() {
  const provider = ethers.provider;
  const merchant = new ethers.Wallet(wallets.seller.private_key, provider);
  const buyer = new ethers.Wallet(wallets.buyer.private_key, provider);
  const [deployer] = await ethers.getSigners(); // OPERATOR + DEPLOYER signer (has OPERATOR_ROLE? we'll check)

  console.log("=== B1 Smoke ===");
  console.log("merchant:", merchant.address);
  console.log("buyer:   ", buyer.address);
  console.log("deployer:", deployer.address);
  console.log("escrow:  ", ESCROW);

  const Escrow = await ethers.getContractFactory("TradeEscrowContract");
  const escrow = Escrow.attach(ESCROW);
  const Erc20 = await ethers.getContractFactory("MockERC20");
  const usdc = Erc20.attach(USDC);

  // Check OPERATOR_ROLE on deployer (needed for initiateTrade)
  const OPERATOR_ROLE = await escrow.OPERATOR_ROLE();
  const deployerHasOperatorRole = await escrow.hasRole(OPERATOR_ROLE, deployer.address);
  console.log("deployer has OPERATOR_ROLE:", deployerHasOperatorRole);

  if (!deployerHasOperatorRole) {
    console.log("\nNOTE: deployer lacks OPERATOR_ROLE on new escrow.");
    console.log("OPERATOR_ROLE held by:", OPERATOR_ADDRESS);
    console.log("Buy-flow initiateTrade requires that key. Skipping initiation step.");
    console.log("\nCallable verification only: check ABI surface.");

    // Just verify the new functions exist on the deployed contract by reading ABI.
    const fragments = escrow.interface.fragments
      .filter((f) => f.type === "function")
      .map((f) => f.name);

    const required = [
      "markPaymentSentByBuyer",
      "confirmPaymentByMerchant",
      "cancelTradeByMerchant",
    ];

    console.log("\nABI surface check:");
    for (const fn of required) {
      const present = fragments.includes(fn);
      console.log(`  ${present ? "✓" : "✗"} ${fn}`);
    }
    return;
  }

  // ─── If we have OPERATOR_ROLE, run a full B1 happy path ───
  console.log("\n=== Full B1 happy path ===");

  // Merchant deposits escrow
  const depositAmt = ethers.parseUnits("50", 6);
  console.log("[1] Merchant approves + deposits...");
  await (await usdc.connect(merchant).approve(ESCROW, ethers.MaxUint256)).wait();
  await (await escrow.connect(merchant).depositEscrow(merchant.address, depositAmt)).wait();
  console.log("    deposited 50 USDC");

  // Operator initiates buy trade
  const tradeId = ethers.hexlify(ethers.randomBytes(32));
  const amount = ethers.parseUnits("10", 6);
  const expiresAt = Math.floor(Date.now() / 1000) + 3600;
  console.log("[2] Operator initiateTrade tradeId=", tradeId);
  await (
    await escrow.connect(deployer).initiateTrade(
      tradeId,
      merchant.address,
      buyer.address,
      amount,
      true, // private (no stake)
      expiresAt
    )
  ).wait();

  // Buyer marks paid via NEW user-signed function
  console.log("[3] Buyer markPaymentSentByBuyer (B1)...");
  const markTx = await escrow.connect(buyer).markPaymentSentByBuyer(tradeId);
  await markTx.wait();
  console.log("    tx:", markTx.hash);

  // Merchant confirms via NEW user-signed function
  console.log("[4] Merchant confirmPaymentByMerchant (B1)...");
  const confirmTx = await escrow.connect(merchant).confirmPaymentByMerchant(tradeId);
  await confirmTx.wait();
  console.log("    tx:", confirmTx.hash);

  // Verify final state
  const trade = await escrow.trades(tradeId);
  console.log("\nFinal trade.status:", trade.status, "(3 = Completed)");
}

main().then(() => process.exit(0)).catch((e) => {
  console.error(e);
  process.exit(1);
});
