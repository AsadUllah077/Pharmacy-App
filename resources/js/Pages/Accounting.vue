<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '../Components/AppLayout.vue';
import Pagination from '../Components/Pagination.vue';
import FormErrors from '../Components/FormErrors.vue';
import { useBusiness } from '../composables';
import { getJson } from '../composables';
import type { Pagination as Paged, Party } from '../types';
interface Balance {
  code: string;
  name: string;
  type: string;
  debit: string;
  credit: string;
}
const props = defineProps<{
  balances: Balance[];
  discrepancies: {
    id: number;
    lot_number: string;
    quantity: number;
    ledger_quantity: number;
    quarantined_quantity: number;
    ledger_quarantine: number;
  }[];
  journals: Paged<{ id: number; source: string; description: string; posting_date: string }>;
  from: string;
  to: string;
}>();
const { money, can } = useBusiness();
const from = ref(props.from),
  to = ref(props.to);
const sum = (key: 'debit' | 'credit') =>
  props.balances.reduce((n, b) => n + Math.round(Number(b[key]) * 100), 0) / 100;
const netIncome = computed(
  () =>
    props.balances
      .filter((b) => ['income', 'contra_income', 'expense'].includes(b.type))
      .reduce(
        (n, b) => n + Math.round(Number(b.credit) * 100) - Math.round(Number(b.debit) * 100),
        0,
      ) / 100,
);
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
  <AppLayout title="Accounting" subtitle="Balanced books. Traceable transactions."
    ><div class="metric-grid three">
      <div class="metric-card">
        <span>Total debits</span><strong>{{ money(sum('debit')) }}</strong
        ><small>Selected posting period</small>
      </div>
      <div class="metric-card">
        <span>Total credits</span><strong>{{ money(sum('credit')) }}</strong
        ><small>Difference: {{ money(sum('debit') - sum('credit')) }}</small>
      </div>
      <div class="metric-card">
        <span>Operating result</span><strong>{{ money(netIncome) }}</strong
        ><small>Revenue less returns, COGS and expenses</small>
      </div>
    </div>
    <section class="panel">
      <div class="panel-heading">
        <h2>Trial balance</h2>
        <span class="badge" :class="sum('debit') === sum('credit') ? 'green' : 'red'">{{
          sum('debit') === sum('credit') ? 'Balanced' : 'Out of balance'
        }}</span>
      </div>
      <form
        class="table-toolbar"
        @submit.prevent="
          router.get('/accounting', { from: from || undefined, to: to || undefined })
        "
      >
        <label>From<input v-model="from" type="date" /></label
        ><label>To<input v-model="to" type="date" /></label
        ><button class="btn secondary">Apply dates</button>
      </form>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Code</th>
              <th>Account</th>
              <th>Type</th>
              <th class="number">Debits</th>
              <th class="number">Credits</th>
              <th class="number">Net debit / (credit)</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="b in balances" :key="b.code">
              <td>{{ b.code }}</td>
              <td class="strong">{{ b.name }}</td>
              <td>{{ b.type }}</td>
              <td class="number">{{ money(b.debit) }}</td>
              <td class="number">{{ money(b.credit) }}</td>
              <td class="number">{{ money(Number(b.debit) - Number(b.credit)) }}</td>
            </tr>
            <tr v-if="!balances.length">
              <td colspan="6" class="empty">
                Journals appear when you receive stock, sell, return, or post payments.
              </td>
            </tr>
          </tbody>
        </table>
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
    <section class="panel">
      <div class="panel-heading">
        <h2>Journal entries</h2>
        <span class="muted">All posting dates</span>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Source</th>
              <th>Description</th>
              <th>Posting date</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="entry in journals.data" :key="entry.id">
              <td>{{ entry.source }}</td>
              <td>{{ entry.description }}</td>
              <td>{{ entry.posting_date }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pagination :links="journals.links" :total="journals.total" /></section
  ></AppLayout>
</template>
