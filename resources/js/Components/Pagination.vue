<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
defineProps<{ links: { url: string | null; label: string; active: boolean }[]; total: number }>();
function label(value: string) {
  return value.replace('&laquo;', '‹').replace('&raquo;', '›');
}
</script>
<template>
  <div class="pagination">
    <span>{{ total }} records</span>
    <div>
      <template v-for="(link, i) in links" :key="i"
        ><Link v-if="link.url" :href="link.url" :class="{ active: link.active }" preserve-scroll>{{
          label(link.label)
        }}</Link
        ><span v-else class="disabled">{{ label(link.label) }}</span></template
      >
    </div>
  </div>
</template>
