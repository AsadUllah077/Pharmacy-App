<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLayout from '../Components/AppLayout.vue';
import { accountingReports } from '../accountingReports';
</script>
<template>
  <AppLayout title="Accounting" subtitle="Reports and supplier payments for your selected branch.">
    <section class="panel form-section">
      <h2>Accounting reports</h2>
      <div class="metric-grid three">
        <Link
          v-for="report in accountingReports"
          :key="report.url"
          :href="report.url"
          class="metric-card"
        >
          <h3>{{ report.title }}</h3>
          <p class="muted">{{ report.description }}</p>
          <span>Open report</span>
        </Link>
      </div>
    </section>
    <section class="panel form-section">
      <h2>Supplier payments</h2>
      <p class="muted">
        View purchase balances, record partial payments and review supplier payment history.
      </p>
      <Link href="/supplier-payments" class="btn primary">Open supplier payments</Link>
    </section>
  </AppLayout>
</template>
