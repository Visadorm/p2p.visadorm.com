/**
 * Client-side AES-256-GCM wallet encryption using Web Crypto API.
 * Private keys are encrypted with a user password — never sent to the server.
 * Format: base64(salt[16] + iv[12] + ciphertext)
 * Key derivation: PBKDF2-SHA256, 100,000 iterations
 */

export async function encryptWalletData(plaintext, password) {
  const encoder = new TextEncoder()
  const salt = crypto.getRandomValues(new Uint8Array(16))
  const iv = crypto.getRandomValues(new Uint8Array(12))

  const baseKey = await crypto.subtle.importKey(
    "raw",
    encoder.encode(password),
    "PBKDF2",
    false,
    ["deriveKey"],
  )

  const key = await crypto.subtle.deriveKey(
    { name: "PBKDF2", salt, iterations: 100000, hash: "SHA-256" },
    baseKey,
    { name: "AES-GCM", length: 256 },
    false,
    ["encrypt"],
  )

  const encrypted = await crypto.subtle.encrypt(
    { name: "AES-GCM", iv },
    key,
    encoder.encode(plaintext),
  )

  const combined = new Uint8Array(16 + 12 + encrypted.byteLength)
  combined.set(salt, 0)
  combined.set(iv, 16)
  combined.set(new Uint8Array(encrypted), 28)

  return btoa(String.fromCharCode(...combined))
}

export async function decryptWalletData(encryptedBlob, password) {
  const encoder = new TextEncoder()
  const combined = Uint8Array.from(atob(encryptedBlob), (c) => c.charCodeAt(0))

  const salt = combined.slice(0, 16)
  const iv = combined.slice(16, 28)
  const ciphertext = combined.slice(28)

  const baseKey = await crypto.subtle.importKey(
    "raw",
    encoder.encode(password),
    "PBKDF2",
    false,
    ["deriveKey"],
  )

  const key = await crypto.subtle.deriveKey(
    { name: "PBKDF2", salt, iterations: 100000, hash: "SHA-256" },
    baseKey,
    { name: "AES-GCM", length: 256 },
    false,
    ["decrypt"],
  )

  const decrypted = await crypto.subtle.decrypt({ name: "AES-GCM", iv }, key, ciphertext)

  return new TextDecoder().decode(decrypted)
}
