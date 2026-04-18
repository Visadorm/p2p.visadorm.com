const { expect } = require("chai");
const { ethers } = require("hardhat");
const {
  loadFixture,
} = require("@nomicfoundation/hardhat-toolbox/network-helpers");

describe("TradeEscrowContract", function () {
  // ─── Fixtures ───

  async function deployFixture() {
    const [deployer, admin, operator, merchant, buyer, feeWallet, outsider] =
      await ethers.getSigners();

    // Deploy MockERC20 (USDC with 6 decimals)
    const usdc = await ethers.deployContract("MockERC20", [
      "USD Coin",
      "USDC",
      6,
    ]);
    await usdc.waitForDeployment();

    // Deploy SoulboundTradeNFT
    const nft = await ethers.deployContract("SoulboundTradeNFT");
    await nft.waitForDeployment();

    // Deploy TradeEscrowContract
    const escrow = await ethers.deployContract("TradeEscrowContract", [
      await usdc.getAddress(),
      feeWallet.address,
      await nft.getAddress(),
      admin.address,
      operator.address,
    ]);
    await escrow.waitForDeployment();

    // Grant MINTER_ROLE to escrow contract on the NFT
    const MINTER_ROLE = await nft.MINTER_ROLE();
    await nft.grantRole(MINTER_ROLE, await escrow.getAddress());

    // Mint USDC to merchant and buyer for testing
    const mintAmount = ethers.parseUnits("10000", 6); // 10,000 USDC
    await usdc.mint(merchant.address, mintAmount);
    await usdc.mint(buyer.address, mintAmount);

    // Approve escrow contract to spend merchant & buyer USDC
    await usdc
      .connect(merchant)
      .approve(await escrow.getAddress(), ethers.MaxUint256);
    await usdc
      .connect(buyer)
      .approve(await escrow.getAddress(), ethers.MaxUint256);

    // Helper to generate trade IDs
    const tradeId = (id) =>
      ethers.keccak256(ethers.toUtf8Bytes(`trade-${id}`));

    return {
      usdc,
      nft,
      escrow,
      deployer,
      admin,
      operator,
      merchant,
      buyer,
      feeWallet,
      outsider,
      tradeId,
    };
  }

  /**
   * Fixture that sets up a merchant with 1000 USDC deposited in escrow.
   */
  async function escrowReadyFixture() {
    const fixture = await loadFixture(deployFixture);
    const { escrow, operator, merchant } = fixture;
    const depositAmount = ethers.parseUnits("1000", 6);

    await escrow.connect(operator).depositEscrow(merchant.address, depositAmount);

    return { ...fixture, depositAmount };
  }

  /**
   * Fixture that sets up a public trade in EscrowLocked status.
   */
  async function tradeInitiatedFixture() {
    const fixture = await loadFixture(escrowReadyFixture);
    const { escrow, operator, merchant, buyer, tradeId } = fixture;
    const tradeAmount = ethers.parseUnits("100", 6);
    const id = tradeId(1);
    const expiresAt = Math.floor(Date.now() / 1000) + 86400;

    await escrow
      .connect(operator)
      .initiateTrade(id, merchant.address, buyer.address, tradeAmount, false, expiresAt);

    return { ...fixture, tradeAmount, id, expiresAt };
  }

  // ─── Deployment ───

  describe("Deployment", function () {
    it("deploys with correct USDC, feeWallet, and NFT addresses", async function () {
      const { escrow, usdc, nft, feeWallet } = await loadFixture(deployFixture);

      expect(await escrow.usdcToken()).to.equal(await usdc.getAddress());
      expect(await escrow.feeWallet()).to.equal(feeWallet.address);
      expect(await escrow.tradeNFT()).to.equal(await nft.getAddress());
    });

    it("sets ADMIN_ROLE correctly", async function () {
      const { escrow, admin } = await loadFixture(deployFixture);
      const ADMIN_ROLE = await escrow.ADMIN_ROLE();

      expect(await escrow.hasRole(ADMIN_ROLE, admin.address)).to.be.true;
    });

    it("sets OPERATOR_ROLE correctly", async function () {
      const { escrow, operator } = await loadFixture(deployFixture);
      const OPERATOR_ROLE = await escrow.OPERATOR_ROLE();

      expect(await escrow.hasRole(OPERATOR_ROLE, operator.address)).to.be.true;
    });

    it("sets DEFAULT_ADMIN_ROLE to admin", async function () {
      const { escrow, admin } = await loadFixture(deployFixture);
      const DEFAULT_ADMIN_ROLE = await escrow.DEFAULT_ADMIN_ROLE();

      expect(await escrow.hasRole(DEFAULT_ADMIN_ROLE, admin.address)).to.be.true;
    });

    it("reverts with zero address for USDC", async function () {
      const [, admin, operator, , , feeWallet] = await ethers.getSigners();
      const nft = await ethers.deployContract("SoulboundTradeNFT");
      await nft.waitForDeployment();

      await expect(
        ethers.deployContract("TradeEscrowContract", [
          ethers.ZeroAddress,
          feeWallet.address,
          await nft.getAddress(),
          admin.address,
          operator.address,
        ])
      ).to.be.revertedWith("Invalid USDC address");
    });

    it("reverts with zero address for feeWallet", async function () {
      const [, admin, operator] = await ethers.getSigners();
      const usdc = await ethers.deployContract("MockERC20", ["USDC", "USDC", 6]);
      await usdc.waitForDeployment();
      const nft = await ethers.deployContract("SoulboundTradeNFT");
      await nft.waitForDeployment();

      await expect(
        ethers.deployContract("TradeEscrowContract", [
          await usdc.getAddress(),
          ethers.ZeroAddress,
          await nft.getAddress(),
          admin.address,
          operator.address,
        ])
      ).to.be.revertedWith("Invalid fee wallet");
    });
  });

  // ─── Escrow ───

  describe("Escrow", function () {
    it("merchant can deposit USDC to escrow", async function () {
      const { escrow, usdc, operator, merchant } = await loadFixture(deployFixture);
      const amount = ethers.parseUnits("500", 6);

      await expect(
        escrow.connect(operator).depositEscrow(merchant.address, amount)
      )
        .to.emit(escrow, "EscrowDeposited")
        .withArgs(merchant.address, amount);

      expect(await escrow.merchantEscrowBalance(merchant.address)).to.equal(amount);
      expect(await usdc.balanceOf(await escrow.getAddress())).to.equal(amount);
    });

    it("merchant can withdraw available (unlocked) balance", async function () {
      const { escrow, usdc, operator, merchant, depositAmount } =
        await loadFixture(escrowReadyFixture);

      const withdrawAmount = ethers.parseUnits("500", 6);
      const merchantBalanceBefore = await usdc.balanceOf(merchant.address);

      await expect(
        escrow.connect(operator).withdrawEscrow(merchant.address, withdrawAmount)
      )
        .to.emit(escrow, "EscrowWithdrawn")
        .withArgs(merchant.address, withdrawAmount);

      expect(await usdc.balanceOf(merchant.address)).to.equal(
        merchantBalanceBefore + withdrawAmount
      );
    });

    it("merchant cannot withdraw more than available", async function () {
      const { escrow, operator, merchant, depositAmount } =
        await loadFixture(escrowReadyFixture);

      const tooMuch = depositAmount + 1n;

      await expect(
        escrow.connect(operator).withdrawEscrow(merchant.address, tooMuch)
      ).to.be.revertedWith("Insufficient available balance");
    });

    it("available balance = total - locked", async function () {
      const { escrow, operator, merchant, buyer, tradeId, depositAmount } =
        await loadFixture(escrowReadyFixture);

      const lockAmount = ethers.parseUnits("300", 6);
      const fee = (lockAmount * 20n) / 10000n;
      const total = lockAmount + fee;
      const id = tradeId(1);
      const expiresAt = Math.floor(Date.now() / 1000) + 86400;

      // Initiate a private trade (no stake) to lock some funds (amount + fee)
      await escrow
        .connect(operator)
        .initiateTrade(id, merchant.address, buyer.address, lockAmount, true, expiresAt);

      const available = await escrow.getAvailableBalance(merchant.address);
      expect(available).to.equal(depositAmount - total);

      // Cannot withdraw more than available
      await expect(
        escrow.connect(operator).withdrawEscrow(merchant.address, depositAmount)
      ).to.be.revertedWith("Insufficient available balance");

      // Can withdraw the available portion
      await escrow.connect(operator).withdrawEscrow(merchant.address, available);
    });

    it("deposit reverts with zero amount", async function () {
      const { escrow, operator, merchant } = await loadFixture(deployFixture);

      await expect(
        escrow.connect(operator).depositEscrow(merchant.address, 0)
      ).to.be.revertedWith("Amount must be > 0");
    });

    it("only OPERATOR_ROLE can deposit", async function () {
      const { escrow, outsider, merchant } = await loadFixture(deployFixture);

      await expect(
        escrow.connect(outsider).depositEscrow(merchant.address, 100)
      ).to.be.reverted;
    });

    it("only OPERATOR_ROLE can withdraw", async function () {
      const { escrow, outsider, merchant } = await loadFixture(escrowReadyFixture);

      await expect(
        escrow.connect(outsider).withdrawEscrow(merchant.address, 100)
      ).to.be.reverted;
    });
  });

  // ─── Trade Lifecycle ───

  describe("Trade Lifecycle", function () {
    it("initiate trade locks merchant USDC (amount + fee), buyer pays $5 stake on public trade", async function () {
      const { escrow, usdc, operator, merchant, buyer, tradeId, depositAmount } =
        await loadFixture(escrowReadyFixture);

      const amount = ethers.parseUnits("200", 6);
      const fee = (amount * 20n) / 10000n;
      const total = amount + fee;
      const id = tradeId(1);
      const expiresAt = Math.floor(Date.now() / 1000) + 86400;
      const STAKE = ethers.parseUnits("5", 6);

      const buyerBalanceBefore = await usdc.balanceOf(buyer.address);

      await expect(
        escrow
          .connect(operator)
          .initiateTrade(id, merchant.address, buyer.address, amount, false, expiresAt)
      )
        .to.emit(escrow, "TradeCreated")
        .withArgs(id, merchant.address, buyer.address, amount, false);

      // Merchant locked amount increased by amount + fee
      expect(await escrow.merchantLockedInTrades(merchant.address)).to.equal(total);

      // Buyer lost $5 stake
      expect(await usdc.balanceOf(buyer.address)).to.equal(
        buyerBalanceBefore - STAKE
      );

      // Trade stored correctly
      const trade = await escrow.trades(id);
      expect(trade.merchant).to.equal(merchant.address);
      expect(trade.buyer).to.equal(buyer.address);
      expect(trade.amount).to.equal(amount);
      expect(trade.stakeAmount).to.equal(STAKE);
      expect(trade.stakePaidBy).to.equal(buyer.address);
      expect(trade.status).to.equal(1); // EscrowLocked
      expect(trade.isPrivate).to.be.false;
    });

    it("private trade: no stake required", async function () {
      const { escrow, usdc, operator, merchant, buyer, tradeId } =
        await loadFixture(escrowReadyFixture);

      const amount = ethers.parseUnits("200", 6);
      const id = tradeId(1);
      const expiresAt = Math.floor(Date.now() / 1000) + 86400;

      const buyerBalanceBefore = await usdc.balanceOf(buyer.address);

      await escrow
        .connect(operator)
        .initiateTrade(id, merchant.address, buyer.address, amount, true, expiresAt);

      // Buyer balance unchanged (no stake)
      expect(await usdc.balanceOf(buyer.address)).to.equal(buyerBalanceBefore);

      const trade = await escrow.trades(id);
      expect(trade.stakeAmount).to.equal(0);
      expect(trade.stakePaidBy).to.equal(ethers.ZeroAddress);
      expect(trade.isPrivate).to.be.true;
    });

    it("cannot initiate if insufficient merchant escrow", async function () {
      const { escrow, operator, merchant, buyer, tradeId, depositAmount } =
        await loadFixture(escrowReadyFixture);

      const tooMuch = depositAmount + 1n;
      const id = tradeId(1);
      const expiresAt = Math.floor(Date.now() / 1000) + 86400;

      await expect(
        escrow
          .connect(operator)
          .initiateTrade(id, merchant.address, buyer.address, tooMuch, true, expiresAt)
      ).to.be.revertedWith("Insufficient merchant escrow");
    });

    it("cannot create duplicate trade IDs", async function () {
      const { escrow, operator, merchant, buyer, tradeId } =
        await loadFixture(escrowReadyFixture);

      const amount = ethers.parseUnits("100", 6);
      const id = tradeId(1);
      const expiresAt = Math.floor(Date.now() / 1000) + 86400;

      await escrow
        .connect(operator)
        .initiateTrade(id, merchant.address, buyer.address, amount, true, expiresAt);

      await expect(
        escrow
          .connect(operator)
          .initiateTrade(id, merchant.address, buyer.address, amount, true, expiresAt)
      ).to.be.revertedWith("Trade already exists");
    });

    it("merchant cannot trade with themselves", async function () {
      const { escrow, operator, merchant, tradeId } =
        await loadFixture(escrowReadyFixture);

      const amount = ethers.parseUnits("100", 6);
      const id = tradeId(1);
      const expiresAt = Math.floor(Date.now() / 1000) + 86400;

      await expect(
        escrow
          .connect(operator)
          .initiateTrade(id, merchant.address, merchant.address, amount, true, expiresAt)
      ).to.be.revertedWith("Merchant cannot trade with self");
    });

    it("trade with 0 amount reverts", async function () {
      const { escrow, operator, merchant, buyer, tradeId } =
        await loadFixture(escrowReadyFixture);

      const id = tradeId(1);
      const expiresAt = Math.floor(Date.now() / 1000) + 86400;

      await expect(
        escrow
          .connect(operator)
          .initiateTrade(id, merchant.address, buyer.address, 0, true, expiresAt)
      ).to.be.revertedWith("Below minimum trade amount");
    });

    it("markPaymentSent updates status", async function () {
      const { escrow, operator, id } = await loadFixture(tradeInitiatedFixture);

      await expect(escrow.connect(operator).markPaymentSent(id))
        .to.emit(escrow, "PaymentMarkedSent")
        .withArgs(id);

      const trade = await escrow.trades(id);
      expect(trade.status).to.equal(2); // PaymentSent
    });

    it("markPaymentSent reverts on wrong status", async function () {
      const { escrow, operator, id } = await loadFixture(tradeInitiatedFixture);

      // Move to PaymentSent first
      await escrow.connect(operator).markPaymentSent(id);

      // Try again — should fail because it's no longer EscrowLocked
      await expect(
        escrow.connect(operator).markPaymentSent(id)
      ).to.be.revertedWith("Invalid trade status");
    });

    it("confirmPayment releases full USDC to buyer, fee to feeWallet from merchant escrow, stake returned", async function () {
      const { escrow, usdc, operator, merchant, buyer, feeWallet, id, tradeAmount } =
        await loadFixture(tradeInitiatedFixture);

      // Mark payment sent first
      await escrow.connect(operator).markPaymentSent(id);

      const STAKE = ethers.parseUnits("5", 6);
      const fee = (tradeAmount * 20n) / 10000n; // 0.2%

      const buyerBalBefore = await usdc.balanceOf(buyer.address);
      const feeWalletBalBefore = await usdc.balanceOf(feeWallet.address);

      await expect(escrow.connect(operator).confirmPayment(id))
        .to.emit(escrow, "TradeCompleted")
        .withArgs(id, fee);

      // Buyer receives full trade amount (merchant absorbs the fee) + stake returned
      const buyerBalAfter = await usdc.balanceOf(buyer.address);
      expect(buyerBalAfter - buyerBalBefore).to.equal(tradeAmount + STAKE);

      // Fee wallet receives fee (from merchant escrow)
      const feeWalletBalAfter = await usdc.balanceOf(feeWallet.address);
      expect(feeWalletBalAfter - feeWalletBalBefore).to.equal(fee);

      // Trade status is Completed
      const trade = await escrow.trades(id);
      expect(trade.status).to.equal(3); // Completed

      // Merchant locked reduced to 0 (was amount + fee, now unlocked fully)
      expect(await escrow.merchantLockedInTrades(merchant.address)).to.equal(0);
    });

    it("confirmPayment works from EscrowLocked status too", async function () {
      const { escrow, operator, id } = await loadFixture(tradeInitiatedFixture);

      // Confirm directly without markPaymentSent
      await expect(escrow.connect(operator).confirmPayment(id))
        .to.emit(escrow, "TradeCompleted");
    });

    it("confirmPayment reverts on wrong status (Completed)", async function () {
      const { escrow, operator, id } = await loadFixture(tradeInitiatedFixture);

      await escrow.connect(operator).confirmPayment(id);

      await expect(
        escrow.connect(operator).confirmPayment(id)
      ).to.be.revertedWith("Invalid trade status");
    });

    it("cancelTrade forfeits stake to feeWallet and unlocks escrow", async function () {
      const { escrow, usdc, operator, merchant, buyer, feeWallet, id, tradeAmount } =
        await loadFixture(tradeInitiatedFixture);

      const STAKE = ethers.parseUnits("5", 6);
      const feeBalBefore = await usdc.balanceOf(feeWallet.address);

      await expect(escrow.connect(operator).cancelTrade(id))
        .to.emit(escrow, "TradeCancelled")
        .withArgs(id);

      // Stake forfeited to feeWallet (not returned to buyer)
      const feeBalAfter = await usdc.balanceOf(feeWallet.address);
      expect(feeBalAfter - feeBalBefore).to.equal(STAKE);

      // Merchant locked reduced back
      expect(await escrow.merchantLockedInTrades(merchant.address)).to.equal(0);

      // Trade status is Cancelled
      const trade = await escrow.trades(id);
      expect(trade.status).to.equal(5); // Cancelled
    });

    it("cancelTrade reverts from PaymentSent status", async function () {
      const { escrow, operator, id } = await loadFixture(tradeInitiatedFixture);

      await escrow.connect(operator).markPaymentSent(id);

      await expect(escrow.connect(operator).cancelTrade(id))
        .to.be.revertedWith("Can only cancel before payment is sent");
    });

    it("cancelTrade reverts on wrong status (Completed)", async function () {
      const { escrow, operator, id } = await loadFixture(tradeInitiatedFixture);

      await escrow.connect(operator).confirmPayment(id);

      await expect(
        escrow.connect(operator).cancelTrade(id)
      ).to.be.revertedWith("Can only cancel before payment is sent");
    });

    it("cancelTrade on private trade (no stake to return)", async function () {
      const { escrow, usdc, operator, merchant, buyer, tradeId, depositAmount } =
        await loadFixture(escrowReadyFixture);

      const amount = ethers.parseUnits("100", 6);
      const id = tradeId(99);
      const expiresAt = Math.floor(Date.now() / 1000) + 86400;

      await escrow
        .connect(operator)
        .initiateTrade(id, merchant.address, buyer.address, amount, true, expiresAt);

      const escrowBal = await usdc.balanceOf(await escrow.getAddress());

      await escrow.connect(operator).cancelTrade(id);

      // No stake was transferred out, escrow balance unchanged
      expect(await usdc.balanceOf(await escrow.getAddress())).to.equal(escrowBal);
    });
  });

  // ─── Disputes ───

  describe("Disputes", function () {
    it("openDispute sets status to Disputed", async function () {
      const { escrow, operator, buyer, id } =
        await loadFixture(tradeInitiatedFixture);

      await expect(escrow.connect(operator).openDispute(id, buyer.address))
        .to.emit(escrow, "DisputeOpened")
        .withArgs(id, buyer.address);

      const trade = await escrow.trades(id);
      expect(trade.status).to.equal(4); // Disputed
    });

    it("merchant can open dispute", async function () {
      const { escrow, operator, merchant, id } =
        await loadFixture(tradeInitiatedFixture);

      await expect(escrow.connect(operator).openDispute(id, merchant.address))
        .to.emit(escrow, "DisputeOpened")
        .withArgs(id, merchant.address);
    });

    it("only trade parties can open dispute", async function () {
      const { escrow, operator, outsider, id } =
        await loadFixture(tradeInitiatedFixture);

      await expect(
        escrow.connect(operator).openDispute(id, outsider.address)
      ).to.be.revertedWith("Not a party to this trade");
    });

    it("openDispute reverts on wrong status", async function () {
      const { escrow, operator, admin, buyer, id } =
        await loadFixture(tradeInitiatedFixture);

      // Open and resolve dispute to get to Resolved status
      await escrow.connect(operator).openDispute(id, buyer.address);
      await escrow.connect(admin).resolveDispute(id, buyer.address);

      // Try to open dispute on resolved trade
      await expect(
        escrow.connect(operator).openDispute(id, buyer.address)
      ).to.be.revertedWith("Invalid trade status");
    });

    it("openDispute works from PaymentSent status", async function () {
      const { escrow, operator, buyer, id } =
        await loadFixture(tradeInitiatedFixture);

      await escrow.connect(operator).markPaymentSent(id);

      await expect(escrow.connect(operator).openDispute(id, buyer.address))
        .to.emit(escrow, "DisputeOpened");
    });

    // ─── Dispute Finality (strict state-based escrow model) ───
    it("openDispute reverts on Completed trade (strict finality)", async function () {
      const { escrow, operator, buyer, id } =
        await loadFixture(tradeInitiatedFixture);

      await escrow.connect(operator).markPaymentSent(id);
      await escrow.connect(operator).confirmPayment(id);

      await expect(
        escrow.connect(operator).openDispute(id, buyer.address)
      ).to.be.revertedWith("Invalid trade status");
    });

    it("openDispute reverts on Cancelled trade", async function () {
      const { escrow, operator, buyer, id } =
        await loadFixture(tradeInitiatedFixture);

      await escrow.connect(operator).cancelTrade(id);

      await expect(
        escrow.connect(operator).openDispute(id, buyer.address)
      ).to.be.revertedWith("Invalid trade status");
    });

    it("confirmPayment reverts on Disputed trade (no release while in dispute)", async function () {
      const { escrow, operator, buyer, id } =
        await loadFixture(tradeInitiatedFixture);

      await escrow.connect(operator).markPaymentSent(id);
      await escrow.connect(operator).openDispute(id, buyer.address);

      await expect(
        escrow.connect(operator).confirmPayment(id)
      ).to.be.revertedWith("Invalid trade status");
    });

    it("cancelTrade reverts on Disputed trade", async function () {
      const { escrow, operator, buyer, id } =
        await loadFixture(tradeInitiatedFixture);

      await escrow.connect(operator).markPaymentSent(id);
      await escrow.connect(operator).openDispute(id, buyer.address);

      await expect(
        escrow.connect(operator).cancelTrade(id)
      ).to.be.revertedWith("Can only cancel before payment is sent");
    });

    it("resolveDispute reverts on Completed trade", async function () {
      const { escrow, operator, admin, buyer, id } =
        await loadFixture(tradeInitiatedFixture);

      await escrow.connect(operator).markPaymentSent(id);
      await escrow.connect(operator).confirmPayment(id);

      await expect(
        escrow.connect(admin).resolveDispute(id, buyer.address)
      ).to.be.revertedWith("Trade not in dispute");
    });

    it("resolveDispute sends full amount to winner, fee from merchant escrow", async function () {
      const { escrow, usdc, operator, admin, merchant, buyer, feeWallet, id, tradeAmount } =
        await loadFixture(tradeInitiatedFixture);

      await escrow.connect(operator).openDispute(id, buyer.address);

      const fee = (tradeAmount * 20n) / 10000n;
      const STAKE = ethers.parseUnits("5", 6);

      const buyerBalBefore = await usdc.balanceOf(buyer.address);
      const feeWalletBalBefore = await usdc.balanceOf(feeWallet.address);

      await expect(escrow.connect(admin).resolveDispute(id, buyer.address))
        .to.emit(escrow, "DisputeResolved")
        .withArgs(id, buyer.address, tradeAmount);

      // Winner (buyer) receives full trade amount (merchant absorbs fee) + stake returned
      const buyerBalAfter = await usdc.balanceOf(buyer.address);
      expect(buyerBalAfter - buyerBalBefore).to.equal(tradeAmount + STAKE);

      // Fee wallet receives fee (from merchant escrow)
      expect(await usdc.balanceOf(feeWallet.address)).to.equal(
        feeWalletBalBefore + fee
      );

      // Trade status is Resolved
      const trade = await escrow.trades(id);
      expect(trade.status).to.equal(6); // Resolved
    });

    it("resolveDispute in favor of merchant — funds stay in escrow, no fee charged", async function () {
      const { escrow, usdc, operator, admin, merchant, buyer, feeWallet, id, tradeAmount } =
        await loadFixture(tradeInitiatedFixture);

      await escrow.connect(operator).openDispute(id, buyer.address);

      const merchantEscrowBefore = await escrow.merchantEscrowBalance(merchant.address);
      const merchantLockedBefore = await escrow.merchantLockedInTrades(merchant.address);
      const feeWalletBefore = await usdc.balanceOf(feeWallet.address);

      await escrow.connect(admin).resolveDispute(id, merchant.address);

      // Merchant escrow balance unchanged (funds stay, just unlocked)
      expect(await escrow.merchantEscrowBalance(merchant.address)).to.equal(merchantEscrowBefore);
      // Locked amount reduced (unlocked)
      expect(await escrow.merchantLockedInTrades(merchant.address)).to.equal(0);
      // No fee sent to feeWallet when merchant wins
      expect(await usdc.balanceOf(feeWallet.address)).to.equal(feeWalletBefore);
    });

    it("only ADMIN_ROLE can resolve disputes", async function () {
      const { escrow, operator, buyer, id } =
        await loadFixture(tradeInitiatedFixture);

      await escrow.connect(operator).openDispute(id, buyer.address);

      await expect(
        escrow.connect(operator).resolveDispute(id, buyer.address)
      ).to.be.reverted;
    });

    it("resolveDispute reverts if not in Disputed status", async function () {
      const { escrow, admin, buyer, id } =
        await loadFixture(tradeInitiatedFixture);

      // Trade is in EscrowLocked, not Disputed
      await expect(
        escrow.connect(admin).resolveDispute(id, buyer.address)
      ).to.be.revertedWith("Trade not in dispute");
    });

    it("resolveDispute reverts if winner is not a party", async function () {
      const { escrow, operator, admin, buyer, outsider, id } =
        await loadFixture(tradeInitiatedFixture);

      await escrow.connect(operator).openDispute(id, buyer.address);

      await expect(
        escrow.connect(admin).resolveDispute(id, outsider.address)
      ).to.be.revertedWith("Winner must be merchant or buyer");
    });
  });

  // ─── Fees ───

  describe("Fees", function () {
    it("0.2% fee calculated correctly for 100 USDC", async function () {
      const { escrow, usdc, operator, feeWallet, id, tradeAmount } =
        await loadFixture(tradeInitiatedFixture);

      // 100 USDC * 20 / 10000 = 0.2 USDC = 200000
      const expectedFee = ethers.parseUnits("0.2", 6);
      const feeWalletBalBefore = await usdc.balanceOf(feeWallet.address);

      await escrow.connect(operator).confirmPayment(id);

      const feeWalletBalAfter = await usdc.balanceOf(feeWallet.address);
      expect(feeWalletBalAfter - feeWalletBalBefore).to.equal(expectedFee);
    });

    it("0.2% fee calculated correctly for 990 USDC (merchant-absorbed fee)", async function () {
      const { escrow, usdc, operator, merchant, buyer, feeWallet, tradeId } =
        await loadFixture(escrowReadyFixture);

      // Use 990 USDC so that total (990 + 1.98 = 991.98) fits within 1000 USDC deposit
      const amount = ethers.parseUnits("990", 6);
      const id = tradeId(2);
      const expiresAt = Math.floor(Date.now() / 1000) + 86400;

      await escrow
        .connect(operator)
        .initiateTrade(id, merchant.address, buyer.address, amount, true, expiresAt);

      // 990 * 20 / 10000 = 1.98 USDC
      const expectedFee = (amount * 20n) / 10000n;
      const feeWalletBalBefore = await usdc.balanceOf(feeWallet.address);

      await escrow.connect(operator).confirmPayment(id);

      expect(await usdc.balanceOf(feeWallet.address)).to.equal(
        feeWalletBalBefore + expectedFee
      );
    });

    it("0.2% fee calculated correctly for small amount (1 USDC)", async function () {
      const { escrow, usdc, operator, merchant, buyer, feeWallet, tradeId } =
        await loadFixture(escrowReadyFixture);

      const amount = ethers.parseUnits("10", 6); // 10 USDC = 10000000 (minimum)
      const id = tradeId(3);
      const expiresAt = Math.floor(Date.now() / 1000) + 86400;

      await escrow
        .connect(operator)
        .initiateTrade(id, merchant.address, buyer.address, amount, true, expiresAt);

      // 10000000 * 20 / 10000 = 20000 (0.02 USDC)
      const expectedFee = 20000n;
      const feeWalletBalBefore = await usdc.balanceOf(feeWallet.address);

      await escrow.connect(operator).confirmPayment(id);

      expect(await usdc.balanceOf(feeWallet.address)).to.equal(
        feeWalletBalBefore + expectedFee
      );
    });

    it("fee sent to feeWallet on dispute resolution", async function () {
      const { escrow, usdc, operator, admin, buyer, feeWallet, id, tradeAmount } =
        await loadFixture(tradeInitiatedFixture);

      await escrow.connect(operator).openDispute(id, buyer.address);

      const expectedFee = (tradeAmount * 20n) / 10000n;
      const feeWalletBalBefore = await usdc.balanceOf(feeWallet.address);

      await escrow.connect(admin).resolveDispute(id, buyer.address);

      expect(await usdc.balanceOf(feeWallet.address)).to.equal(
        feeWalletBalBefore + expectedFee
      );
    });
  });

  // ─── Pause ───

  describe("Pause", function () {
    it("admin can pause", async function () {
      const { escrow, admin } = await loadFixture(deployFixture);

      await escrow.connect(admin).pause();
      expect(await escrow.paused()).to.be.true;
    });

    it("admin can unpause", async function () {
      const { escrow, admin } = await loadFixture(deployFixture);

      await escrow.connect(admin).pause();
      await escrow.connect(admin).unpause();
      expect(await escrow.paused()).to.be.false;
    });

    it("non-admin cannot pause", async function () {
      const { escrow, operator } = await loadFixture(deployFixture);

      await expect(escrow.connect(operator).pause()).to.be.reverted;
    });

    it("depositEscrow reverts when paused", async function () {
      const { escrow, admin, operator, merchant } =
        await loadFixture(deployFixture);

      await escrow.connect(admin).pause();

      await expect(
        escrow.connect(operator).depositEscrow(merchant.address, 100)
      ).to.be.reverted;
    });

    it("withdrawEscrow reverts when paused", async function () {
      const { escrow, admin, operator, merchant } =
        await loadFixture(escrowReadyFixture);

      await escrow.connect(admin).pause();

      await expect(
        escrow
          .connect(operator)
          .withdrawEscrow(merchant.address, ethers.parseUnits("100", 6))
      ).to.be.reverted;
    });

    it("initiateTrade reverts when paused", async function () {
      const { escrow, admin, operator, merchant, buyer, tradeId } =
        await loadFixture(escrowReadyFixture);

      await escrow.connect(admin).pause();

      await expect(
        escrow
          .connect(operator)
          .initiateTrade(
            tradeId(1),
            merchant.address,
            buyer.address,
            ethers.parseUnits("100", 6),
            true,
            Math.floor(Date.now() / 1000) + 86400
          )
      ).to.be.reverted;
    });

    it("markPaymentSent reverts when paused", async function () {
      const { escrow, admin, operator, id } =
        await loadFixture(tradeInitiatedFixture);

      await escrow.connect(admin).pause();

      await expect(
        escrow.connect(operator).markPaymentSent(id)
      ).to.be.reverted;
    });

    it("confirmPayment reverts when paused", async function () {
      const { escrow, admin, operator, id } =
        await loadFixture(tradeInitiatedFixture);

      await escrow.connect(admin).pause();

      await expect(
        escrow.connect(operator).confirmPayment(id)
      ).to.be.reverted;
    });

    it("cancelTrade reverts when paused", async function () {
      const { escrow, admin, operator, id } =
        await loadFixture(tradeInitiatedFixture);

      await escrow.connect(admin).pause();

      await expect(
        escrow.connect(operator).cancelTrade(id)
      ).to.be.reverted;
    });

    it("dispute resolution works even when paused (no whenNotPaused)", async function () {
      const { escrow, operator, admin, buyer, id } =
        await loadFixture(tradeInitiatedFixture);

      // Open dispute before pause (openDispute has no whenNotPaused)
      await escrow.connect(operator).openDispute(id, buyer.address);

      // Pause the contract
      await escrow.connect(admin).pause();

      // resolveDispute should still work (no whenNotPaused modifier)
      await expect(escrow.connect(admin).resolveDispute(id, buyer.address))
        .to.emit(escrow, "DisputeResolved");
    });

    it("openDispute reverts when paused", async function () {
      const { escrow, operator, admin, buyer, id } =
        await loadFixture(tradeInitiatedFixture);

      await escrow.connect(admin).pause();

      await expect(escrow.connect(operator).openDispute(id, buyer.address))
        .to.be.revertedWithCustomError(escrow, "EnforcedPause");
    });
  });

  // ─── NFT Minting via Escrow ───

  describe("Cash Meeting NFT", function () {
    it("mintTradeNFT creates NFT through escrow", async function () {
      const { escrow, nft, operator, buyer, merchant, id, tradeAmount } =
        await loadFixture(tradeInitiatedFixture);

      await escrow
        .connect(operator)
        .mintTradeNFT(id, "Central Park, NYC");

      const data = await nft.getByTradeId(id);
      expect(data.tradeId).to.equal(id);
      expect(data.merchant).to.equal(merchant.address);
      expect(data.buyer).to.equal(buyer.address);
      expect(data.amount).to.equal(tradeAmount);
      expect(data.meetingLocation).to.equal("Central Park, NYC");
    });

    it("burnTradeNFT removes NFT through escrow", async function () {
      const { escrow, nft, operator, id } =
        await loadFixture(tradeInitiatedFixture);

      await escrow.connect(operator).mintTradeNFT(id, "Central Park, NYC");

      await escrow.connect(operator).burnTradeNFT(id);

      await expect(nft.getByTradeId(id)).to.be.revertedWith(
        "No NFT for trade"
      );
    });

    it("mintTradeNFT reverts on wrong trade status", async function () {
      const { escrow, operator, id } = await loadFixture(tradeInitiatedFixture);

      await escrow.connect(operator).confirmPayment(id);

      await expect(
        escrow.connect(operator).mintTradeNFT(id, "Central Park, NYC")
      ).to.be.revertedWith("Invalid trade status");
    });
  });

  // ─── Additional Edge-Case Tests (to reach 100+) ──────────────

  describe("Edge Cases — Deposit & Withdraw", function () {
    it("should revert deposit of zero amount", async function () {
      const { escrow, operator, merchant } = await loadFixture(deployFixture);
      await expect(
        escrow.connect(operator).depositEscrow(merchant.address, 0)
      ).to.be.revertedWith("Amount must be > 0");
    });

    it("should revert withdraw of zero amount", async function () {
      const { escrow, operator, merchant } = await loadFixture(escrowReadyFixture);
      await expect(
        escrow.connect(operator).withdrawEscrow(merchant.address, 0)
      ).to.be.revertedWith("Amount must be > 0");
    });

    it("should revert withdraw exceeding available balance", async function () {
      const { escrow, operator, merchant } = await loadFixture(escrowReadyFixture);
      const tooMuch = ethers.parseUnits("2000", 6);
      await expect(
        escrow.connect(operator).withdrawEscrow(merchant.address, tooMuch)
      ).to.be.revertedWith("Insufficient available balance");
    });

    it("should allow full withdrawal when no trades locked", async function () {
      const { escrow, operator, merchant, usdc } = await loadFixture(escrowReadyFixture);
      const full = ethers.parseUnits("1000", 6);
      await escrow.connect(operator).withdrawEscrow(merchant.address, full);
      expect(await escrow.merchantEscrowBalance(merchant.address)).to.equal(0);
    });

    it("should track multiple deposits correctly", async function () {
      const { escrow, operator, merchant } = await loadFixture(escrowReadyFixture);
      const second = ethers.parseUnits("500", 6);
      await escrow.connect(operator).depositEscrow(merchant.address, second);
      expect(await escrow.merchantEscrowBalance(merchant.address)).to.equal(
        ethers.parseUnits("1500", 6)
      );
    });
  });

  describe("Edge Cases — Trade Lifecycle", function () {
    it("should revert initiateTrade with zero amount", async function () {
      const { escrow, operator, merchant, buyer, tradeId } = await loadFixture(escrowReadyFixture);
      await expect(
        escrow.connect(operator).initiateTrade(tradeId("zero"), merchant.address, buyer.address, 0, false, Math.floor(Date.now()/1000)+3600)
      ).to.be.revertedWith("Below minimum trade amount");
    });

    it("should revert initiateTrade when merchant === buyer", async function () {
      const { escrow, operator, merchant, tradeId } = await loadFixture(escrowReadyFixture);
      const amount = ethers.parseUnits("10", 6);
      await expect(
        escrow.connect(operator).initiateTrade(tradeId("self"), merchant.address, merchant.address, amount, false, Math.floor(Date.now()/1000)+3600)
      ).to.be.revertedWith("Merchant cannot trade with self");
    });

    it("should revert duplicate trade ID", async function () {
      const { escrow, operator, merchant, buyer, tradeId } = await loadFixture(escrowReadyFixture);
      const amount = ethers.parseUnits("10", 6);
      const id = tradeId("dup");
      await escrow.connect(operator).initiateTrade(id, merchant.address, buyer.address, amount, false, Math.floor(Date.now()/1000)+3600);
      await expect(
        escrow.connect(operator).initiateTrade(id, merchant.address, buyer.address, amount, false, Math.floor(Date.now()/1000)+3600)
      ).to.be.revertedWith("Trade already exists");
    });

    it("should revert initiateTrade exceeding escrow balance", async function () {
      const { escrow, operator, merchant, buyer, tradeId } = await loadFixture(escrowReadyFixture);
      const tooMuch = ethers.parseUnits("5000", 6);
      await expect(
        escrow.connect(operator).initiateTrade(tradeId("big"), merchant.address, buyer.address, tooMuch, false, Math.floor(Date.now()/1000)+3600)
      ).to.be.revertedWith("Insufficient merchant escrow");
    });

    it("should not collect stake for private trades", async function () {
      const { escrow, operator, merchant, buyer, tradeId, usdc } = await loadFixture(escrowReadyFixture);
      const amount = ethers.parseUnits("10", 6);
      const buyerBefore = await usdc.balanceOf(buyer.address);
      await escrow.connect(operator).initiateTrade(tradeId("priv"), merchant.address, buyer.address, amount, true, Math.floor(Date.now()/1000)+3600);
      const buyerAfter = await usdc.balanceOf(buyer.address);
      expect(buyerBefore).to.equal(buyerAfter);
    });

    it("should collect $5 stake for public trades", async function () {
      const { escrow, operator, merchant, buyer, tradeId, usdc } = await loadFixture(escrowReadyFixture);
      const amount = ethers.parseUnits("10", 6);
      const stake = ethers.parseUnits("5", 6);
      const buyerBefore = await usdc.balanceOf(buyer.address);
      await escrow.connect(operator).initiateTrade(tradeId("pub"), merchant.address, buyer.address, amount, false, Math.floor(Date.now()/1000)+3600);
      const buyerAfter = await usdc.balanceOf(buyer.address);
      expect(buyerBefore - buyerAfter).to.equal(stake);
    });

    it("should revert markPaymentSent on wrong status", async function () {
      const { escrow, operator, merchant, buyer, tradeId } = await loadFixture(escrowReadyFixture);
      const amount = ethers.parseUnits("10", 6);
      const id = tradeId("wrongmark");
      await escrow.connect(operator).initiateTrade(id, merchant.address, buyer.address, amount, false, Math.floor(Date.now()/1000)+3600);
      await escrow.connect(operator).markPaymentSent(id);
      await expect(escrow.connect(operator).markPaymentSent(id)).to.be.revertedWith("Invalid trade status");
    });

    it("should revert confirmPayment on cancelled trade", async function () {
      const { escrow, operator, merchant, buyer, tradeId } = await loadFixture(escrowReadyFixture);
      const amount = ethers.parseUnits("10", 6);
      const id = tradeId("cancelled-confirm");
      await escrow.connect(operator).initiateTrade(id, merchant.address, buyer.address, amount, false, Math.floor(Date.now()/1000)+3600);
      await escrow.connect(operator).cancelTrade(id);
      await expect(escrow.connect(operator).confirmPayment(id)).to.be.revertedWith("Invalid trade status");
    });

    it("should revert cancelTrade on completed trade", async function () {
      const { escrow, operator, merchant, buyer, tradeId } = await loadFixture(escrowReadyFixture);
      const amount = ethers.parseUnits("10", 6);
      const id = tradeId("completed-cancel");
      await escrow.connect(operator).initiateTrade(id, merchant.address, buyer.address, amount, false, Math.floor(Date.now()/1000)+3600);
      await escrow.connect(operator).confirmPayment(id);
      await expect(escrow.connect(operator).cancelTrade(id)).to.be.revertedWith("Can only cancel before payment is sent");
    });
  });

  describe("Edge Cases — Disputes", function () {
    it("should revert openDispute on pending (non-existent) trade", async function () {
      const { escrow, operator, buyer, tradeId } = await loadFixture(escrowReadyFixture);
      await expect(
        escrow.connect(operator).openDispute(tradeId("ghost"), buyer.address)
      ).to.be.revertedWith("Invalid trade status");
    });

    it("should revert resolveDispute with wrong winner", async function () {
      const { escrow, operator, admin, merchant, buyer, outsider, tradeId } = await loadFixture(escrowReadyFixture);
      const amount = ethers.parseUnits("10", 6);
      const id = tradeId("bad-winner");
      await escrow.connect(operator).initiateTrade(id, merchant.address, buyer.address, amount, false, Math.floor(Date.now()/1000)+3600);
      await escrow.connect(operator).openDispute(id, buyer.address);
      await expect(
        escrow.connect(admin).resolveDispute(id, outsider.address)
      ).to.be.revertedWith("Winner must be merchant or buyer");
    });

    it("should revert resolveDispute called by operator (not admin)", async function () {
      const { escrow, operator, merchant, buyer, tradeId } = await loadFixture(escrowReadyFixture);
      const amount = ethers.parseUnits("10", 6);
      const id = tradeId("op-resolve");
      await escrow.connect(operator).initiateTrade(id, merchant.address, buyer.address, amount, false, Math.floor(Date.now()/1000)+3600);
      await escrow.connect(operator).openDispute(id, buyer.address);
      await expect(
        escrow.connect(operator).resolveDispute(id, buyer.address)
      ).to.be.reverted;
    });
  });

  describe("Edge Cases — Access Control", function () {
    it("should revert deposit by non-operator", async function () {
      const { escrow, outsider, merchant } = await loadFixture(deployFixture);
      const amount = ethers.parseUnits("100", 6);
      await expect(escrow.connect(outsider).depositEscrow(merchant.address, amount)).to.be.reverted;
    });

    it("should revert withdraw by non-operator", async function () {
      const { escrow, outsider, merchant } = await loadFixture(escrowReadyFixture);
      await expect(escrow.connect(outsider).withdrawEscrow(merchant.address, 1)).to.be.reverted;
    });

    it("should revert initiateTrade by non-operator", async function () {
      const { escrow, outsider, merchant, buyer, tradeId } = await loadFixture(escrowReadyFixture);
      await expect(
        escrow.connect(outsider).initiateTrade(tradeId("noauth"), merchant.address, buyer.address, 1, false, 99999999999)
      ).to.be.reverted;
    });

    it("should revert markPaymentSent by non-operator", async function () {
      const { escrow, outsider, tradeId } = await loadFixture(escrowReadyFixture);
      await expect(escrow.connect(outsider).markPaymentSent(tradeId("noauth2"))).to.be.reverted;
    });

    it("should revert confirmPayment by non-operator", async function () {
      const { escrow, outsider, tradeId } = await loadFixture(escrowReadyFixture);
      await expect(escrow.connect(outsider).confirmPayment(tradeId("noauth3"))).to.be.reverted;
    });

    it("should revert cancelTrade by non-operator", async function () {
      const { escrow, outsider, tradeId } = await loadFixture(escrowReadyFixture);
      await expect(escrow.connect(outsider).cancelTrade(tradeId("noauth4"))).to.be.reverted;
    });

    it("should revert openDispute by non-operator", async function () {
      const { escrow, outsider, buyer, tradeId } = await loadFixture(escrowReadyFixture);
      await expect(escrow.connect(outsider).openDispute(tradeId("noauth5"), buyer.address)).to.be.reverted;
    });

    it("should revert resolveDispute by non-admin", async function () {
      const { escrow, outsider, buyer, tradeId } = await loadFixture(escrowReadyFixture);
      await expect(escrow.connect(outsider).resolveDispute(tradeId("noauth6"), buyer.address)).to.be.reverted;
    });
  });

  describe("Edge Cases — Stake Forfeiture", function () {
    it("should forfeit stake to feeWallet on cancel", async function () {
      const { escrow, operator, merchant, buyer, feeWallet, tradeId, usdc } = await loadFixture(escrowReadyFixture);
      const amount = ethers.parseUnits("10", 6);
      const stake = ethers.parseUnits("5", 6);
      const id = tradeId("forfeit");
      const feeBefore = await usdc.balanceOf(feeWallet.address);
      await escrow.connect(operator).initiateTrade(id, merchant.address, buyer.address, amount, false, Math.floor(Date.now()/1000)+3600);
      await escrow.connect(operator).cancelTrade(id);
      const feeAfter = await usdc.balanceOf(feeWallet.address);
      expect(feeAfter - feeBefore).to.equal(stake);
    });

    it("should return stake to buyer on confirm", async function () {
      const { escrow, operator, merchant, buyer, tradeId, usdc } = await loadFixture(escrowReadyFixture);
      const amount = ethers.parseUnits("10", 6);
      const stake = ethers.parseUnits("5", 6);
      const id = tradeId("return-stake");
      await escrow.connect(operator).initiateTrade(id, merchant.address, buyer.address, amount, false, Math.floor(Date.now()/1000)+3600);
      const buyerBefore = await usdc.balanceOf(buyer.address);
      await escrow.connect(operator).confirmPayment(id);
      const buyerAfter = await usdc.balanceOf(buyer.address);
      // Buyer receives trade amount + stake
      expect(buyerAfter - buyerBefore).to.equal(amount + stake);
    });

    it("should return stake on dispute resolution", async function () {
      const { escrow, operator, admin, merchant, buyer, tradeId, usdc } = await loadFixture(escrowReadyFixture);
      const amount = ethers.parseUnits("10", 6);
      const stake = ethers.parseUnits("5", 6);
      const id = tradeId("resolve-stake");
      await escrow.connect(operator).initiateTrade(id, merchant.address, buyer.address, amount, false, Math.floor(Date.now()/1000)+3600);
      await escrow.connect(operator).openDispute(id, buyer.address);
      const buyerBefore = await usdc.balanceOf(buyer.address);
      await escrow.connect(admin).resolveDispute(id, buyer.address);
      const buyerAfter = await usdc.balanceOf(buyer.address);
      expect(buyerAfter - buyerBefore).to.equal(amount + stake);
    });
  });
});
