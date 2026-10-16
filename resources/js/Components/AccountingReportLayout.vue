<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLayout from './AppLayout.vue';
import { accountingReports } from '../accountingReports';
defineProps<{ title: string; subtitle: string }>();
</script>
<template>
  <AppLayout :title="title" :subtitle="subtitle">
    <template #actions
      ><Link href="/accounting" class="btn secondary">Back to accounting</Link></template
    >
    <nav class="table-toolbar" aria-label="Accounting reports">
      <Link
        v-for="report in accountingReports"
        :key="report.url"
        :href="report.url"
        class="btn"
        :class="title === report.title ? 'primary' : 'secondary'"
        :aria-current="title === report.title ? 'page' : undefined"
        >{{ report.title }}</Link
      >
    </nav>
    <slot />
  </AppLayout>
</template>
