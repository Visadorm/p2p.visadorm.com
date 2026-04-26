const { ethers } = require("hardhat");
const fs = require("fs");
const path = require("path");

const USDC_ADDRESS = process.env.USDC_ADDRESS || "0xe3B1038eecea95053256D0e5d52D11A0703D1c4F";
const ETH_PER_WALLET = process.env.ETH_PER_WALLET || "0.005";
const USDC_SELLER = process.env.USDC_SELLER || "200";
const USDC_BUYER = process.env.USDC_BUYER || "20";

async function main() {
  const [funder] = await ethers.getSigners();
  const network = await ethers.provider.getNetwork();

  console.log("=".repeat(60));
  console.log("Sell-flow test wallet provisioner");
  console.log("=".repeat(60));
  console.log(`Network:        ${network.name} (chainId ${network.chainId})`);
  console.log(`Funder:         ${funder.address}`);
  console.log(`Funder ETH:     ${ethers.formatEther(await ethers.provider.getBalance(funder.address))}`);
  console.log(`USDC contract:  ${USDC_ADDRESS}`);
  console.log("=".repeat(60));

  const seller = ethers.Wallet.createRandom().connect(ethers.provider);
  const buyer = ethers.Wallet.createRandom().connect(ethers.provider);

  console.log("\nGenerated wallets:");
  console.log(`  Seller: ${seller.address}`);
  console.log(`  Buyer:  ${buyer.address}`);

  console.log("\nFunding ETH...");
  const ethAmount = ethers.parseEther(ETH_PER_WALLET);

  const ethTx1 = await funder.sendTransaction({ to: seller.address, value: ethAmount });
  await ethTx1.wait();
  console.log(`  Seller +${ETH_PER_WALLET} ETH — tx ${ethTx1.hash}`);

  const ethTx2 = await funder.sendTransaction({ to: buyer.address, value: ethAmount });
  await ethTx2.wait();
  console.log(`  Buyer  +${ETH_PER_WALLET} ETH — tx ${ethTx2.hash}`);

  console.log("\nMinting USDC...");
  const usdc = await ethers.getContractAt("MockERC20", USDC_ADDRESS);

  const sellerUsdc = ethers.parseUnits(USDC_SELLER, 6);
  const buyerUsdc = ethers.parseUnits(USDC_BUYER, 6);

  const usdcTx1 = await usdc.mint(seller.address, sellerUsdc);
  await usdcTx1.wait();
  console.log(`  Seller +${USDC_SELLER} USDC — tx ${usdcTx1.hash}`);

  const usdcTx2 = await usdc.mint(buyer.address, buyerUsdc);
  await usdcTx2.wait();
  console.log(`  Buyer  +${USDC_BUYER} USDC — tx ${usdcTx2.hash}`);

  const out = {
    network: network.name,
    chainId: Number(network.chainId),
    usdc: USDC_ADDRESS,
    created_at: new Date().toISOString(),
    seller: {
      address: seller.address,
      private_key: seller.privateKey,
      mnemonic: seller.mnemonic ? seller.mnemonic.phrase : null,
      eth_funded: ETH_PER_WALLET,
      usdc_funded: USDC_SELLER,
    },
    buyer: {
      address: buyer.address,
      private_key: buyer.privateKey,
      mnemonic: buyer.mnemonic ? buyer.mnemonic.phrase : null,
      eth_funded: ETH_PER_WALLET,
      usdc_funded: USDC_BUYER,
    },
  };

  const outDir = path.join(__dirname, "..", "test-wallets");
  if (!fs.existsSync(outDir)) {
    fs.mkdirSync(outDir, { recursive: true });
  }
  const ts = new Date().toISOString().replace(/[:.]/g, "-");
  const filename = `${network.name}-${ts}.json`;
  const filepath = path.join(outDir, filename);
  fs.writeFileSync(filepath, JSON.stringify(out, null, 2), { mode: 0o600 });

  console.log("\n" + "=".repeat(60));
  console.log("DONE");
  console.log("=".repeat(60));
  console.log(`Seeds + keys saved (chmod 600): ${filepath}`);
  console.log("\nIMPORTANT:");
  console.log("  - File contains private keys + mnemonics — never commit.");
  console.log("  - Add 'contracts/test-wallets/' to .gitignore if not already.");
  console.log("  - Import seller mnemonic in MetaMask window 1.");
  console.log("  - Import buyer  mnemonic in MetaMask window 2 (different profile).");
  console.log("  - Set MetaMask network to Base Sepolia (chainId 84532).");
}

main()
  .then(() => process.exit(0))
  .catch((err) => {
    console.error(err);
    process.exit(1);
  });
