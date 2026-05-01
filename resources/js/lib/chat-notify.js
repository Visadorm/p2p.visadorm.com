let audioCtx = null

function getCtx() {
  if (!audioCtx) {
    const Ctx = window.AudioContext || window.webkitAudioContext
    if (!Ctx) return null
    audioCtx = new Ctx()
  }
  if (audioCtx.state === "suspended") audioCtx.resume()
  return audioCtx
}

export function playChatChime() {
  try {
    const ctx = getCtx()
    if (!ctx) return
    const now = ctx.currentTime

    const playTone = (freq, startOffset, duration) => {
      const osc = ctx.createOscillator()
      const gain = ctx.createGain()
      osc.type = "sine"
      osc.frequency.setValueAtTime(freq, now + startOffset)
      gain.gain.setValueAtTime(0, now + startOffset)
      gain.gain.linearRampToValueAtTime(0.18, now + startOffset + 0.01)
      gain.gain.exponentialRampToValueAtTime(0.001, now + startOffset + duration)
      osc.connect(gain)
      gain.connect(ctx.destination)
      osc.start(now + startOffset)
      osc.stop(now + startOffset + duration + 0.05)
    }

    playTone(880, 0, 0.18)
    playTone(1320, 0.12, 0.22)
  } catch {
    // ignore — autoplay restrictions or unsupported browser
  }
}

export function flashTabTitle(text, originalTitle, intervalMs = 1200) {
  let on = false
  const id = setInterval(() => {
    document.title = on ? originalTitle : text
    on = !on
  }, intervalMs)

  const restore = () => {
    clearInterval(id)
    document.title = originalTitle
  }

  const onVisible = () => {
    if (document.visibilityState === "visible") {
      restore()
      document.removeEventListener("visibilitychange", onVisible)
    }
  }
  document.addEventListener("visibilitychange", onVisible)
  return restore
}

const MUTE_KEY = "chat_sound_muted"

export function isChatMuted() {
  try { return localStorage.getItem(MUTE_KEY) === "1" } catch { return false }
}

export function setChatMuted(muted) {
  try { localStorage.setItem(MUTE_KEY, muted ? "1" : "0") } catch {}
}
