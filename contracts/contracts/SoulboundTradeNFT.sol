// SPDX-License-Identifier: MIT
pragma solidity 0.8.27;

import "@openzeppelin/contracts/token/ERC721/ERC721.sol";
import "@openzeppelin/contracts/access/AccessControl.sol";

/**
 * @title SoulboundTradeNFT
 * @dev Non-transferable ERC721 for cash meeting trade verification.
 *      Only the TradeEscrowContract (MINTER_ROLE) can mint and burn.
 *      Tokens are soulbound — transfers are blocked.
 */
contract SoulboundTradeNFT is ERC721, AccessControl {
    bytes32 public constant MINTER_ROLE = keccak256("MINTER_ROLE");

    uint256 private _nextTokenId;

    struct TradeNFTData {
        bytes32 tradeId;
        address merchant;
        address buyer;
        uint256 amount;
        string meetingLocation;
        uint256 mintedAt;
    }

    mapping(uint256 => TradeNFTData) public tokenData;
    mapping(bytes32 => uint256) public tradeIdToTokenId;

    event TradeNFTMinted(
        uint256 indexed tokenId,
        bytes32 indexed tradeId,
        address indexed buyer,
        address merchant,
        uint256 amount
    );

    event TradeNFTBurned(uint256 indexed tokenId, bytes32 indexed tradeId);

    constructor() ERC721("Visadorm Trade NFT", "VTRADE") {
        _grantRole(DEFAULT_ADMIN_ROLE, msg.sender);
    }

    /**
     * @dev Mint a soulbound NFT for a cash meeting trade.
     */
    function mint(
        address buyer,
        bytes32 tradeId,
        address merchant,
        uint256 amount,
        string calldata meetingLocation
    ) external onlyRole(MINTER_ROLE) returns (uint256) {
        require(tradeIdToTokenId[tradeId] == 0, "NFT already minted for trade");

        _nextTokenId++;
        uint256 tokenId = _nextTokenId;

        _mint(buyer, tokenId);

        tokenData[tokenId] = TradeNFTData({
            tradeId: tradeId,
            merchant: merchant,
            buyer: buyer,
            amount: amount,
            meetingLocation: meetingLocation,
            mintedAt: block.timestamp
        });

        tradeIdToTokenId[tradeId] = tokenId;

        emit TradeNFTMinted(tokenId, tradeId, buyer, merchant, amount);

        return tokenId;
    }

    /**
     * @dev Burn an NFT after trade completion or cancellation.
     */
    function burn(uint256 tokenId) external onlyRole(MINTER_ROLE) {
        bytes32 tradeId = tokenData[tokenId].tradeId;

        _burn(tokenId);

        delete tradeIdToTokenId[tradeId];
        delete tokenData[tokenId];

        emit TradeNFTBurned(tokenId, tradeId);
    }

    /**
     * @dev Block all transfers — tokens are soulbound.
     */
    function _update(
        address to,
        uint256 tokenId,
        address auth
    ) internal override returns (address) {
        address from = _ownerOf(tokenId);

        // Allow minting (from == address(0)) and burning (to == address(0))
        if (from != address(0) && to != address(0)) {
            revert("SoulboundTradeNFT: transfer not allowed");
        }

        return super._update(to, tokenId, auth);
    }

    /**
     * @dev Get NFT data by trade ID.
     */
    function getByTradeId(bytes32 tradeId) external view returns (TradeNFTData memory) {
        uint256 tokenId = tradeIdToTokenId[tradeId];
        require(tokenId != 0, "No NFT for trade");
        return tokenData[tokenId];
    }

    function supportsInterface(
        bytes4 interfaceId
    ) public view override(ERC721, AccessControl) returns (bool) {
        return super.supportsInterface(interfaceId);
    }
}
