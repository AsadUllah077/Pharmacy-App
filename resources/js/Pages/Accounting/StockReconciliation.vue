<script setup lang="ts">
import AccountingReportLayout from '../../Components/AccountingReportLayout.vue';
defineProps<{
  discrepancies: {
    id: number;
    lot_number: string;
    quantity: number;
    ledger_quantity: number;
    quarantined_quantity: number;
    ledger_quarantine: number;
  }[];
}>();
</script>
<template>
  <AccountingReportLayout
    title="Stock reconciliation"
    subtitle="Current batch balances compared with all recorded stock movements. Up to 100 discrepancies shown."
  >
    <section class="panel form-section">
      <h2>Stock reconciliation</h2>
      <p v-if="!discrepancies.length" class="notice success">
        Batch balances agree with all recorded stock movements.
      </p>
      <p v-for="d in discrepancies" :key="d.id" class="notice error">
        Batch {{ d.lot_number }}: balance {{ d.quantity }} / movement {{ d.ledger_quantity }};
        quarantine {{ d.quarantined_quantity }} / movement {{ d.ledger_quarantine }}
      </p>
    </section>
  </AccountingReportLayout>
</template>
