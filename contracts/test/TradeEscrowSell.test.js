const { expect } = require("chai");
const { ethers } = require("hardhat");
const { loadFixture, time } = require("@nomicfoundation/hardhat-toolbox/network-helpers");

describe("TradeEscrowContract — Sell flow", function () {
  async function deployFixture() {
    const [deployer, admin, operator, seller, buyer, feeWallet, outsider] =
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

    const mintAmount = ethers.parseUnits("10000", 6);
    await usdc.mint(seller.address, mintAmount);
    await usdc.mint(buyer.address, mintAmount);

    await usdc.connect(seller).approve(await escrow.getAddress(), ethers.MaxUint256);
    await usdc.connect(buyer).approve(await escrow.getAddress(), ethers.MaxUint256);

    const tradeId = (id) => ethers.keccak256(ethers.toUtf8Bytes(`sell-${id}`));
    const futureExpiry = async () => (await time.latest()) + 3600;

    return {
      usdc, nft, escrow, deployer, admin, operator, seller, buyer, feeWallet, outsider,
      tradeId, futureExpiry,
    };
  }

  async function fundedOfferFixture() {
    const fx = await loadFixture(deployFixture);
    const id = fx.tradeId(1);
    const amount = ethers.parseUnits("100", 6);
    await fx.escrow.connect(fx.seller).fundSellTrade(id, amount, false, await fx.futureExpiry());
    return { ...fx, id, amount };
  }

  async function takenOfferFixture() {
    const fx = await loadFixture(deployFixture);
    const id = fx.tradeId(1);
    const amount = ethers.parseUnits("100", 6);
    await fx.escrow.connect(fx.seller).fundSellTrade(id, amount, false, await fx.futureExpiry());
    await fx.escrow.connect(fx.buyer).takeSellTrade(id);
    return { ...fx, id, amount };
  }

  describe("fundSellTrade", function () {
    it("seller funds offer, status SellFunded, USDC pulled into contract", async function () {
      const { escrow, seller, usdc, tradeId, futureExpiry } = await loadFixture(deployFixture);
      const id = tradeId(1);
      const amount = ethers.parseUnits("100", 6);

      await expect(escrow.connect(seller).fundSellTrade(id, amount, false, await futureExpiry()))
        .to.emit(escrow, "SellTradeFunded");

      const trade = await escrow.trades(id);
      expect(trade.seller).to.equal(seller.address);
      expect(trade.amount).to.equal(amount);
      expect(trade.status).to.equal(7);
      expect(trade.kind).to.equal(1);
      expect(await usdc.balanceOf(await escrow.getAddress())).to.equal(amount);
    });

    it("reverts on duplicate trade id", async function () {
      const { escrow, seller, tradeId, futureExpiry } = await loadFixture(deployFixture);
      const id = tradeId(1);
      const amount = ethers.parseUnits("100", 6);
      await escrow.connect(seller).fundSellTrade(id, amount, false, await futureExpiry());
      await expect(
        escrow.connect(seller).fundSellTrade(id, amount, false, await futureExpiry())
      ).to.be.revertedWith("Trade already exists");
    });

    it("reverts below minimum trade amount", async function () {
      const { escrow, seller, tradeId, futureExpiry } = await loadFixture(deployFixture);
      await expect(
        escrow.connect(seller).fundSellTrade(tradeId(1), ethers.parseUnits("5", 6), false, await futureExpiry())
      ).to.be.revertedWith("Below minimum trade amount");
    });

    it("reverts when expiry already past", async function () {
      const { escrow, seller, tradeId } = await loadFixture(deployFixture);
      const past = (await time.latest()) - 10;
      await expect(
        escrow.connect(seller).fundSellTrade(tradeId(1), ethers.parseUnits("100", 6), false, past)
      ).to.be.revertedWith("Expiry must be in future");
    });
  });

  describe("takeSellTrade", function () {
    it("buyer takes offer, status EscrowLocked, stake pulled", async function () {
      const { escrow, buyer, id } = await loadFixture(fundedOfferFixture);

      await expect(escrow.connect(buyer).takeSellTrade(id))
        .to.emit(escrow, "SellTradeTaken").withArgs(id, buyer.address);

      const trade = await escrow.trades(id);
      expect(trade.buyer).to.equal(buyer.address);
      expect(trade.status).to.equal(1);
      expect(trade.stakeAmount).to.equal(ethers.parseUnits("5", 6));
    });

    it("reverts when seller tries to take own offer", async function () {
      const { escrow, seller, id } = await loadFixture(fundedOfferFixture);
      await expect(escrow.connect(seller).takeSellTrade(id))
        .to.be.revertedWith("Seller cannot take own offer");
    });

    it("reverts when offer already taken", async function () {
      const { escrow, buyer, outsider, id } = await loadFixture(fundedOfferFixture);
      await escrow.connect(buyer).takeSellTrade(id);
      await expect(escrow.connect(outsider).takeSellTrade(id))
        .to.be.revertedWith("Offer not available");
    });

    it("reverts when offer expired", async function () {
      const { escrow, buyer, id } = await loadFixture(fundedOfferFixture);
      await time.increase(7200);
      await expect(escrow.connect(buyer).takeSellTrade(id)).to.be.revertedWith("Offer expired");
    });

    it("private offer skips stake collection", async function () {
      const { escrow, seller, buyer, usdc, tradeId, futureExpiry } = await loadFixture(deployFixture);
      const id = tradeId(2);
      await escrow.connect(seller).fundSellTrade(id, ethers.parseUnits("100", 6), true, await futureExpiry());

      const buyerBefore = await usdc.balanceOf(buyer.address);
      await escrow.connect(buyer).takeSellTrade(id);
      expect(await usdc.balanceOf(buyer.address)).to.equal(buyerBefore);

      const trade = await escrow.trades(id);
      expect(trade.stakeAmount).to.equal(0);
    });
  });

  describe("markSellPaymentSent", function () {
    it("buyer marks payment sent, status PaymentSent", async function () {
      const { escrow, buyer, id } = await loadFixture(takenOfferFixture);
      await expect(escrow.connect(buyer).markSellPaymentSent(id))
        .to.emit(escrow, "PaymentMarkedSent").withArgs(id);
      const trade = await escrow.trades(id);
      expect(trade.status).to.equal(2);
    });

    it("reverts when caller is not buyer", async function () {
      const { escrow, seller, id } = await loadFixture(takenOfferFixture);
      await expect(escrow.connect(seller).markSellPaymentSent(id))
        .to.be.revertedWith("Only buyer can mark payment sent");
    });
  });

  describe("releaseSellEscrow (direct seller authority)", function () {
    it("seller releases, buyer receives amount minus fee, fee to feeWallet", async function () {
      const { escrow, seller, buyer, feeWallet, usdc, id, amount } = await loadFixture(takenOfferFixture);
      await escrow.connect(buyer).markSellPaymentSent(id);

      const buyerBefore = await usdc.balanceOf(buyer.address);
      const feeBefore = await usdc.balanceOf(feeWallet.address);

      await expect(escrow.connect(seller).releaseSellEscrow(id))
        .to.emit(escrow, "SellEscrowReleased");

      const fee = (amount * 20n) / 10000n;
      expect(await usdc.balanceOf(buyer.address)).to.equal(buyerBefore + amount - fee + ethers.parseUnits("5", 6));
      expect(await usdc.balanceOf(feeWallet.address)).to.equal(feeBefore + fee);

      const trade = await escrow.trades(id);
      expect(trade.status).to.equal(3);
    });

    it("buyer cannot call release", async function () {
      const { escrow, buyer, id } = await loadFixture(takenOfferFixture);
      await expect(escrow.connect(buyer).releaseSellEscrow(id))
        .to.be.revertedWith("Only seller can release");
    });

    it("operator cannot call direct release", async function () {
      const { escrow, operator, id } = await loadFixture(takenOfferFixture);
      await expect(escrow.connect(operator).releaseSellEscrow(id))
        .to.be.revertedWith("Only seller can release");
    });

    it("admin cannot call direct release", async function () {
      const { escrow, admin, id } = await loadFixture(takenOfferFixture);
      await expect(escrow.connect(admin).releaseSellEscrow(id))
        .to.be.revertedWith("Only seller can release");
    });

    it("reverts on already-completed trade (finality)", async function () {
      const { escrow, seller, id } = await loadFixture(takenOfferFixture);
      await escrow.connect(seller).releaseSellEscrow(id);
      await expect(escrow.connect(seller).releaseSellEscrow(id))
        .to.be.revertedWith("Invalid trade status");
    });
  });

  describe("executeMetaSellRelease (EIP-712 meta-tx)", function () {
    async function signRelease(seller, escrow, tradeId, nonce, deadline) {
      const domain = {
        name: "VisadormP2P",
        version: "1",
        chainId: (await ethers.provider.getNetwork()).chainId,
        verifyingContract: await escrow.getAddress(),
      };
      const types = {
        ReleaseSellEscrow: [
          { name: "tradeId", type: "bytes32" },
          { name: "nonce", type: "uint256" },
          { name: "deadline", type: "uint256" },
        ],
      };
      const value = { tradeId, nonce, deadline };
      return seller.signTypedData(domain, types, value);
    }

    it("operator submits valid seller signature, release succeeds", async function () {
      const { escrow, seller, buyer, operator, id } = await loadFixture(takenOfferFixture);
      await escrow.connect(buyer).markSellPaymentSent(id);

      const nonce = await escrow.sellerNonce(seller.address);
      const deadline = (await time.latest()) + 600;
      const sig = await signRelease(seller, escrow, id, nonce, deadline);

      await expect(escrow.connect(operator).executeMetaSellRelease(id, nonce, deadline, sig))
        .to.emit(escrow, "SellEscrowReleased");

      expect(await escrow.sellerNonce(seller.address)).to.equal(nonce + 1n);
    });

    it("reverts on tampered signature", async function () {
      const { escrow, seller, operator, outsider, id } = await loadFixture(takenOfferFixture);
      const nonce = await escrow.sellerNonce(seller.address);
      const deadline = (await time.latest()) + 600;
      const sig = await signRelease(outsider, escrow, id, nonce, deadline);

      await expect(escrow.connect(operator).executeMetaSellRelease(id, nonce, deadline, sig))
        .to.be.revertedWith("Bad seller signature");
    });

    it("reverts on replayed nonce", async function () {
      const { escrow, seller, operator, id, tradeId, futureExpiry, buyer } = await loadFixture(deployFixture);
      const idA = tradeId("A");
      const idB = tradeId("B");
      const amount = ethers.parseUnits("100", 6);
      await escrow.connect(seller).fundSellTrade(idA, amount, false, await futureExpiry());
      await escrow.connect(seller).fundSellTrade(idB, amount, false, await futureExpiry());
      await escrow.connect(buyer).takeSellTrade(idA);
      await escrow.connect(buyer).takeSellTrade(idB);

      const nonce = await escrow.sellerNonce(seller.address);
      const deadline = (await time.latest()) + 600;
      const sigA = await signRelease(seller, escrow, idA, nonce, deadline);

      await escrow.connect(operator).executeMetaSellRelease(idA, nonce, deadline, sigA);

      const sigB_replay = await signRelease(seller, escrow, idB, nonce, deadline);
      await expect(escrow.connect(operator).executeMetaSellRelease(idB, nonce, deadline, sigB_replay))
        .to.be.revertedWith("Invalid nonce");
    });

    it("reverts on expired deadline", async function () {
      const { escrow, seller, operator, id } = await loadFixture(takenOfferFixture);
      const nonce = await escrow.sellerNonce(seller.address);
      const deadline = (await time.latest()) + 60;
      const sig = await signRelease(seller, escrow, id, nonce, deadline);
      await time.increase(120);

      await expect(escrow.connect(operator).executeMetaSellRelease(id, nonce, deadline, sig))
        .to.be.revertedWith("Signature expired");
    });

    it("non-operator cannot relay", async function () {
      const { escrow, seller, outsider, id } = await loadFixture(takenOfferFixture);
      const nonce = await escrow.sellerNonce(seller.address);
      const deadline = (await time.latest()) + 600;
      const sig = await signRelease(seller, escrow, id, nonce, deadline);

      await expect(escrow.connect(outsider).executeMetaSellRelease(id, nonce, deadline, sig))
        .to.be.reverted;
    });
  });

  describe("openSellDispute", function () {
    it("buyer opens dispute, status Disputed", async function () {
      const { escrow, buyer, id } = await loadFixture(takenOfferFixture);
      await expect(escrow.connect(buyer).openSellDispute(id))
        .to.emit(escrow, "DisputeOpened").withArgs(id, buyer.address);
    });

    it("seller opens dispute, status Disputed", async function () {
      const { escrow, seller, id } = await loadFixture(takenOfferFixture);
      await escrow.connect(seller).openSellDispute(id);
      const trade = await escrow.trades(id);
      expect(trade.status).to.equal(4);
    });

    it("non-party cannot open dispute", async function () {
      const { escrow, outsider, id } = await loadFixture(takenOfferFixture);
      await expect(escrow.connect(outsider).openSellDispute(id))
        .to.be.revertedWith("Not a party to this trade");
    });

    it("cannot dispute completed trade (finality)", async function () {
      const { escrow, seller, buyer, id } = await loadFixture(takenOfferFixture);
      await escrow.connect(seller).releaseSellEscrow(id);
      await expect(escrow.connect(buyer).openSellDispute(id))
        .to.be.revertedWith("Invalid trade status");
    });
  });

  describe("resolveSellDispute (multisig only)", function () {
    it("admin resolves for buyer, USDC sent to buyer minus fee", async function () {
      const { escrow, seller, buyer, admin, feeWallet, usdc, id, amount } = await loadFixture(takenOfferFixture);
      await escrow.connect(buyer).openSellDispute(id);

      const buyerBefore = await usdc.balanceOf(buyer.address);
      await escrow.connect(admin).resolveSellDispute(id, buyer.address);

      const fee = (amount * 20n) / 10000n;
      expect(await usdc.balanceOf(buyer.address)).to.equal(buyerBefore + amount - fee + ethers.parseUnits("5", 6));
    });

    it("admin resolves for seller, USDC returned to seller minus fee", async function () {
      const { escrow, seller, buyer, admin, usdc, id, amount } = await loadFixture(takenOfferFixture);
      await escrow.connect(buyer).openSellDispute(id);

      const sellerBefore = await usdc.balanceOf(seller.address);
      await escrow.connect(admin).resolveSellDispute(id, seller.address);

      const fee = (amount * 20n) / 10000n;
      expect(await usdc.balanceOf(seller.address)).to.equal(sellerBefore + amount - fee);
    });

    it("operator cannot resolve dispute", async function () {
      const { escrow, buyer, operator, id } = await loadFixture(takenOfferFixture);
      await escrow.connect(buyer).openSellDispute(id);
      await expect(escrow.connect(operator).resolveSellDispute(id, buyer.address))
        .to.be.reverted;
    });
  });

  describe("cancelSellOffer (seller before buyer takes)", function () {
    it("seller cancels own funded offer, USDC refunded", async function () {
      const { escrow, seller, usdc, id, amount } = await loadFixture(fundedOfferFixture);

      const before = await usdc.balanceOf(seller.address);
      await expect(escrow.connect(seller).cancelSellOffer(id))
        .to.emit(escrow, "SellOfferCancelled");
      expect(await usdc.balanceOf(seller.address)).to.equal(before + amount);
    });

    it("non-seller cannot cancel offer", async function () {
      const { escrow, outsider, id } = await loadFixture(fundedOfferFixture);
      await expect(escrow.connect(outsider).cancelSellOffer(id))
        .to.be.revertedWith("Only seller can cancel own offer");
    });

    it("cannot cancel after buyer takes (use dispute instead)", async function () {
      const { escrow, seller, id } = await loadFixture(takenOfferFixture);
      await expect(escrow.connect(seller).cancelSellOffer(id))
        .to.be.revertedWith("Offer no longer cancellable by seller");
    });
  });

  describe("cancelExpiredSellTrade (anyone, after expiry)", function () {
    it("refunds seller after offer expires with no buyer", async function () {
      const { escrow, seller, outsider, usdc, id, amount } = await loadFixture(fundedOfferFixture);
      await time.increase(7200);

      const before = await usdc.balanceOf(seller.address);
      await escrow.connect(outsider).cancelExpiredSellTrade(id);
      expect(await usdc.balanceOf(seller.address)).to.equal(before + amount);
    });

    it("refunds seller and stake-payer when expired post-take", async function () {
      const { escrow, seller, buyer, usdc, id, amount } = await loadFixture(takenOfferFixture);
      await time.increase(7200);

      const sellerBefore = await usdc.balanceOf(seller.address);
      const buyerBefore = await usdc.balanceOf(buyer.address);
      await escrow.cancelExpiredSellTrade(id);
      expect(await usdc.balanceOf(seller.address)).to.equal(sellerBefore + amount);
      expect(await usdc.balanceOf(buyer.address)).to.equal(buyerBefore + ethers.parseUnits("5", 6));
    });

    it("reverts when not expired", async function () {
      const { escrow, id } = await loadFixture(fundedOfferFixture);
      await expect(escrow.cancelExpiredSellTrade(id)).to.be.revertedWith("Trade not expired");
    });
  });

  describe("Buy/Sell isolation", function () {
    it("sell-only function rejects buy-kind trade", async function () {
      const { usdc, escrow, operator, deployer, admin: adminSig, seller, buyer, feeWallet, tradeId } = await loadFixture(deployFixture);
      const merchant = seller;
      await escrow.connect(operator).depositEscrow(merchant.address, ethers.parseUnits("500", 6));

      const buyId = tradeId("buy-1");
      await escrow.connect(operator).initiateTrade(
        buyId, merchant.address, buyer.address, ethers.parseUnits("100", 6), false, (await time.latest()) + 3600
      );

      await expect(escrow.connect(merchant).releaseSellEscrow(buyId))
        .to.be.revertedWith("Not a sell trade");
    });
  });
});
