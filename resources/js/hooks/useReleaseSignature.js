import { useCallback, useState } from "react"
import { api } from "@/lib/api"
import { useWallet } from "@/hooks/useWallet"

export function useReleaseSignature(tradeHash) {
  const { signTypedData } = useWallet()
  const [payload, setPayload] = useState(null)
  const [signing, setSigning] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState(null)
  const [released, setReleased] = useState(null)

  const fetchPayload = useCallback(async () => {
    setError(null)
    try {
      const res = await api.getSellReleasePayload(tradeHash)
      setPayload(res.data)
      return res.data
    } catch (err) {
      setError(err?.message || "Failed to fetch release payload")
      throw err
    }
  }, [tradeHash])

  const signAndRelease = useCallback(async () => {
    setError(null)
    let payloadToSign = payload
    if (!payloadToSign) {
      payloadToSign = await fetchPayload()
    }

    setSigning(true)
    let signature
    try {
      signature = await signTypedData({
        domain: payloadToSign.domain,
        types: payloadToSign.types,
        message: payloadToSign.message,
      })
      if (!signature) throw new Error("Wallet did not return a signature")
    } catch (err) {
      setSigning(false)
      setError(err?.message || "Signing rejected")
      throw err
    }
    setSigning(false)

    setSubmitting(true)
    try {
      const res = await api.submitSellRelease(tradeHash, {
        signature,
        nonce: payloadToSign.message.nonce,
        deadline: payloadToSign.message.deadline,
      })
      setReleased(res.data)
      return res.data
    } catch (err) {
      setError(err?.message || "Release submission failed")
      throw err
    } finally {
      setSubmitting(false)
    }
  }, [payload, fetchPayload, signTypedData, tradeHash])

  return { payload, fetchPayload, signAndRelease, signing, submitting, released, error }
}
