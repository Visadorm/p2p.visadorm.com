import { useState, useEffect, useCallback } from "react"
import { useWallet } from "@/hooks/useWallet"
import { api } from "@/lib/api"

export function useNotifications() {
  const { isAuthenticated } = useWallet()
  const [unreadCount, setUnreadCount] = useState(0)
  const [notifications, setNotifications] = useState([])
  const [isLoading, setIsLoading] = useState(false)

  const fetchUnreadCount = useCallback(async () => {
    if (!isAuthenticated) return
    try {
      const res = await api.getUnreadCount()
      setUnreadCount(res.data?.unread_count ?? res.data?.count ?? 0)
    } catch {
      // silent
    }
  }, [isAuthenticated])

  const fetchNotifications = useCallback(async () => {
    if (!isAuthenticated) return
    try {
      setIsLoading(true)
      const res = await api.getNotifications(1)
      const items = res.data?.data || res.data || []
      setNotifications(Array.isArray(items) ? items.slice(0, 8) : [])
    } catch {
      // silent
    } finally {
      setIsLoading(false)
    }
  }, [isAuthenticated])

  // Poll unread count every 30s
  useEffect(() => {
    fetchUnreadCount()
    const interval = setInterval(fetchUnreadCount, 30000)
    return () => clearInterval(interval)
  }, [fetchUnreadCount])

  const markRead = useCallback(async (notificationId) => {
    try {
      await api.markNotificationRead(notificationId)
      setUnreadCount((c) => Math.max(0, c - 1))
      setNotifications((prev) =>
        prev.map((n) => (n.id === notificationId ? { ...n, is_read: true } : n))
      )
    } catch {
      // silent
    }
  }, [])

  const markAllRead = useCallback(async () => {
    try {
      await api.markAllNotificationsRead()
      setUnreadCount(0)
      setNotifications((prev) => prev.map((n) => ({ ...n, is_read: true })))
    } catch {
      // silent
    }
  }, [])

  return {
    notifications,
    unreadCount,
    markRead,
    markAllRead,
    isLoading,
    fetchNotifications,
  }
}
