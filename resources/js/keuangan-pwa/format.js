export function formatRupiah(value) {
    if (value == null) return 'Rp 0'
    const num = typeof value === 'string' ? parseFloat(value) : value
    if (Number.isNaN(num)) return 'Rp 0'

    const abs = Math.abs(num)
    if (abs >= 1_000_000_000_000) {
        return `${num < 0 ? '−' : ''}Rp ${(abs / 1_000_000_000_000).toFixed(1).replace('.0', '')} T`
    }

    return formatRupiahFull(num)
}

export function formatRupiahFull(value) {
    if (value == null) return 'Rp 0'
    const num = typeof value === 'string' ? parseFloat(value) : value
    if (Number.isNaN(num)) return 'Rp 0'
    return `Rp ${new Intl.NumberFormat('id-ID').format(num)}`
}

export function formatTanggal(dateStr) {
    if (!dateStr) return '-'
    const d = new Date(dateStr)
    return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

export function formatWaktu(dateStr) {
    if (!dateStr) return '-'
    const d = new Date(dateStr)
    return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
}
