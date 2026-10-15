import { computed, ref } from 'vue';

export type ThemePreference = 'system' | 'light' | 'dark';
const storageKey = 'pharmaflow.theme';
const preference = ref<ThemePreference>('system');
const systemDark = ref(false);
const isDark = computed(
  () => preference.value === 'dark' || (preference.value === 'system' && systemDark.value),
);
let initialized = false;

function validPreference(value: string | null): ThemePreference {
  return value === 'light' || value === 'dark' ? value : 'system';
}

function applyTheme() {
  document.documentElement.dataset.theme = isDark.value ? 'dark' : 'light';
  document.documentElement.style.colorScheme = isDark.value ? 'dark' : 'light';
}

export function initializeTheme() {
  if (initialized) return;
  initialized = true;
  const media = window.matchMedia('(prefers-color-scheme: dark)');
  systemDark.value = media.matches;
  try {
    preference.value = validPreference(localStorage.getItem(storageKey));
  } catch {
    /* Storage can be disabled in private browsers. */
  }
  applyTheme();
  media.addEventListener('change', (event) => {
    systemDark.value = event.matches;
    applyTheme();
  });
  window.addEventListener('storage', (event) => {
    if (event.key === storageKey || event.key === null) {
      preference.value = validPreference(event.newValue);
      applyTheme();
    }
  });
}

function setTheme(value: ThemePreference) {
  preference.value = value;
  try {
    if (value === 'system') localStorage.removeItem(storageKey);
    else localStorage.setItem(storageKey, value);
  } catch {
    /* Keep the selection usable for this session without storage. */
  }
  applyTheme();
}

export function useTheme() {
  return {
    preference: computed(() => preference.value),
    isDark,
    setTheme,
    toggleTheme: () => setTheme(isDark.value ? 'light' : 'dark'),
  };
}
