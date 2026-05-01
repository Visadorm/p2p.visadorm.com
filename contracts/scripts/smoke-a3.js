const { ethers } = require("hardhat");
const fs = require("fs");

const ESCROW = "0x780675087C6B3c8262BaCCC6653AA28deea7E4c7";
const USDC = "0x7c33814E64FaC03Fd45C3B11C94a4BFa7cb6E1d1";

const wallets = JSON.parse(
  fs.readFileSync(__dirname + "/../test-wallets/baseSepolia-2026-04-25T21-32-42-787Z.json")
);

async function main() {
  const provider = ethers.provider;
  const seller = new ethers.Wallet(wallets.seller.private_key, provider);
  const merchant = new ethers.Wallet(wallets.buyer.private_key, provider);

  console.log("seller:  ", seller.address);
  console.log("merchant:", merchant.address);

  const Escrow = await ethers.getContractFactory("TradeEscrowContract");
  const escrow = Escrow.attach(ESCROW);
  const Erc20 = await ethers.getContractFactory("MockERC20");
  const usdc = Erc20.attach(USDC);

  const fragments = escrow.interface.fragments
    .filter((f) => f.type === "function")
    .map((f) => f.name);
  console.log("\ncancelSellTradeByBuyer present:", fragments.includes("cancelSellTradeByBuyer"));

  const allowance = await usdc.allowance(seller.address, ESCROW);
  if (allowance < ethers.parseUnits("100", 6)) {
    console.log("\napprove max...");
    await (await usdc.connect(seller).approve(ESCROW, ethers.MaxUint256)).wait();
  }

  const id = ethers.hexlify(ethers.randomBytes(32));
  const amount = ethers.parseUnits("10", 6);
  const expiresAt = Math.floor(Date.now() / 1000) + 3600;

  console.log("\n[1] seller openSellTrade...");
  await (await escrow.connect(seller).openSellTrade(id, merchant.address, amount, expiresAt, true, false, "", { gasLimit: 600000 })).wait();

  console.log("[2] merchant joinSellTrade...");
  await (await escrow.connect(merchant).joinSellTrade(id, { gasLimit: 200000 })).wait();

  const sellerBalBefore = await usdc.balanceOf(seller.address);
  console.log("[3] merchant cancelSellTradeByBuyer (A3 strict)...");
  const tx = await escrow.connect(merchant).cancelSellTradeByBuyer(id, { gasLimit: 300000 });
  await tx.wait();
  console.log("    tx:", tx.hash);

  const sellerBalAfter = await usdc.balanceOf(seller.address);
  const refund = sellerBalAfter - sellerBalBefore;
  console.log("    seller refund:", ethers.formatUnits(refund, 6), "USDC");

  const trade = await escrow.trades(id);
  console.log("    final status:", trade.status, "(5 = Cancelled)");
}

main().then(() => process.exit(0)).catch((e) => { console.error(e); process.exit(1); });
