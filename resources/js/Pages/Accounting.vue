<script setup lang="ts">
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../Components/AppLayout.vue';
import FormErrors from '../Components/FormErrors.vue';
import { useBusiness, getJson } from '../composables';
import type { Party } from '../types';
import { accountingReports } from '../accountingReports';
const { can } = useBusiness();
const supplierSearch = ref('');
const suppliers = ref<Party[]>([]);
const searchError = ref('');
const payment = useForm({
  supplier_id: '',
  amount: '',
  method: 'bank',
  request_key: crypto.randomUUID(),
});
async function find() {
  try {
    const response = await getJson<{ data: Party[] }>(
      '/supplier-search?q=' + encodeURIComponent(supplierSearch.value),
    );
    suppliers.value = response.data;
    searchError.value = '';
  } catch (e) {
    searchError.value = (e as Error).message;
  }
}
function pay() {
  if (!confirm('Post this supplier payment?')) return;
  payment.post('/supplier-payments', {
    onSuccess: () => {
      payment.reset();
      payment.request_key = crypto.randomUUID();
    },
  });
}
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
    <section v-if="can('accounting.manage')" class="panel form-section">
      <h2>Pay supplier</h2>
      <form @submit.prevent="pay">
        <FormErrors :errors="payment.errors" />
        <p v-if="searchError" class="field-error">{{ searchError }}</p>
        <div class="form-grid">
          <label
            >Find supplier<input
              v-model="supplierSearch"
              placeholder="Enter supplier name"
              @input="find" /></label
          ><label
            >Supplier<select v-model="payment.supplier_id" required>
              <option value="">Select supplier</option>
              <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select></label
          ><label
            >Amount<input
              v-model="payment.amount"
              type="number"
              min="0.01"
              step="0.01"
              required /></label
          ><label
            >Payment method<select v-model="payment.method">
              <option value="bank">Bank</option>
              <option value="cash">Cash</option>
              <option value="card">Card</option>
              <option value="wallet">Wallet</option>
            </select></label
          >
        </div>
        <p class="muted">
          Payments are limited to this supplier's outstanding received-goods liability.
        </p>
        <button class="btn primary" :disabled="payment.processing">Post payment</button>
      </form>
    </section>
  </AppLayout>
</template>
