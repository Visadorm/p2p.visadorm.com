/**
 * API client for Visadorm P2P backend.
 * All authenticated requests include the Sanctum Bearer token.
 */

const API_BASE = '/api';

function getToken() {
    return localStorage.getItem('auth_token');
}

function headers(extra = {}) {
    const h = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        ...extra,
    };
    const token = getToken();
    if (token) {
        h['Authorization'] = `Bearer ${token}`;
    }
    return h;
}

function fileHeaders() {
    const h = { 'Accept': 'application/json' };
    const token = getToken();
    if (token) {
        h['Authorization'] = `Bearer ${token}`;
    }
    return h;
}

async function handleResponse(res) {
    const data = await res.json();
    if (!res.ok) {
        // Auto-logout on 401 (expired/invalid token)
        if (res.status === 401) {
            localStorage.removeItem('auth_token');
            localStorage.removeItem('wallet_connected');
            localStorage.removeItem('phrase_wallet_encrypted');
            window.location.href = '/connect';
            return;
        }
        throw { status: res.status, message: data.message || 'Request failed', errors: data.errors };
    }
    return data;
}

export const api = {
    // Auth
    getNonce: (walletAddress) =>
        fetch(`${API_BASE}/auth/nonce`, {
            method: 'POST',
            headers: headers(),
            body: JSON.stringify({ wallet_address: walletAddress }),
        }).then(handleResponse),

    verify: (walletAddress, signature, nonce) =>
        fetch(`${API_BASE}/auth/verify`, {
            method: 'POST',
            headers: headers(),
            body: JSON.stringify({ wallet_address: walletAddress, signature, nonce }),
        }).then(handleResponse),

    logout: () =>
        fetch(`${API_BASE}/auth/logout`, {
            method: 'POST',
            headers: headers(),
        }).then(handleResponse),

    me: () =>
        fetch(`${API_BASE}/auth/me`, {
            method: 'GET',
            headers: headers(),
        }).then(handleResponse),

    // Exchange Rates
    getExchangeRates: () =>
        fetch(`${API_BASE}/exchange-rates`, { headers: headers() }).then(handleResponse),

    // Countries
    getCountries: () =>
        fetch(`${API_BASE}/p2p-countries`, { headers: headers() }).then(handleResponse),

    // Merchant
    getDashboard: () =>
        fetch(`${API_BASE}/merchant/dashboard`, { headers: headers() }).then(handleResponse),

    updateProfile: (data) =>
        fetch(`${API_BASE}/merchant/profile`, {
            method: 'PUT',
            headers: headers(),
            body: JSON.stringify(data),
        }).then(handleResponse),

    getMerchantProfile: (username) =>
        fetch(`${API_BASE}/merchant/${username}/profile`, { headers: headers() }).then(handleResponse),

    uploadAvatar: (file) => {
        const form = new FormData()
        form.append('avatar', file)
        return fetch(`${API_BASE}/merchant/avatar`, {
            method: 'POST',
            headers: fileHeaders(),
            body: form,
        }).then(handleResponse)
    },

    // Trading Links
    getTradingLinks: () =>
        fetch(`${API_BASE}/merchant/trading-links`, { headers: headers() }).then(handleResponse),

    createTradingLink: (data) =>
        fetch(`${API_BASE}/merchant/trading-links`, {
            method: 'POST',
            headers: headers(),
            body: JSON.stringify(data),
        }).then(handleResponse),

    updateTradingLink: (id, data) =>
        fetch(`${API_BASE}/merchant/trading-links/${id}`, {
            method: 'PUT',
            headers: headers(),
            body: JSON.stringify(data),
        }).then(handleResponse),

    deleteTradingLink: (id) =>
        fetch(`${API_BASE}/merchant/trading-links/${id}`, {
            method: 'DELETE',
            headers: headers(),
        }).then(handleResponse),

    // Payment Methods
    getPaymentMethods: () =>
        fetch(`${API_BASE}/merchant/payment-methods`, { headers: headers() }).then(handleResponse),

    createPaymentMethod: (data) =>
        fetch(`${API_BASE}/merchant/payment-methods`, {
            method: 'POST',
            headers: headers(),
            body: JSON.stringify(data),
        }).then(handleResponse),

    updatePaymentMethod: (id, data) =>
        fetch(`${API_BASE}/merchant/payment-methods/${id}`, {
            method: 'PUT',
            headers: headers(),
            body: JSON.stringify(data),
        }).then(handleResponse),

    deletePaymentMethod: (id) =>
        fetch(`${API_BASE}/merchant/payment-methods/${id}`, {
            method: 'DELETE',
            headers: headers(),
        }).then(handleResponse),

    // Currencies
    getCurrencies: () =>
        fetch(`${API_BASE}/merchant/currencies`, { headers: headers() }).then(handleResponse),

    createCurrency: (data) =>
        fetch(`${API_BASE}/merchant/currencies`, {
            method: 'POST',
            headers: headers(),
            body: JSON.stringify(data),
        }).then(handleResponse),

    updateCurrency: (id, data) =>
        fetch(`${API_BASE}/merchant/currencies/${id}`, {
            method: 'PUT',
            headers: headers(),
            body: JSON.stringify(data),
        }).then(handleResponse),

    deleteCurrency: (id) =>
        fetch(`${API_BASE}/merchant/currencies/${id}`, {
            method: 'DELETE',
            headers: headers(),
        }).then(handleResponse),

    // Trades
    getTradingLinkDetails: (slug) =>
        fetch(`${API_BASE}/trade/${slug}`, { headers: headers() }).then(handleResponse),

    initiateTrade: (slug, data) =>
        fetch(`${API_BASE}/trade/${slug}/initiate`, {
            method: 'POST',
            headers: headers(),
            body: JSON.stringify(data),
        }).then(handleResponse),

    getTradeStatus: (hash) =>
        fetch(`${API_BASE}/trade/${hash}/status`, { headers: headers() }).then(handleResponse),

    markPaid: (hash) =>
        fetch(`${API_BASE}/trade/${hash}/paid`, {
            method: 'POST',
            headers: headers(),
        }).then(handleResponse),

    uploadBankProof: (hash, file) => {
        const formData = new FormData();
        formData.append('bank_proof', file);
        return fetch(`${API_BASE}/trade/${hash}/bank-proof`, {
            method: 'POST',
            headers: fileHeaders(),
            body: formData,
        }).then(handleResponse);
    },

    uploadBuyerId: (hash, file) => {
        const formData = new FormData();
        formData.append('buyer_id', file);
        return fetch(`${API_BASE}/trade/${hash}/buyer-id`, {
            method: 'POST',
            headers: fileHeaders(),
            body: formData,
        }).then(handleResponse);
    },

    cancelTrade: (hash) =>
        fetch(`${API_BASE}/trade/${hash}/cancel`, {
            method: 'POST',
            headers: headers(),
        }).then(handleResponse),

    confirmTrade: (hash) =>
        fetch(`${API_BASE}/merchant/trades/${hash}/confirm`, {
            method: 'POST',
            headers: headers(),
        }).then(handleResponse),

    getMerchantTrades: (params = '') =>
        fetch(`${API_BASE}/merchant/trades${params ? '?' + params : ''}`, { headers: headers() }).then(handleResponse),

    getMerchantTradeDetail: (hash) =>
        fetch(`${API_BASE}/merchant/trades/${hash}`, { headers: headers() }).then(handleResponse),

    downloadBankProof: (hash) =>
        fetch(`${API_BASE}/merchant/trades/${hash}/bank-proof`, { headers: fileHeaders() }),

    downloadBuyerId: (hash) =>
        fetch(`${API_BASE}/merchant/trades/${hash}/buyer-id`, { headers: fileHeaders() }),

    // Reviews
    createReview: (hash, data) =>
        fetch(`${API_BASE}/trade/${hash}/review`, {
            method: 'POST',
            headers: headers(),
            body: JSON.stringify(data),
        }).then(handleResponse),

    // KYC
    getKycDocuments: () =>
        fetch(`${API_BASE}/merchant/kyc`, { headers: headers() }).then(handleResponse),

    // A8: identity profile read + submit. Locked once submitted.
    getKycProfile: () =>
        fetch(`${API_BASE}/merchant/kyc/profile`, { headers: headers() }).then(handleResponse),

    submitKycProfile: (data) =>
        fetch(`${API_BASE}/merchant/kyc/profile`, {
            method: 'POST',
            headers: headers(),
            body: JSON.stringify(data),
        }).then(handleResponse),

    uploadKycDocument: (type, file) => {
        const formData = new FormData();
        formData.append('type', type);
        formData.append('file', file);
        return fetch(`${API_BASE}/merchant/kyc/upload`, {
            method: 'POST',
            headers: fileHeaders(),
            body: formData,
        }).then(handleResponse);
    },

    deleteKycDocument: (id) =>
        fetch(`${API_BASE}/merchant/kyc/${id}`, {
            method: 'DELETE',
            headers: headers(),
        }).then(handleResponse),

    // Escrow
    depositEscrow: (amount) =>
        fetch(`${API_BASE}/merchant/escrow/deposit`, {
            method: 'POST',
            headers: headers(),
            body: JSON.stringify({ amount }),
        }).then(handleResponse),

    withdrawEscrow: (amount) =>
        fetch(`${API_BASE}/merchant/escrow/withdraw`, {
            method: 'POST',
            headers: headers(),
            body: JSON.stringify({ amount }),
        }).then(handleResponse),

    getEscrowTxStatus: (hash) =>
        fetch(`${API_BASE}/merchant/escrow/tx/${hash}`, {
            headers: headers(),
        }).then(handleResponse),

    // Disputes
    openDispute: (hash, reason) =>
        fetch(`${API_BASE}/trade/${hash}/dispute`, {
            method: 'POST',
            headers: headers(),
            body: JSON.stringify({ reason }),
        }).then(handleResponse),

    getDispute: (tradeHash) =>
        fetch(`${API_BASE}/trade/${tradeHash}/dispute`, { headers: headers() }).then(handleResponse),

    uploadDisputeEvidence: (tradeHash, file, note) => {
        const formData = new FormData();
        formData.append('file', file);
        if (note) formData.append('note', note);
        return fetch(`${API_BASE}/trade/${tradeHash}/dispute/evidence`, {
            method: 'POST',
            headers: fileHeaders(),
            body: formData,
        }).then(handleResponse);
    },

    // Sell Trades — non-custodial flow. All on-chain actions broadcast from
    // user wallet; backend records tx hashes after RPC verification.
    getActiveSellTrade: () =>
        fetch(`${API_BASE}/sell-trades/active`, { headers: headers() }).then(handleResponse),

    openSellTrade: (body) =>
        fetch(`${API_BASE}/sell-trades`, {
            method: 'POST',
            headers: headers(),
            body: JSON.stringify(body),
        }).then(handleResponse),

    confirmSellFund: (tradeHash, body) =>
        fetch(`${API_BASE}/sell-trades/${tradeHash}/confirm-fund`, {
            method: 'POST',
            headers: headers(),
            body: JSON.stringify(body),
        }).then(handleResponse),

    getSellTrade: (tradeHash) =>
        fetch(`${API_BASE}/sell-trades/${tradeHash}`, { headers: headers() }).then(handleResponse),

    confirmSellJoin: (tradeHash, body) =>
        fetch(`${API_BASE}/sell-trades/${tradeHash}/confirm-join`, {
            method: 'POST',
            headers: headers(),
            body: JSON.stringify(body),
        }).then(handleResponse),

    confirmSellMarkPaid: (tradeHash, body) =>
        fetch(`${API_BASE}/sell-trades/${tradeHash}/confirm-mark-paid`, {
            method: 'POST',
            headers: headers(),
            body: JSON.stringify(body),
        }).then(handleResponse),

    uploadSellCashProof: (tradeHash, file, note) => {
        const formData = new FormData();
        formData.append('proof', file);
        if (note) formData.append('note', note);
        return fetch(`${API_BASE}/sell-trades/${tradeHash}/cash-proof`, {
            method: 'POST',
            headers: fileHeaders(),
            body: formData,
        }).then(handleResponse);
    },

    // A4: buyer uploads fiat payment proof (image/PDF, ≤5MB).
    uploadSellPaymentProof: (tradeHash, file) => {
        const formData = new FormData();
        formData.append('proof', file);
        return fetch(`${API_BASE}/sell-trades/${tradeHash}/payment-proof`, {
            method: 'POST',
            headers: fileHeaders(),
            body: formData,
        }).then(handleResponse);
    },

    // A4: fetch the auth-protected payment proof file.
    downloadSellPaymentProof: (tradeHash) =>
        fetch(`${API_BASE}/sell-trades/${tradeHash}/payment-proof`, { headers: fileHeaders() }),

    // A7: fetch the auth-protected cash proof file (in-person trades).
    downloadSellCashProof: (tradeHash) =>
        fetch(`${API_BASE}/sell-trades/${tradeHash}/cash-proof`, { headers: fileHeaders() }),

    // B1: user-signed buy flow. Body shape:
    //   - {} → returns { escrow_address, calldata } for wallet to broadcast
    //   - { tx_hash } → records broadcast result + advances DB state
    userSignedMarkPaid: (tradeHash, body = {}) =>
        fetch(`${API_BASE}/trade/${tradeHash}/user-signed/mark-paid`, {
            method: 'POST',
            headers: headers(),
            body: JSON.stringify(body),
        }).then(handleResponse),

    userSignedConfirm: (tradeHash, body = {}) =>
        fetch(`${API_BASE}/trade/${tradeHash}/user-signed/confirm`, {
            method: 'POST',
            headers: headers(),
            body: JSON.stringify(body),
        }).then(handleResponse),

    userSignedCancel: (tradeHash, body = {}) =>
        fetch(`${API_BASE}/trade/${tradeHash}/user-signed/cancel`, {
            method: 'POST',
            headers: headers(),
            body: JSON.stringify(body),
        }).then(handleResponse),

    // B1 high-level helpers: bundle "fetch calldata → wallet sign → record tx".
    // Caller passes a signer (ethers v6 Signer). Use these from JSX components.
    runUserSignedAction: async (action, tradeHash, signer) => {
        const apiFn = {
            'mark-paid': fetch.bind(null, `${API_BASE}/trade/${tradeHash}/user-signed/mark-paid`),
            'confirm':   fetch.bind(null, `${API_BASE}/trade/${tradeHash}/user-signed/confirm`),
            'cancel':    fetch.bind(null, `${API_BASE}/trade/${tradeHash}/user-signed/cancel`),
        }[action]
        if (!apiFn) throw new Error(`Unknown user-signed action: ${action}`)

        const init = (body) => ({ method: 'POST', headers: headers(), body: JSON.stringify(body) })

        // 1. Get calldata
        const payloadRes = await apiFn(init({})).then(handleResponse)
        const { escrow_address, calldata } = payloadRes.data

        // 2. User signs + broadcasts
        const tx = await signer.sendTransaction({ to: escrow_address, data: calldata, gasLimit: 500000 })
        const receipt = await tx.wait()
        if (receipt.status !== 1) throw new Error('Transaction reverted')

        // 3. Record on backend
        const recordRes = await apiFn(init({ tx_hash: tx.hash })).then(handleResponse)
        return { tx_hash: tx.hash, ...recordRes }
    },

    // A5: trade chat — list, send (text or text+image), download attachment.
    listSellTradeMessages: (tradeHash) =>
        fetch(`${API_BASE}/sell-trades/${tradeHash}/messages`, { headers: headers() }).then(handleResponse),

    sendSellTradeMessage: (tradeHash, { body, attachment }) => {
        const formData = new FormData()
        if (body) formData.append('body', body)
        if (attachment) formData.append('attachment', attachment)
        return fetch(`${API_BASE}/sell-trades/${tradeHash}/messages`, {
            method: 'POST',
            headers: fileHeaders(),
            body: formData,
        }).then(handleResponse)
    },

    downloadSellTradeMessageAttachment: (tradeHash, messageId) =>
        fetch(`${API_BASE}/sell-trades/${tradeHash}/messages/${messageId}/attachment`, { headers: fileHeaders() }),

    setSellVerifyPayment: (tradeHash, verified) =>
        fetch(`${API_BASE}/sell-trades/${tradeHash}/verify-payment`, {
            method: 'POST',
            headers: headers(),
            body: JSON.stringify({ verified }),
        }).then(handleResponse),

    confirmSellRelease: (tradeHash, body) =>
        fetch(`${API_BASE}/sell-trades/${tradeHash}/confirm-release`, {
            method: 'POST',
            headers: headers(),
            body: JSON.stringify(body),
        }).then(handleResponse),

    openSellTradeDispute: (tradeHash, body) =>
        fetch(`${API_BASE}/sell-trades/${tradeHash}/dispute`, {
            method: 'POST',
            headers: headers(),
            body: JSON.stringify(body),
        }).then(handleResponse),

    cancelSellTrade: (tradeHash, body) =>
        fetch(`${API_BASE}/sell-trades/${tradeHash}/cancel`, {
            method: 'POST',
            headers: headers(),
            body: JSON.stringify(body),
        }).then(handleResponse),

    // Notifications
    getNotifications: (page = 1) =>
        fetch(`${API_BASE}/notifications?page=${page}`, { headers: headers() }).then(handleResponse),

    markNotificationRead: (id) =>
        fetch(`${API_BASE}/notifications/${id}/read`, {
            method: 'POST',
            headers: headers(),
        }).then(handleResponse),

    markAllNotificationsRead: () =>
        fetch(`${API_BASE}/notifications/read-all`, {
            method: 'POST',
            headers: headers(),
        }).then(handleResponse),

    getUnreadCount: () =>
        fetch(`${API_BASE}/notifications/unread-count`, { headers: headers() }).then(handleResponse),
};
