const { ethers } = require("hardhat");

async function main() {
  const TO = process.env.TO || "0x6051De6c953AebF5c7d158d466efb7F0C39A0576";
  const AMOUNT = process.env.AMOUNT_ETH || "0.003";

  const [signer] = await ethers.getSigners();
  console.log("Signer:", signer.address);
  console.log(`Sending ${AMOUNT} ETH to ${TO}`);

  const tx = await signer.sendTransaction({
    to: TO,
    value: ethers.parseEther(AMOUNT),
  });
  console.log("Tx:", tx.hash);
  await tx.wait();

  const bal = await ethers.provider.getBalance(TO);
  console.log("Balance:", ethers.formatEther(bal), "ETH");
}

main().then(() => process.exit(0)).catch((e) => {
  console.error(e);
  process.exit(1);
});
