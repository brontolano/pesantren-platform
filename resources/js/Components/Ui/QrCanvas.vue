<script setup>
import { computed } from 'vue'

/**
 * QrCanvas — generator QR Code mandiri (tanpa dependency / aset eksternal).
 * Render payload (mis. string QRIS) menjadi grid SVG hitam-putih.
 * Implementasi encoder QR ringkas (byte mode, error correction level M),
 * cukup untuk payload pendek seperti QRIS (≈94 karakter).
 *
 * Props:
 * - value: string payload yang akan di-encode
 * - size: ukuran piksel sisi QR (default 220)
 *
 * Bila encoding gagal (payload terlalu panjang dll), emit @error agar induk
 * bisa fallback ke gambar (qrImageUrl).
 */
const props = defineProps({
    value: { type: String, required: true },
    size: { type: [Number, String], default: 220 },
})
const emit = defineEmits(['error'])

/* ───────────────────────── QR encoder (ringkas) ───────────────────────── */
// Galois Field GF(256) untuk Reed-Solomon.
const EXP = new Array(512)
const LOG = new Array(256)
;(function initGF() {
    let x = 1
    for (let i = 0; i < 255; i++) {
        EXP[i] = x
        LOG[x] = i
        x <<= 1
        if (x & 0x100) x ^= 0x11d
    }
    for (let i = 255; i < 512; i++) EXP[i] = EXP[i - 255]
})()

function gfMul(a, b) {
    if (a === 0 || b === 0) return 0
    return EXP[LOG[a] + LOG[b]]
}

function rsGenerator(degree) {
    let poly = [1]
    for (let i = 0; i < degree; i++) {
        const next = new Array(poly.length + 1).fill(0)
        for (let j = 0; j < poly.length; j++) {
            next[j] ^= gfMul(poly[j], EXP[i])
            next[j + 1] ^= poly[j]
        }
        poly = next
    }
    return poly
}

function rsEncode(data, ecLen) {
    const gen = rsGenerator(ecLen)
    const res = new Array(ecLen).fill(0)
    for (let i = 0; i < data.length; i++) {
        const factor = data[i] ^ res[0]
        res.shift()
        res.push(0)
        for (let j = 0; j < gen.length; j++) {
            res[j] ^= gfMul(gen[j], factor)
        }
    }
    return res
}

// Tabel kapasitas + parameter EC level M untuk versi 1..10 (cukup utk QRIS).
// [version, totalCodewords, ecPerBlock, group1Blocks, group1DataCw, group2Blocks, group2DataCw]
const VERSIONS_M = [
    [1, 26, 10, 1, 16, 0, 0],
    [2, 44, 16, 1, 28, 0, 0],
    [3, 70, 26, 1, 44, 0, 0],
    [4, 100, 18, 2, 32, 0, 0],
    [5, 134, 24, 2, 43, 0, 0],
    [6, 172, 16, 4, 27, 0, 0],
    [7, 196, 18, 4, 31, 0, 0],
    [8, 242, 22, 2, 38, 2, 39],
    [9, 292, 22, 3, 36, 2, 37],
    [10, 346, 26, 4, 43, 1, 44],
]

const ALIGN_POS = {
    1: [], 2: [6, 18], 3: [6, 22], 4: [6, 26], 5: [6, 30],
    6: [6, 34], 7: [6, 22, 38], 8: [6, 24, 42], 9: [6, 26, 46], 10: [6, 28, 50],
}

function bitsToBytes(bits) {
    const bytes = []
    for (let i = 0; i < bits.length; i += 8) {
        let b = 0
        for (let j = 0; j < 8; j++) b = (b << 1) | (bits[i + j] ? 1 : 0)
        bytes.push(b)
    }
    return bytes
}

function pushBits(arr, value, len) {
    for (let i = len - 1; i >= 0; i--) arr.push((value >> i) & 1)
}

// Format info (EC level M = 0b00) + mask pattern, dengan BCH.
function formatInfoBits(mask) {
    const data = (0b00 << 3) | mask // M -> 0b00
    let v = data << 10
    const g = 0b10100110111
    for (let i = 14; i >= 10; i--) {
        if ((v >> i) & 1) v ^= g << (i - 10)
    }
    let bits = ((data << 10) | v) ^ 0b101010000010010
    const out = []
    for (let i = 14; i >= 0; i--) out.push((bits >> i) & 1)
    return out
}

function buildMatrix(version, codewords) {
    const size = version * 4 + 17
    const m = Array.from({ length: size }, () => new Array(size).fill(null))
    const reserved = Array.from({ length: size }, () => new Array(size).fill(false))

    function placeFinder(r, c) {
        for (let i = -1; i <= 7; i++) {
            for (let j = -1; j <= 7; j++) {
                const rr = r + i
                const cc = c + j
                if (rr < 0 || rr >= size || cc < 0 || cc >= size) continue
                const inRing =
                    i >= 0 && i <= 6 && j >= 0 && j <= 6 &&
                    (i === 0 || i === 6 || j === 0 || j === 6 ||
                        (i >= 2 && i <= 4 && j >= 2 && j <= 4))
                m[rr][cc] = inRing ? 1 : 0
                reserved[rr][cc] = true
            }
        }
    }
    placeFinder(0, 0)
    placeFinder(0, size - 7)
    placeFinder(size - 7, 0)

    // Timing patterns
    for (let i = 8; i < size - 8; i++) {
        const v = i % 2 === 0 ? 1 : 0
        if (m[6][i] === null) { m[6][i] = v; reserved[6][i] = true }
        if (m[i][6] === null) { m[i][6] = v; reserved[i][6] = true }
    }

    // Alignment patterns
    const positions = ALIGN_POS[version] || []
    for (const r of positions) {
        for (const c of positions) {
            if (reserved[r][c]) continue
            for (let i = -2; i <= 2; i++) {
                for (let j = -2; j <= 2; j++) {
                    const ring = Math.max(Math.abs(i), Math.abs(j))
                    m[r + i][c + j] = ring === 1 ? 0 : 1
                    reserved[r + i][c + j] = true
                }
            }
        }
    }

    // Dark module
    m[size - 8][8] = 1
    reserved[size - 8][8] = true

    // Reserve format info areas
    for (let i = 0; i <= 8; i++) {
        if (i !== 6) { reserved[8][i] = true; reserved[i][8] = true }
    }
    for (let i = 0; i < 8; i++) {
        reserved[8][size - 1 - i] = true
        reserved[size - 1 - i][8] = true
    }
    reserved[8][8] = true

    // Place data codewords (zigzag, bottom-up)
    let bitIndex = 0
    const dataBits = []
    for (const cw of codewords) for (let b = 7; b >= 0; b--) dataBits.push((cw >> b) & 1)

    let col = size - 1
    let upward = true
    while (col > 0) {
        if (col === 6) col-- // skip timing column
        for (let i = 0; i < size; i++) {
            const row = upward ? size - 1 - i : i
            for (let c = 0; c < 2; c++) {
                const cc = col - c
                if (!reserved[row][cc] && m[row][cc] === null) {
                    m[row][cc] = bitIndex < dataBits.length ? dataBits[bitIndex] : 0
                    bitIndex++
                }
            }
        }
        col -= 2
        upward = !upward
    }

    return { m, reserved, size }
}

function applyMask(m, reserved, size, mask) {
    const out = m.map((row) => row.slice())
    for (let r = 0; r < size; r++) {
        for (let c = 0; c < size; c++) {
            if (reserved[r][c]) continue
            let flip = false
            switch (mask) {
                case 0: flip = (r + c) % 2 === 0; break
                case 1: flip = r % 2 === 0; break
                case 2: flip = c % 3 === 0; break
                case 3: flip = (r + c) % 3 === 0; break
                case 4: flip = (Math.floor(r / 2) + Math.floor(c / 3)) % 2 === 0; break
                case 5: flip = ((r * c) % 2) + ((r * c) % 3) === 0; break
                case 6: flip = (((r * c) % 2) + ((r * c) % 3)) % 2 === 0; break
                case 7: flip = (((r + c) % 2) + ((r * c) % 3)) % 2 === 0; break
            }
            if (flip) out[r][c] ^= 1
        }
    }
    return out
}

function placeFormat(m, size, mask) {
    const fmt = formatInfoBits(mask)
    // Around top-left
    let idx = 0
    for (let i = 0; i <= 5; i++) m[8][i] = fmt[idx++]
    m[8][7] = fmt[idx++]
    m[8][8] = fmt[idx++]
    m[7][8] = fmt[idx++]
    for (let i = 5; i >= 0; i--) m[i][8] = fmt[idx++]
    // Around top-right & bottom-left (second copy)
    idx = 0
    for (let i = 0; i <= 7; i++) m[8][size - 1 - i] = fmt[idx++]
    for (let i = 0; i <= 6; i++) m[size - 7 + i][8] = fmt[idx++]
}

function scorePenalty(m, size) {
    let penalty = 0
    // Rule 1: runs of 5+
    for (let r = 0; r < size; r++) {
        let run = 1
        for (let c = 1; c < size; c++) {
            if (m[r][c] === m[r][c - 1]) { run++; if (run === 5) penalty += 3; else if (run > 5) penalty++ }
            else run = 1
        }
    }
    for (let c = 0; c < size; c++) {
        let run = 1
        for (let r = 1; r < size; r++) {
            if (m[r][c] === m[r - 1][c]) { run++; if (run === 5) penalty += 3; else if (run > 5) penalty++ }
            else run = 1
        }
    }
    // Rule 3: dark module ratio (cheap approximation)
    let dark = 0
    for (let r = 0; r < size; r++) for (let c = 0; c < size; c++) if (m[r][c]) dark++
    const ratio = (dark / (size * size)) * 100
    penalty += Math.floor(Math.abs(ratio - 50) / 5) * 10
    return penalty
}

function encode(text) {
    const enc = new TextEncoder()
    const bytes = Array.from(enc.encode(text))

    // Pick smallest version that fits (level M).
    let chosen = null
    for (const ver of VERSIONS_M) {
        const [, , ecLen, g1, g1cw, g2, g2cw] = ver
        const totalDataCw = g1 * g1cw + g2 * g2cw
        // header: mode(4) + count(8 for v1-9 byte mode) + data*8 + terminator
        const countBits = 8
        const headerBits = 4 + countBits
        const neededBits = headerBits + bytes.length * 8
        if (neededBits <= totalDataCw * 8) {
            chosen = ver
            break
        }
    }
    if (!chosen) throw new Error('payload too long')

    const [version, , ecLen, g1, g1cw, g2, g2cw] = chosen
    const totalDataCw = g1 * g1cw + g2 * g2cw

    // Build bit stream
    const bits = []
    pushBits(bits, 0b0100, 4) // byte mode
    pushBits(bits, bytes.length, 8)
    for (const b of bytes) pushBits(bits, b, 8)
    // terminator
    const cap = totalDataCw * 8
    let term = Math.min(4, cap - bits.length)
    for (let i = 0; i < term; i++) bits.push(0)
    // pad to byte
    while (bits.length % 8 !== 0) bits.push(0)
    // pad bytes
    const padBytes = [0xec, 0x11]
    let pi = 0
    let dataBytes = bitsToBytes(bits)
    while (dataBytes.length < totalDataCw) {
        dataBytes.push(padBytes[pi % 2])
        pi++
    }

    // Split into blocks, compute EC
    const blocks = []
    let offset = 0
    const blockDefs = []
    for (let i = 0; i < g1; i++) blockDefs.push(g1cw)
    for (let i = 0; i < g2; i++) blockDefs.push(g2cw)
    for (const len of blockDefs) {
        const data = dataBytes.slice(offset, offset + len)
        offset += len
        const ec = rsEncode(data, ecLen)
        blocks.push({ data, ec })
    }

    // Interleave data
    const maxData = Math.max(...blockDefs)
    const finalCw = []
    for (let i = 0; i < maxData; i++) {
        for (const blk of blocks) if (i < blk.data.length) finalCw.push(blk.data[i])
    }
    for (let i = 0; i < ecLen; i++) {
        for (const blk of blocks) finalCw.push(blk.ec[i])
    }

    // Build & mask
    const { m, reserved, size } = buildMatrix(version, finalCw)
    let best = null
    let bestScore = Infinity
    for (let mask = 0; mask < 8; mask++) {
        const masked = applyMask(m, reserved, size, mask)
        placeFormat(masked, size, mask)
        const score = scorePenalty(masked, size)
        if (score < bestScore) { bestScore = score; best = masked }
    }
    return { matrix: best, size }
}

const result = computed(() => {
    try {
        const r = encode(props.value)
        return r
    } catch (e) {
        emit('error', e)
        return null
    }
})

const px = computed(() => Number(props.size) || 220)
const quiet = 4 // quiet zone modules
const dim = computed(() => (result.value ? result.value.size + quiet * 2 : 0))
const cell = computed(() => (dim.value ? px.value / dim.value : 0))

// Build a single SVG path string of all dark modules (efficient render).
const pathData = computed(() => {
    if (!result.value) return ''
    const { matrix, size } = result.value
    const c = cell.value
    let d = ''
    for (let r = 0; r < size; r++) {
        for (let col = 0; col < size; col++) {
            if (matrix[r][col]) {
                const x = (col + quiet) * c
                const y = (r + quiet) * c
                d += `M${x.toFixed(2)} ${y.toFixed(2)}h${c.toFixed(2)}v${c.toFixed(2)}h-${c.toFixed(2)}z`
            }
        }
    }
    return d
})
</script>

<template>
    <svg
        v-if="result"
        :width="px"
        :height="px"
        :viewBox="`0 0 ${px} ${px}`"
        role="img"
        aria-label="Kode QR pembayaran"
        class="block"
        shape-rendering="crispEdges"
    >
        <rect :width="px" :height="px" fill="#ffffff" />
        <path :d="pathData" fill="#0f172a" />
    </svg>
</template>
