<script setup lang="ts">
import { Moon, Sun } from 'lucide-vue-next';
import { useTheme } from '../theme';
const { isDark, toggleTheme } = useTheme();
</script>
<template>
  <button
    type="button"
    class="icon-button theme-toggle"
    :aria-label="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
    :title="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
    :aria-pressed="isDark"
    @click="toggleTheme"
  >
    <Sun v-if="isDark" :size="19" aria-hidden="true" />
    <Moon v-else :size="19" aria-hidden="true" />
  </button>
</template>
