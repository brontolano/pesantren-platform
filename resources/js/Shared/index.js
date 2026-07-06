// =====================================================================
// UI KIT BERSAMA (Shared) — fondasi design-system lintas PWA peran.
//
// Strategi (Fase 0 blueprint): promosikan komponen netral-peran dari
// `@/Components/Ui` (kit kanonik Portal Wali) ke namespace bersama `@/Shared`,
// tambah komponen shell baru (AppShell/HeroHeader/BottomNav/SegmentedControl/
// LoadingState) + helper. App Guru/Kasir/Pimpinan meng-import dari `@/Shared`.
//
// Zero-churn: Portal Wali tetap memakai `@/Components/Ui` apa adanya.
//   import { AppShell, HeroHeader, UiCard, MoneyText } from '@/Shared'
// =====================================================================

// Re-export seluruh kit kanonik (UiButton, UiCard, StatCard, StatusBadge,
// EmptyState, MoneyText, IconCircle, Chip, ListRow, SectionCard, UiField,
// UiInput, BottomSheet, FlashToast, QrCanvas, MetodeCard, ornamen islami, dst).
export * from '@/Components/Ui'

// Alias netral-peran (agar tak berbau "wali").
export { default as ListItem } from '@/Components/Ui/ListRow.vue'
export { default as PageHeader } from '@/Components/Ui/PageHeading.vue'

// Komponen shell baru (diekstrak dari pola PortalWaliLayout).
export { default as AppShell } from './AppShell.vue'
export { default as HeroHeader } from './HeroHeader.vue'
export { default as BottomNav } from './BottomNav.vue'
export { default as SegmentedControl } from './SegmentedControl.vue'
export { default as LoadingState } from './LoadingState.vue'

// Helper & composable bersama.
export * from './format.js'
export { useOnline } from './useOnline.js'
export { useOfflineForm } from './useOfflineForm.js'
export { SENSITIVE_STORAGE_KEYS, clearSensitiveStorage, clearSensitiveStorageIfGuest } from './authStorage.js'
export { default as KembaliBar } from './KembaliBar.vue'
export { STAF_NAV } from './stafNav.js'
