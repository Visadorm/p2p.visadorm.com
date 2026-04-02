import { createContext, useContext, useState, useEffect, useCallback } from "react"
import { ethers } from "ethers"
import { api } from "@/lib/api"
import { encryptWalletData, decryptWalletData } from "@/lib/wallet-crypto"

const WalletContext = createContext(undefined)

// Module-level singleton — prevents WalletConnect "already initialized" errors
let wcProviderInstance = null

// Fallback chain config — overridden by backend settings via Inertia props
const FALLBACK_CHAIN_ID = 84532

const CHAIN_CONFIGS = {
  84532: {
    chainName: "Base Sepolia",
    rpcUrls: ["https://sepolia.base.org"],
    blockExplorerUrls: ["https://sepolia.basescan.org"],
  },
  8453: {
    chainName: "Base",
    rpcUrls: ["https://mainnet.base.org"],
    blockExplorerUrls: ["https://basescan.org"],
  },
}

export function WalletProvider({ children, blockchain }) {
  const TARGET_CHAIN_ID = blockchain?.chain_id || FALLBACK_CHAIN_ID
  const TARGET_RPC_URL = blockchain?.rpc_url || CHAIN_CONFIGS[TARGET_CHAIN_ID]?.rpcUrls?.[0] || "https://sepolia.base.org"
  const CHAIN_CONFIG = {
    chainId: ethers.utils.hexValue(TARGET_CHAIN_ID),
    chainName: CHAIN_CONFIGS[TARGET_CHAIN_ID]?.chainName || blockchain?.network || "Base Sepolia",
    nativeCurrency: { name: "ETH", symbol: "ETH", decimals: 18 },
    rpcUrls: [TARGET_RPC_URL],
    blockExplorerUrls: CHAIN_CONFIGS[TARGET_CHAIN_ID]?.blockExplorerUrls || ["https://sepolia.basescan.org"],
  }

  const [address, setAddress] = useState(null)
  const [chainId, setChainId] = useState(null)
  const [provider, setProvider] = useState(null)
  const [signer, setSigner] = useState(null)
  const [phraseWallet, setPhraseWallet] = useState(null) // in-memory only, never persisted raw
  const [connecting, setConnecting] = useState(false)
  const [connectedWallet, setConnectedWallet] = useState(null)
  const [merchant, setMerchant] = useState(null)
  const [token, setToken] = useState(() => localStorage.getItem("auth_token"))
  const [authenticating, setAuthenticating] = useState(false)
  const [isInitialized, setIsInitialized] = useState(false)

  const isConnected = !!address
  const isAuthenticated = !!token && !!merchant
  const isCorrectChain = chainId === TARGET_CHAIN_ID
  const isNewMerchant = merchant && (!merchant.username || merchant.username.startsWith("user_"))
  const hasPhraseWallet = !!localStorage.getItem("phrase_wallet_encrypted")

  // Connect an injected browser wallet (MetaMask, Trust, Coinbase, WalletConnect)
  const connectWallet = useCallback(async (walletType = "injected") => {
    let ethereum = null

    if (walletType === "metamask") {
      ethereum = window.ethereum?.providers?.find((p) => p.isMetaMask) || (window.ethereum?.isMetaMask ? window.ethereum : null)
    } else if (walletType === "coinbase") {
      ethereum = window.ethereum?.providers?.find((p) => p.isCoinbaseWallet) || (window.ethereum?.isCoinbaseWallet ? window.ethereum : null)
    } else if (walletType === "trust") {
      ethereum = window.trustwallet || (window.ethereum?.isTrust ? window.ethereum : null)
    } else if (walletType === "walletconnect") {
      try {
        if (!wcProviderInstance) {
          const { EthereumProvider } = await import("@walletconnect/ethereum-provider")
          wcProviderInstance = await EthereumProvider.init({
            projectId: import.meta.env.VITE_WALLETCONNECT_PROJECT_ID || "",
            chains: [TARGET_CHAIN_ID],
            optionalChains: [8453],
            showQrModal: true,
          })
        }
        await wcProviderInstance.enable()
        ethereum = wcProviderInstance
      } catch (err) {
        // If the cached instance errored, clear it so next attempt can reinitialize
        wcProviderInstance = null
        console.error("WalletConnect init failed:", err)
        return null
      }
    } else {
      ethereum = window.ethereum
    }

    if (!ethereum && walletType !== "walletconnect") {
      // Trust and Coinbase mobile wallets connect via WalletConnect QR — fall back silently
      if (walletType === "trust" || walletType === "coinbase") {
        return connectWallet("walletconnect")
      }
      // Mobile: deep-link into MetaMask in-app browser
      const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent)
      if (isMobile && walletType === "metamask") {
        const dappUrl = window.location.href.replace(/^https?:\/\//, "")
        window.location.href = `https://metamask.app.link/dapp/${dappUrl}`
        return null
      }
      // Desktop: MetaMask not installed — send to download page
      window.open("https://metamask.io/download/", "_blank")
      return null
    }

    if (!ethereum) return null

    setConnecting(true)
    try {
      // Request accounts first
      await ethereum.request({ method: "eth_requestAccounts" })

      // Check chain and switch if needed before creating the ethers provider
      const chainIdHex = await ethereum.request({ method: "eth_chainId" })
      const currentChainId = parseInt(chainIdHex, 16)

      if (currentChainId !== TARGET_CHAIN_ID) {
        try {
          await ethereum.request({
            method: "wallet_switchEthereumChain",
            params: [{ chainId: CHAIN_CONFIG.chainId }],
          })
        } catch (switchErr) {
          if (switchErr.code === 4902) {
            await ethereum.request({
              method: "wallet_addEthereumChain",
              params: [CHAIN_CONFIG],
            })
          } else {
            throw switchErr
          }
        }
      }

      // Create a fresh Web3Provider AFTER any chain switch to avoid NETWORK_ERROR
      const web3Provider = new ethers.providers.Web3Provider(ethereum)
      const web3Signer = web3Provider.getSigner()
      const walletAddress = await web3Signer.getAddress()
      const network = await web3Provider.getNetwork()

      setProvider(web3Provider)
      setSigner(web3Signer)
      setAddress(walletAddress)
      setChainId(network.chainId)
      setConnectedWallet(walletType)

      localStorage.setItem("wallet_connected", walletType)

      return { walletAddress, web3Signer }
    } catch (err) {
      console.error("Wallet connection failed:", err)
      return null
    } finally {
      setConnecting(false)
    }
  }, [])

  // Import a wallet via BIP39 recovery phrase (works for any wallet)
  // Encrypts the private key with the user's password — never stored raw
  const connectWithPhrase = useCallback(async (phrase, password) => {
    const trimmed = phrase.trim()

    if (!ethers.utils.isValidMnemonic(trimmed)) {
      throw new Error("Invalid recovery phrase. Please check your words and try again.")
    }

    const wallet = ethers.Wallet.fromMnemonic(trimmed)

    // Encrypt private key with user password (AES-256-GCM, PBKDF2 100k)
    const encrypted = await encryptWalletData(wallet.privateKey, password)

    localStorage.setItem("phrase_wallet_encrypted", encrypted)
    localStorage.setItem("phrase_wallet_address", wallet.address)
    localStorage.setItem("wallet_connected", "phrase")

    setPhraseWallet(wallet)
    setAddress(wallet.address)
    setConnectedWallet("phrase")

    return { walletAddress: wallet.address, phraseWallet: wallet }
  }, [])

  // Unlock a previously imported phrase wallet using the saved password
  const unlockPhraseWallet = useCallback(async (password) => {
    const encrypted = localStorage.getItem("phrase_wallet_encrypted")
    if (!encrypted) throw new Error("No saved wallet found.")

    try {
      const privateKey = await decryptWalletData(encrypted, password)
      const wallet = new ethers.Wallet(privateKey)

      setPhraseWallet(wallet)
      setAddress(wallet.address)
      setConnectedWallet("phrase")

      return wallet
    } catch {
      throw new Error("Incorrect password.")
    }
  }, [])

  // Full auth flow: connect → get nonce → sign → verify → get token
  const connect = useCallback(async (walletType = "injected", phraseData = null) => {
    let walletAddress, signerOrWallet

    if (walletType === "phrase") {
      if (!phraseData) throw new Error("Recovery phrase and password are required.")
      const result = await connectWithPhrase(phraseData.phrase, phraseData.password)
      walletAddress = result.walletAddress
      signerOrWallet = result.phraseWallet
    } else {
      const result = await connectWallet(walletType)
      if (!result) throw new Error("Wallet connection was cancelled or failed. Please try again.")
      walletAddress = result.walletAddress
      signerOrWallet = result.web3Signer
    }

    setAuthenticating(true)
    try {
      const nonceResponse = await api.getNonce(walletAddress)
      const { nonce, message } = nonceResponse.data

      const signature = await signerOrWallet.signMessage(message)

      const verifyResponse = await api.verify(walletAddress, signature, nonce)
      const { token: authToken, merchant: merchantData } = verifyResponse.data

      localStorage.setItem("auth_token", authToken)
      setToken(authToken)
      setMerchant(merchantData)

      return merchantData
    } catch (err) {
      console.error("Auth failed:", err)
      disconnect()
      throw err
    } finally {
      setAuthenticating(false)
    }
  }, [connectWallet, connectWithPhrase])

  // Disconnect and clear all state + storage
  const disconnect = useCallback(() => {
    setAddress(null)
    setChainId(null)
    setProvider(null)
    setSigner(null)
    setPhraseWallet(null)
    setConnectedWallet(null)
    setMerchant(null)
    setToken(null)
    localStorage.removeItem("wallet_connected")
    localStorage.removeItem("auth_token")
    // Note: phrase_wallet_encrypted and phrase_wallet_address are kept so user
    // can unlock again without re-importing the phrase. Call clearPhraseWallet()
    // to fully remove the saved wallet.

    // Reset WalletConnect singleton so a fresh connection can be made next time
    if (wcProviderInstance) {
      wcProviderInstance.disconnect().catch(() => {})
      wcProviderInstance = null
    }

    api.logout().catch(() => {})
  }, [])

  // Permanently delete the saved phrase wallet from localStorage
  const clearPhraseWallet = useCallback(() => {
    localStorage.removeItem("phrase_wallet_encrypted")
    localStorage.removeItem("phrase_wallet_address")
  }, [])

  // Switch to the correct chain
  const switchChain = useCallback(async () => {
    if (!window.ethereum) return
    try {
      await window.ethereum.request({
        method: "wallet_switchEthereumChain",
        params: [{ chainId: CHAIN_CONFIG.chainId }],
      })
    } catch (err) {
      if (err.code === 4902) {
        await window.ethereum.request({
          method: "wallet_addEthereumChain",
          params: [CHAIN_CONFIG],
        })
      }
    }
  }, [])

  // Sign a message (supports both injected signer and phrase wallet)
  const signMessage = useCallback(async (message) => {
    if (phraseWallet) return await phraseWallet.signMessage(message)
    if (signer) return await signer.signMessage(message)
    return null
  }, [signer, phraseWallet])

  // Refresh merchant data from API
  const refreshMerchant = useCallback(async () => {
    if (!token) return
    try {
      const response = await api.me()
      setMerchant(response.data)
    } catch {
      disconnect()
    }
  }, [token, disconnect])

  // Auto-reconnect on page load
  useEffect(() => {
    const savedWallet = localStorage.getItem("wallet_connected")
    const savedToken = localStorage.getItem("auth_token")

    if (savedWallet === "phrase") {
      // Phrase wallet: restore address without needing password (public address is safe to store)
      const savedAddress = localStorage.getItem("phrase_wallet_address")
      if (savedAddress && savedToken) {
        setAddress(savedAddress)
        setConnectedWallet("phrase")
        api.me()
          .then((response) => setMerchant(response.data))
          .catch(() => {
            localStorage.removeItem("auth_token")
            setToken(null)
          })
          .finally(() => setIsInitialized(true))
      } else {
        setIsInitialized(true)
      }
    } else if (savedWallet && savedToken && window.ethereum) {
      connectWallet(savedWallet)
        .then(() =>
          api.me()
            .then((response) => setMerchant(response.data))
            .catch(() => {
              localStorage.removeItem("auth_token")
              localStorage.removeItem("wallet_connected")
              setToken(null)
            }),
        )
        .finally(() => setIsInitialized(true))
    } else {
      setIsInitialized(true)
    }
  }, [connectWallet])

  // Listen for injected wallet changes
  useEffect(() => {
    if (!window.ethereum) return
    const handleAccounts = (accounts) => {
      if (accounts.length === 0) disconnect()
      else {
        const newAddr = accounts[0]
        if (address && newAddr.toLowerCase() !== address.toLowerCase()) {
          disconnect()
        } else {
          setAddress(newAddr)
        }
      }
    }
    const handleChain = (id) => setChainId(parseInt(id, 16))

    window.ethereum.on("accountsChanged", handleAccounts)
    window.ethereum.on("chainChanged", handleChain)
    return () => {
      window.ethereum.removeListener("accountsChanged", handleAccounts)
      window.ethereum.removeListener("chainChanged", handleChain)
    }
  }, [address, disconnect])

  return (
    <WalletContext.Provider value={{
      address, isConnected, isAuthenticated, isInitialized, isNewMerchant,
      chainId, isCorrectChain,
      provider, signer, phraseWallet, hasPhraseWallet,
      connecting, authenticating, connectedWallet,
      merchant, token,
      connect, disconnect, switchChain, signMessage,
      unlockPhraseWallet, clearPhraseWallet, refreshMerchant,
    }}>
      {children}
    </WalletContext.Provider>
  )
}

export function useWallet() {
  const ctx = useContext(WalletContext)
  if (!ctx) throw new Error("useWallet must be used within WalletProvider")
  return ctx
}
