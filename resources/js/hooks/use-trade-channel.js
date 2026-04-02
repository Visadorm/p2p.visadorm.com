import { useState, useEffect, useRef, useCallback } from "react"

/**
 * Hook that listens for real-time trade updates via Laravel Echo (Reverb/Pusher).
 * Falls back to 10-second polling if Echo is not configured or connection fails.
 *
 * @param {string} tradeHash  - The trade hash to subscribe to
 * @param {Function} fetchTrade - Async function that fetches the latest trade data
 * @param {Object} options
 * @param {boolean} options.enabled - Whether to enable the channel (default: true)
 * @param {number} options.pollInterval - Fallback poll interval in ms (default: 10000)
 * @returns {{ isConnected: boolean, connectionType: 'echo'|'polling'|null }}
 */
export function useTradeChannel(tradeHash, fetchTrade, options = {}) {
  const { enabled = true, pollInterval = 5000 } = options
  const [isConnected, setIsConnected] = useState(false)
  const [connectionType, setConnectionType] = useState(null)
  const echoRef = useRef(null)
  const channelRef = useRef(null)
  const pollRef = useRef(null)

  // Start polling fallback
  const startPolling = useCallback(() => {
    if (pollRef.current) return
    setConnectionType("polling")
    setIsConnected(true)
    pollRef.current = setInterval(() => {
      fetchTrade().catch(() => {})
    }, pollInterval)
  }, [fetchTrade, pollInterval])

  // Stop polling
  const stopPolling = useCallback(() => {
    if (pollRef.current) {
      clearInterval(pollRef.current)
      pollRef.current = null
    }
  }, [])

  useEffect(() => {
    if (!enabled || !tradeHash) return

    let destroyed = false

    async function connectEcho() {
      try {
        // Check if Reverb/Echo env vars are configured via Vite
        const reverbHost = import.meta.env.VITE_REVERB_HOST
        const reverbPort = import.meta.env.VITE_REVERB_PORT
        const reverbKey = import.meta.env.VITE_REVERB_APP_KEY
        const reverbScheme = import.meta.env.VITE_REVERB_SCHEME

        if (!reverbKey || !reverbHost) {
          // Echo not configured, fall back to polling
          if (!destroyed) startPolling()
          return
        }

        // Dynamically import Echo and Pusher only when needed
        const [{ default: Echo }, { default: Pusher }] = await Promise.all([
          import("laravel-echo"),
          import("pusher-js"),
        ])

        if (destroyed) return

        const echoInstance = new Echo({
          broadcaster: "reverb",
          key: reverbKey,
          wsHost: reverbHost,
          wsPort: reverbPort || 8080,
          wssPort: reverbPort || 443,
          forceTLS: (reverbScheme || "https") === "https",
          enabledTransports: ["ws", "wss"],
          disableStats: true,
        })

        echoRef.current = echoInstance

        const channel = echoInstance.channel(`trade.${tradeHash}`)
        channelRef.current = channel

        // Listen for all trade events
        const tradeEvents = [
          ".App\\Events\\TradeInitiated",
          ".App\\Events\\PaymentMarked",
          ".App\\Events\\TradeCompleted",
          ".App\\Events\\TradeCancelled",
          ".App\\Events\\BankProofUploaded",
          ".App\\Events\\BuyerIdSubmitted",
          ".App\\Events\\DisputeOpened",
        ]

        tradeEvents.forEach((eventName) => {
          channel.listen(eventName, () => {
            // When any trade event fires, re-fetch the full trade data
            fetchTrade().catch(() => {})
          })
        })

        if (!destroyed) {
          setConnectionType("echo")
          setIsConnected(true)

          // Safety: if Echo connects but no events fire within 15s, also start polling
          setTimeout(() => {
            if (!destroyed && !pollRef.current) startPolling()
          }, 15000)
        }
      } catch {
        // Echo connection failed, fall back to polling
        if (!destroyed) startPolling()
      }
    }

    // Always start polling immediately as baseline, Echo upgrades when ready
    if (!destroyed) startPolling()
    connectEcho()

    return () => {
      destroyed = true

      // Clean up Echo channel
      if (channelRef.current && echoRef.current) {
        echoRef.current.leave(`trade.${tradeHash}`)
        channelRef.current = null
      }
      if (echoRef.current) {
        echoRef.current.disconnect()
        echoRef.current = null
      }

      // Clean up polling
      stopPolling()

      setIsConnected(false)
      setConnectionType(null)
    }
  }, [tradeHash, enabled, startPolling, stopPolling, fetchTrade])

  return { isConnected, connectionType }
}
