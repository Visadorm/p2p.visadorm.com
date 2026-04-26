const { ethers } = require("hardhat");

async function main() {
  const USDC = process.env.USDC_ADDRESS || "0xc4d1c4B5778f61d8DdAB492FEF745FB5133FEC53";
  const TO = process.env.TO || "0x392d5b11Ca3d89769e76924409364bA0CE302B9a";
  const AMOUNT = ethers.parseUnits(process.env.AMOUNT || "10000", 6);

  const [signer] = await ethers.getSigners();
  console.log(`Signer: ${signer.address}`);

  const usdc = await ethers.getContractAt("MockERC20", USDC);
  const tx = await usdc.mint(TO, AMOUNT);
  console.log(`Tx: ${tx.hash}`);
  await tx.wait();

  const bal = await usdc.balanceOf(TO);
  console.log(`Balance of ${TO}: ${ethers.formatUnits(bal, 6)} USDC`);
}

main().then(() => process.exit(0)).catch((e) => { console.error(e); process.exit(1); });
