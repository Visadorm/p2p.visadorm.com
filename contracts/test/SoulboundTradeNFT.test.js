const { expect } = require("chai");
const { ethers } = require("hardhat");
const {
  loadFixture,
} = require("@nomicfoundation/hardhat-toolbox/network-helpers");

describe("SoulboundTradeNFT", function () {
  async function deployFixture() {
    const [deployer, minter, buyer, merchant, outsider] =
      await ethers.getSigners();

    const nft = await ethers.deployContract("SoulboundTradeNFT");
    await nft.waitForDeployment();

    // Grant MINTER_ROLE to the minter account
    const MINTER_ROLE = await nft.MINTER_ROLE();
    await nft.grantRole(MINTER_ROLE, minter.address);

    const tradeId = (id) =>
      ethers.keccak256(ethers.toUtf8Bytes(`trade-${id}`));

    return { nft, deployer, minter, buyer, merchant, outsider, tradeId, MINTER_ROLE };
  }

  /**
   * Fixture with one NFT already minted.
   */
  async function mintedFixture() {
    const fixture = await loadFixture(deployFixture);
    const { nft, minter, buyer, merchant, tradeId } = fixture;

    const id = tradeId(1);
    const amount = ethers.parseUnits("500", 6);
    const location = "Central Park, NYC";

    await nft
      .connect(minter)
      .mint(buyer.address, id, merchant.address, amount, location);

    const tokenId = await nft.tradeIdToTokenId(id);

    return { ...fixture, id, amount, location, tokenId };
  }

  // ─── Minting ───

  describe("Minting", function () {
    it("mint creates NFT with correct data", async function () {
      const { nft, minter, buyer, merchant, tradeId } =
        await loadFixture(deployFixture);

      const id = tradeId(1);
      const amount = ethers.parseUnits("500", 6);
      const location = "Central Park, NYC";

      await expect(
        nft.connect(minter).mint(buyer.address, id, merchant.address, amount, location)
      )
        .to.emit(nft, "TradeNFTMinted")
        .withArgs(1, id, buyer.address, merchant.address, amount);

      // Check NFT ownership
      expect(await nft.ownerOf(1)).to.equal(buyer.address);

      // Check stored data
      const data = await nft.tokenData(1);
      expect(data.tradeId).to.equal(id);
      expect(data.merchant).to.equal(merchant.address);
      expect(data.buyer).to.equal(buyer.address);
      expect(data.amount).to.equal(amount);
      expect(data.meetingLocation).to.equal(location);
      expect(data.mintedAt).to.be.gt(0);

      // Check tradeIdToTokenId mapping
      expect(await nft.tradeIdToTokenId(id)).to.equal(1);
    });

    it("only MINTER_ROLE can mint", async function () {
      const { nft, outsider, buyer, merchant, tradeId } =
        await loadFixture(deployFixture);

      const id = tradeId(1);

      await expect(
        nft
          .connect(outsider)
          .mint(buyer.address, id, merchant.address, 1000, "Location")
      ).to.be.reverted;
    });

    it("cannot mint duplicate trade ID", async function () {
      const { nft, minter, buyer, merchant, id } =
        await loadFixture(mintedFixture);

      await expect(
        nft
          .connect(minter)
          .mint(buyer.address, id, merchant.address, 1000, "Other Location")
      ).to.be.revertedWith("NFT already minted for trade");
    });

    it("increments token IDs sequentially", async function () {
      const { nft, minter, buyer, merchant, tradeId } =
        await loadFixture(deployFixture);

      await nft
        .connect(minter)
        .mint(buyer.address, tradeId(1), merchant.address, 100, "Loc A");

      await nft
        .connect(minter)
        .mint(buyer.address, tradeId(2), merchant.address, 200, "Loc B");

      expect(await nft.tradeIdToTokenId(tradeId(1))).to.equal(1);
      expect(await nft.tradeIdToTokenId(tradeId(2))).to.equal(2);
    });
  });

  // ─── Burning ───

  describe("Burning", function () {
    it("burn removes NFT", async function () {
      const { nft, minter, id, tokenId } = await loadFixture(mintedFixture);

      await expect(nft.connect(minter).burn(tokenId))
        .to.emit(nft, "TradeNFTBurned")
        .withArgs(tokenId, id);

      // Token no longer exists
      await expect(nft.ownerOf(tokenId)).to.be.reverted;

      // Mappings cleared
      expect(await nft.tradeIdToTokenId(id)).to.equal(0);
    });

    it("only MINTER_ROLE can burn", async function () {
      const { nft, outsider, tokenId } = await loadFixture(mintedFixture);

      await expect(
        nft.connect(outsider).burn(tokenId)
      ).to.be.reverted;
    });
  });

  // ─── Soulbound (Transfer Blocked) ───

  describe("Soulbound", function () {
    it("transfer reverts (soulbound)", async function () {
      const { nft, buyer, merchant, tokenId } =
        await loadFixture(mintedFixture);

      await expect(
        nft
          .connect(buyer)
          .transferFrom(buyer.address, merchant.address, tokenId)
      ).to.be.revertedWith("SoulboundTradeNFT: transfer not allowed");
    });

    it("safeTransferFrom also reverts", async function () {
      const { nft, buyer, merchant, tokenId } =
        await loadFixture(mintedFixture);

      await expect(
        nft
          .connect(buyer)
          ["safeTransferFrom(address,address,uint256)"](
            buyer.address,
            merchant.address,
            tokenId
          )
      ).to.be.revertedWith("SoulboundTradeNFT: transfer not allowed");
    });

    it("approve + transferFrom still reverts", async function () {
      const { nft, buyer, merchant, outsider, tokenId } =
        await loadFixture(mintedFixture);

      await nft.connect(buyer).approve(outsider.address, tokenId);

      await expect(
        nft
          .connect(outsider)
          .transferFrom(buyer.address, merchant.address, tokenId)
      ).to.be.revertedWith("SoulboundTradeNFT: transfer not allowed");
    });
  });

  // ─── Queries ───

  describe("Queries", function () {
    it("getByTradeId returns correct data", async function () {
      const { nft, buyer, merchant, id, amount, location } =
        await loadFixture(mintedFixture);

      const data = await nft.getByTradeId(id);

      expect(data.tradeId).to.equal(id);
      expect(data.merchant).to.equal(merchant.address);
      expect(data.buyer).to.equal(buyer.address);
      expect(data.amount).to.equal(amount);
      expect(data.meetingLocation).to.equal(location);
      expect(data.mintedAt).to.be.gt(0);
    });

    it("getByTradeId reverts for non-existent trade", async function () {
      const { nft, tradeId } = await loadFixture(deployFixture);

      await expect(
        nft.getByTradeId(tradeId(999))
      ).to.be.revertedWith("No NFT for trade");
    });

    it("supportsInterface returns true for ERC721 and AccessControl", async function () {
      const { nft } = await loadFixture(deployFixture);

      // ERC721 interface ID
      expect(await nft.supportsInterface("0x80ac58cd")).to.be.true;
      // AccessControl interface ID
      expect(await nft.supportsInterface("0x7965db0b")).to.be.true;
      // ERC165 interface ID
      expect(await nft.supportsInterface("0x01ffc9a7")).to.be.true;
    });
  });
});
