const { expect } = require("chai");
const { ethers } = require("hardhat");
const {
  loadFixture,
  time,
} = require("@nomicfoundation/hardhat-toolbox/network-helpers");

describe("TradeEscrowContract — Sell Flow", function () {
  // ─── Fixtures ───

  async function deployFixture() {
    const [deployer, admin, operator, merchant, seller, feeWallet, outsider] =
      await ethers.getSigners();

    const usdc = await ethers.deployContract("MockERC20", ["USD Coin", "USDC", 6]);
    await usdc.waitForDeployment();

    const nft = await ethers.deployContract("SoulboundTradeNFT");
    await nft.waitForDeployment();

    const escrow = await ethers.deployContract("TradeEscrowContract", [
      await usdc.getAddress(),
      feeWallet.address,
      await nft.getAddress(),
      admin.address,
      operator.address,
    ]);
    await escrow.waitForDeployment();

    const MINTER_ROLE = await nft.MINTER_ROLE();
    await nft.grantRole(MINTER_ROLE, await escrow.getAddress());

    // Mint USDC to seller + merchant for testing
    const mintAmount = ethers.parseUnits("10000", 6);
    await usdc.mint(seller.address, mintAmount);
    await usdc.mint(merchant.address, mintAmount);

    // Approve escrow to spend USDC
    await usdc.connect(seller).approve(await escrow.getAddress(), ethers.MaxUint256);
    await usdc.connect(merchant).approve(await escrow.getAddress(), ethers.MaxUint256);

    const tradeId = (id) => ethers.keccak256(ethers.toUtf8Bytes(`sell-trade-${id}`));

    const FUTURE = async (secondsAhead = 86400) => {
      const block = await ethers.provider.getBlock("latest");
      return block.timestamp + secondsAhead;
    };

    return {
      usdc, nft, escrow,
      deployer, admin, operator, merchant, seller, feeWallet, outsider,
      tradeId, FUTURE,
    };
  }

  async function fundedSellTradeFixture() {
    const f = await loadFixture(deployFixture);
    const { escrow, seller, merchant, tradeId, FUTURE } = f;
    const amount = ethers.parseUnits("100", 6);
    const id = tradeId(1);
    const expiresAt = await FUTURE();

    await escrow.connect(seller).openSellTrade(id, merchant.address, amount, expiresAt, true, false, "");
    return { ...f, amount, id, expiresAt };
  }

  async function joinedSellTradeFixture() {
    const f = await loadFixture(fundedSellTradeFixture);
    await f.escrow.connect(f.merchant).joinSellTrade(f.id);
    return f;
  }

  async function paymentSentFixture() {
    const f = await loadFixture(joinedSellTradeFixture);
    await f.escrow.connect(f.merchant).markSellPaymentSent(f.id);
    return f;
  }

  // ─── Open / Fund (T01-T08) ───

  describe("openSellTrade", function () {
    it("T01: locks amount + fee + stake from seller wallet", async function () {
      const { escrow, usdc, seller, merchant, tradeId, FUTURE } = await loadFixture(deployFixture);
      const amount = ethers.parseUnits("100", 6);
      const fee = (amount * 20n) / 10000n;
      const stake = ethers.parseUnits("5", 6);
      const id = tradeId(1);

      const balBefore = await usdc.balanceOf(seller.address);
      await escrow.connect(seller).openSellTrade(id, merchant.address, amount, await FUTURE(), true, false, "");
      const balAfter = await usdc.balanceOf(seller.address);

      expect(balBefore - balAfter).to.equal(amount + fee + stake);
      expect(await usdc.balanceOf(await escrow.getAddress())).to.equal(amount + fee + stake);
    });

    it("T02: reverts when amount < MIN_TRADE_AMOUNT", async function () {
      const { escrow, seller, merchant, tradeId, FUTURE } = await loadFixture(deployFixture);
      const tooSmall = ethers.parseUnits("5", 6);
      await expect(
        escrow.connect(seller).openSellTrade(tradeId(1), merchant.address, tooSmall, await FUTURE(), true, false, "")
      ).to.be.revertedWith("Below minimum trade amount");
    });

    it("T03: reverts when expiresAt in past", async function () {
      const { escrow, seller, merchant, tradeId } = await loadFixture(deployFixture);
      const past = Math.floor(Date.now() / 1000) - 100;
      await expect(
        escrow.connect(seller).openSellTrade(tradeId(1), merchant.address, ethers.parseUnits("100", 6), past, true, false, "")
      ).to.be.revertedWith("Expiry must be in future");
    });

    it("T04: reverts when merchant == seller", async function () {
      const { escrow, seller, tradeId, FUTURE } = await loadFixture(deployFixture);
      await expect(
        escrow.connect(seller).openSellTrade(tradeId(1), seller.address, ethers.parseUnits("100", 6), await FUTURE(), true, false, "")
      ).to.be.revertedWith("Cannot trade with self");
    });

    it("T05: reverts when tradeId already exists", async function () {
      const { escrow, seller, merchant, id, FUTURE } = await loadFixture(fundedSellTradeFixture);
      await expect(
        escrow.connect(seller).openSellTrade(id, merchant.address, ethers.parseUnits("100", 6), await FUTURE(), true, false, "")
      ).to.be.revertedWith("Trade already exists");
    });

    it("T06: requireStake=false → no stake locked", async function () {
      const { escrow, usdc, seller, merchant, tradeId, FUTURE } = await loadFixture(deployFixture);
      const amount = ethers.parseUnits("100", 6);
      const fee = (amount * 20n) / 10000n;
      const id = tradeId(1);

      const balBefore = await usdc.balanceOf(seller.address);
      await escrow.connect(seller).openSellTrade(id, merchant.address, amount, await FUTURE(), false, false, "");
      const balAfter = await usdc.balanceOf(seller.address);

      expect(balBefore - balAfter).to.equal(amount + fee);
      const trade = await escrow.trades(id);
      expect(trade.stakeAmount).to.equal(0n);
    });

    it("T07: isCashTrade=true mints NFT to seller", async function () {
      const { escrow, nft, seller, merchant, tradeId, FUTURE } = await loadFixture(deployFixture);
      const amount = ethers.parseUnits("100", 6);
      const id = tradeId(1);

      await escrow.connect(seller).openSellTrade(id, merchant.address, amount, await FUTURE(), true, true, "Brooklyn Cafe");
      const tokenId = await nft.tradeIdToTokenId(id);
      expect(tokenId).to.be.gt(0);
      expect(await nft.ownerOf(tokenId)).to.equal(seller.address);
    });

    it("T08: emits SellTradeOpened with correct params", async function () {
      const { escrow, seller, merchant, tradeId, FUTURE } = await loadFixture(deployFixture);
      const amount = ethers.parseUnits("100", 6);
      const id = tradeId(1);
      await expect(escrow.connect(seller).openSellTrade(id, merchant.address, amount, await FUTURE(), true, false, ""))
        .to.emit(escrow, "SellTradeOpened")
        .withArgs(id, seller.address, merchant.address, amount);
    });
  });

  // ─── Join (T09-T12) ───

  describe("joinSellTrade", function () {
    it("T09: succeeds when called by trade.merchant", async function () {
      const { escrow, merchant, id } = await loadFixture(fundedSellTradeFixture);
      await escrow.connect(merchant).joinSellTrade(id);
      const trade = await escrow.trades(id);
      expect(trade.status).to.equal(1); // EscrowLocked
    });

    it("T10: reverts when called by seller, operator, admin, random", async function () {
      const { escrow, seller, operator, admin, outsider, id } = await loadFixture(fundedSellTradeFixture);
      await expect(escrow.connect(seller).joinSellTrade(id)).to.be.revertedWith("Only target merchant");
      await expect(escrow.connect(operator).joinSellTrade(id)).to.be.revertedWith("Only target merchant");
      await expect(escrow.connect(admin).joinSellTrade(id)).to.be.revertedWith("Only target merchant");
      await expect(escrow.connect(outsider).joinSellTrade(id)).to.be.revertedWith("Only target merchant");
    });

    it("T11: reverts after expiresAt", async function () {
      const { escrow, merchant, id, expiresAt } = await loadFixture(fundedSellTradeFixture);
      await time.increaseTo(expiresAt + 1);
      await expect(escrow.connect(merchant).joinSellTrade(id)).to.be.revertedWith("Expired");
    });

    it("T12: reverts when trade not in Pending status", async function () {
      const { escrow, merchant, id } = await loadFixture(joinedSellTradeFixture);
      await expect(escrow.connect(merchant).joinSellTrade(id)).to.be.revertedWith("Not joinable");
    });
  });

  // ─── Mark paid (T13-T15) ───

  describe("markSellPaymentSent", function () {
    it("T13: succeeds when called by trade.merchant", async function () {
      const { escrow, merchant, id } = await loadFixture(joinedSellTradeFixture);
      await escrow.connect(merchant).markSellPaymentSent(id);
      const trade = await escrow.trades(id);
      expect(trade.status).to.equal(2); // PaymentSent
    });

    it("T14: reverts when called by seller, operator, admin, random", async function () {
      const { escrow, seller, operator, admin, outsider, id } = await loadFixture(joinedSellTradeFixture);
      await expect(escrow.connect(seller).markSellPaymentSent(id)).to.be.revertedWith("Only merchant (buyer of USDC)");
      await expect(escrow.connect(operator).markSellPaymentSent(id)).to.be.revertedWith("Only merchant (buyer of USDC)");
      await expect(escrow.connect(admin).markSellPaymentSent(id)).to.be.revertedWith("Only merchant (buyer of USDC)");
      await expect(escrow.connect(outsider).markSellPaymentSent(id)).to.be.revertedWith("Only merchant (buyer of USDC)");
    });

    it("T15: reverts when trade not in EscrowLocked status", async function () {
      const { escrow, merchant, id } = await loadFixture(fundedSellTradeFixture);
      await expect(escrow.connect(merchant).markSellPaymentSent(id)).to.be.revertedWith("Bad status");
    });
  });

  // ─── Release (T16-T20 — CRITICAL) ───

  describe("releaseSellEscrow", function () {
    it("T16: succeeds when called by seller, status=PaymentSent", async function () {
      const { escrow, seller, id } = await loadFixture(paymentSentFixture);
      await escrow.connect(seller).releaseSellEscrow(id);
      const trade = await escrow.trades(id);
      expect(trade.status).to.equal(3); // Completed
    });

    it("T17: succeeds when called by seller, status=EscrowLocked (skip-paid path)", async function () {
      const { escrow, seller, id } = await loadFixture(joinedSellTradeFixture);
      await escrow.connect(seller).releaseSellEscrow(id);
      const trade = await escrow.trades(id);
      expect(trade.status).to.equal(3);
    });

    it("T18: reverts when called by merchant, operator, admin, random", async function () {
      const { escrow, merchant, operator, admin, outsider, id } = await loadFixture(paymentSentFixture);
      await expect(escrow.connect(merchant).releaseSellEscrow(id)).to.be.revertedWith("Only seller");
      await expect(escrow.connect(operator).releaseSellEscrow(id)).to.be.revertedWith("Only seller");
      await expect(escrow.connect(admin).releaseSellEscrow(id)).to.be.revertedWith("Only seller");
      await expect(escrow.connect(outsider).releaseSellEscrow(id)).to.be.revertedWith("Only seller");
    });

    it("T19: transfers amount-fee to merchant; fee to feeWallet; stake returned to seller", async function () {
      const { escrow, usdc, seller, merchant, feeWallet, id, amount } = await loadFixture(paymentSentFixture);
      const fee = (amount * 20n) / 10000n;
      const stake = ethers.parseUnits("5", 6);
      const merchantBalBefore = await usdc.balanceOf(merchant.address);
      const feeBalBefore = await usdc.balanceOf(feeWallet.address);
      const sellerBalBefore = await usdc.balanceOf(seller.address);

      await escrow.connect(seller).releaseSellEscrow(id);

      expect((await usdc.balanceOf(merchant.address)) - merchantBalBefore).to.equal(amount - fee);
      expect((await usdc.balanceOf(feeWallet.address)) - feeBalBefore).to.equal(fee);
      expect((await usdc.balanceOf(seller.address)) - sellerBalBefore).to.equal(stake);
    });

    it("T20: emits SellEscrowReleased + TradeCompleted; burns NFT if cash", async function () {
      const { escrow, nft, seller, merchant, tradeId, FUTURE } = await loadFixture(deployFixture);
      const amount = ethers.parseUnits("100", 6);
      const id = tradeId(1);
      await escrow.connect(seller).openSellTrade(id, merchant.address, amount, await FUTURE(), true, true, "Cafe");
      await escrow.connect(merchant).joinSellTrade(id);
      await escrow.connect(merchant).markSellPaymentSent(id);

      const tokenId = await nft.tradeIdToTokenId(id);
      expect(tokenId).to.be.gt(0);

      await expect(escrow.connect(seller).releaseSellEscrow(id))
        .to.emit(escrow, "SellEscrowReleased")
        .and.to.emit(escrow, "TradeCompleted");

      await expect(nft.ownerOf(tokenId)).to.be.reverted;
    });
  });

  // ─── Dispute (T21-T26) ───

  describe("openSellDispute + resolveSellDispute", function () {
    it("T21: openSellDispute callable by seller (direct), valid status EscrowLocked", async function () {
      const { escrow, seller, id } = await loadFixture(joinedSellTradeFixture);
      await escrow.connect(seller).openSellDispute(id);
      const trade = await escrow.trades(id);
      expect(trade.status).to.equal(4); // Disputed
    });

    it("T22: openSellDispute callable by merchant (direct), valid status PaymentSent", async function () {
      const { escrow, merchant, id } = await loadFixture(paymentSentFixture);
      await escrow.connect(merchant).openSellDispute(id);
      const trade = await escrow.trades(id);
      expect(trade.status).to.equal(4);
    });

    it("T23: reverts during Pending (no counterparty)", async function () {
      const { escrow, seller, id } = await loadFixture(fundedSellTradeFixture);
      await expect(escrow.connect(seller).openSellDispute(id)).to.be.revertedWith("Dispute requires merchant joined");
    });

    it("T24: reverts for random caller", async function () {
      const { escrow, outsider, id } = await loadFixture(joinedSellTradeFixture);
      await expect(escrow.connect(outsider).openSellDispute(id)).to.be.revertedWith("Not party");
    });

    it("T25: resolveSellDispute only ADMIN_ROLE; merchant wins → amount-fee", async function () {
      const { escrow, usdc, admin, operator, merchant, feeWallet, seller, id, amount } = await loadFixture(paymentSentFixture);
      await escrow.connect(seller).openSellDispute(id);
      const fee = (amount * 20n) / 10000n;
      const stake = ethers.parseUnits("5", 6);

      await expect(escrow.connect(operator).resolveSellDispute(id, merchant.address)).to.be.reverted;

      const merchBefore = await usdc.balanceOf(merchant.address);
      const feeBefore = await usdc.balanceOf(feeWallet.address);
      const sellerBefore = await usdc.balanceOf(seller.address);

      await escrow.connect(admin).resolveSellDispute(id, merchant.address);

      expect((await usdc.balanceOf(merchant.address)) - merchBefore).to.equal(amount - fee);
      expect((await usdc.balanceOf(feeWallet.address)) - feeBefore).to.equal(fee);
      expect((await usdc.balanceOf(seller.address)) - sellerBefore).to.equal(stake);
    });

    it("T26: seller wins → seller gets amount-fee, fee to feeWallet, stake to seller; NFT burned if cash", async function () {
      const { escrow, usdc, nft, admin, seller, merchant, feeWallet, tradeId, FUTURE } = await loadFixture(deployFixture);
      const amount = ethers.parseUnits("100", 6);
      const id = tradeId(7);
      await escrow.connect(seller).openSellTrade(id, merchant.address, amount, await FUTURE(), true, true, "Cafe");
      await escrow.connect(merchant).joinSellTrade(id);
      await escrow.connect(merchant).openSellDispute(id);

      const tokenId = await nft.tradeIdToTokenId(id);
      const fee = (amount * 20n) / 10000n;
      const stake = ethers.parseUnits("5", 6);
      const sellerBefore = await usdc.balanceOf(seller.address);
      const feeBefore = await usdc.balanceOf(feeWallet.address);

      await escrow.connect(admin).resolveSellDispute(id, seller.address);

      // Seller gets (amount - fee) + stake = (100 - 0.2) + 5
      expect((await usdc.balanceOf(seller.address)) - sellerBefore).to.equal((amount - fee) + stake);
      expect((await usdc.balanceOf(feeWallet.address)) - feeBefore).to.equal(fee);
      await expect(nft.ownerOf(tokenId)).to.be.reverted;
    });
  });

  // ─── Cancel (T27-T29) ───

  describe("cancelSellTradePending + cancelExpiredSellTrade", function () {
    it("T27: cancelSellTradePending only seller, only Pending; refunds amount+fee+stake; burns NFT", async function () {
      const { escrow, usdc, nft, seller, merchant, tradeId, FUTURE } = await loadFixture(deployFixture);
      const amount = ethers.parseUnits("100", 6);
      const fee = (amount * 20n) / 10000n;
      const stake = ethers.parseUnits("5", 6);
      const id = tradeId(9);
      await escrow.connect(seller).openSellTrade(id, merchant.address, amount, await FUTURE(), true, true, "Cafe");
      const tokenId = await nft.tradeIdToTokenId(id);

      const balBefore = await usdc.balanceOf(seller.address);
      await escrow.connect(seller).cancelSellTradePending(id);
      const balAfter = await usdc.balanceOf(seller.address);

      expect(balAfter - balBefore).to.equal(amount + fee + stake);
      await expect(nft.ownerOf(tokenId)).to.be.reverted;
    });

    it("T28: cancelSellTradePending reverts after merchant joined", async function () {
      const { escrow, seller, id } = await loadFixture(joinedSellTradeFixture);
      await expect(escrow.connect(seller).cancelSellTradePending(id)).to.be.revertedWith("Already joined");
    });

    it("T29: cancelExpiredSellTrade permissionless after expiry; reverts before expiry", async function () {
      const { escrow, usdc, seller, outsider, id, expiresAt, amount } = await loadFixture(fundedSellTradeFixture);
      await expect(escrow.connect(outsider).cancelExpiredSellTrade(id)).to.be.revertedWith("Not expired");

      await time.increaseTo(expiresAt + 1);
      const fee = (amount * 20n) / 10000n;
      const stake = ethers.parseUnits("5", 6);
      const balBefore = await usdc.balanceOf(seller.address);
      await escrow.connect(outsider).cancelExpiredSellTrade(id);
      const balAfter = await usdc.balanceOf(seller.address);

      expect(balAfter - balBefore).to.equal(amount + fee + stake);
    });
  });

  // ─── Operator boundaries (T30-T32) ───

  describe("Operator boundary guards", function () {
    it("T30: cancelTrade(sellTradeId) reverts (Operator cannot touch sell)", async function () {
      const { escrow, operator, id } = await loadFixture(joinedSellTradeFixture);
      await expect(escrow.connect(operator).cancelTrade(id)).to.be.revertedWith("Operator cannot touch sell");
    });

    it("T31: confirmPayment(sellTradeId) reverts (Operator cannot release sell)", async function () {
      const { escrow, operator, id } = await loadFixture(paymentSentFixture);
      await expect(escrow.connect(operator).confirmPayment(id)).to.be.revertedWith("Operator cannot release sell");
    });

    it("T32: after Released, openSellDispute reverts; second releaseSellEscrow reverts", async function () {
      const { escrow, seller, id } = await loadFixture(paymentSentFixture);
      await escrow.connect(seller).releaseSellEscrow(id);

      await expect(escrow.connect(seller).openSellDispute(id)).to.be.revertedWith("Dispute requires merchant joined");
      await expect(escrow.connect(seller).releaseSellEscrow(id)).to.be.revertedWith("Bad status");
    });
  });

  // ─── E2E (T33-T35) ───

  describe("E2E flows", function () {
    it("T33: online happy path balances correct", async function () {
      const { escrow, usdc, seller, merchant, feeWallet, tradeId, FUTURE } = await loadFixture(deployFixture);
      const amount = ethers.parseUnits("250", 6);
      const fee = (amount * 20n) / 10000n;
      const stake = ethers.parseUnits("5", 6);
      const id = tradeId(33);

      const sellerStart = await usdc.balanceOf(seller.address);
      const merchantStart = await usdc.balanceOf(merchant.address);
      const feeStart = await usdc.balanceOf(feeWallet.address);

      await escrow.connect(seller).openSellTrade(id, merchant.address, amount, await FUTURE(), true, false, "");
      await escrow.connect(merchant).joinSellTrade(id);
      await escrow.connect(merchant).markSellPaymentSent(id);
      await escrow.connect(seller).releaseSellEscrow(id);

      const sellerEnd = await usdc.balanceOf(seller.address);
      const merchantEnd = await usdc.balanceOf(merchant.address);
      const feeEnd = await usdc.balanceOf(feeWallet.address);

      // Seller out: amount + fee, in: stake (returned). Net out = amount + fee - stake = wait no, net out = amount + fee
      expect(sellerStart - sellerEnd).to.equal(amount + fee);
      expect(merchantEnd - merchantStart).to.equal(amount - fee);
      expect(feeEnd - feeStart).to.equal(fee);
    });

    it("T34: cash happy path; NFT burned at release", async function () {
      const { escrow, nft, seller, merchant, tradeId, FUTURE } = await loadFixture(deployFixture);
      const amount = ethers.parseUnits("100", 6);
      const id = tradeId(34);
      await escrow.connect(seller).openSellTrade(id, merchant.address, amount, await FUTURE(), true, true, "Brooklyn");
      const tokenId = await nft.tradeIdToTokenId(id);
      expect(await nft.ownerOf(tokenId)).to.equal(seller.address);

      await escrow.connect(merchant).joinSellTrade(id);
      await escrow.connect(merchant).markSellPaymentSent(id);
      await escrow.connect(seller).releaseSellEscrow(id);

      await expect(nft.ownerOf(tokenId)).to.be.reverted;
    });

    it("T35: dispute path: open → join → markPaid → openDispute(merchant) → resolve(buyer)", async function () {
      const { escrow, usdc, admin, seller, merchant, feeWallet, tradeId, FUTURE } = await loadFixture(deployFixture);
      const amount = ethers.parseUnits("100", 6);
      const fee = (amount * 20n) / 10000n;
      const stake = ethers.parseUnits("5", 6);
      const id = tradeId(35);

      await escrow.connect(seller).openSellTrade(id, merchant.address, amount, await FUTURE(), true, false, "");
      await escrow.connect(merchant).joinSellTrade(id);
      await escrow.connect(merchant).markSellPaymentSent(id);
      await escrow.connect(merchant).openSellDispute(id);

      const merchBefore = await usdc.balanceOf(merchant.address);
      const feeBefore = await usdc.balanceOf(feeWallet.address);
      const sellerBefore = await usdc.balanceOf(seller.address);

      await escrow.connect(admin).resolveSellDispute(id, merchant.address);

      // Merchant wins as buyer of USDC
      expect((await usdc.balanceOf(merchant.address)) - merchBefore).to.equal(amount - fee);
      expect((await usdc.balanceOf(feeWallet.address)) - feeBefore).to.equal(fee);
      // Seller stake returned
      expect((await usdc.balanceOf(seller.address)) - sellerBefore).to.equal(stake);
    });
  });
});
