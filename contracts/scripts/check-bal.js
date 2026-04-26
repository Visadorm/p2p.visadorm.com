const { ethers } = require("hardhat");

async function main() {
  const USDC = "0xe3B1038eecea95053256D0e5d52D11A0703D1c4F";
  const TO = "0x392d5b11Ca3d89769e76924409364bA0CE302B9a";
  const usdc = await ethers.getContractAt("MockERC20", USDC);
  console.log(`Balance: ${ethers.formatUnits(await usdc.balanceOf(TO), 6)} USDC`);
  console.log(`Supply:  ${ethers.formatUnits(await usdc.totalSupply(), 6)} USDC`);
}

main().then(() => process.exit(0)).catch((e) => { console.error(e); process.exit(1); });
