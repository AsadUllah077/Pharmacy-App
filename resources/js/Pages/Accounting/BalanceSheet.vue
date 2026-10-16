<script setup lang="ts">
import { ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AccountingReportLayout from '../../Components/AccountingReportLayout.vue';
import FormErrors from '../../Components/FormErrors.vue';
import { useBusiness } from '../../composables';
type BalanceRow = { code: string; name: string; amount: string };
const props = defineProps<{
  asOf: string;
  sheet: {
    assets: BalanceRow[];
    liabilities: BalanceRow[];
    equity: BalanceRow[];
    totals: {
      assets: string;
      liabilities: string;
      equity: string;
      liabilities_and_equity: string;
      difference: string;
    };
    balanced: boolean;
  };
}>();
const { money, branch } = useBusiness();
const page = usePage();
const asOf = ref(props.asOf);
watch(
  () => props.asOf,
  (value) => {
    asOf.value = value;
  },
);
const sections = [
  { key: 'assets', title: 'Assets', empty: 'No asset balances as of this date.' },
  { key: 'liabilities', title: 'Liabilities', empty: 'No liability balances as of this date.' },
  { key: 'equity', title: 'Equity', empty: 'No equity balances as of this date.' },
] as const;
function amount(value: string) {
  return Number(value) < 0 ? '(' + money(Math.abs(Number(value))) + ')' : money(value);
}
function print() {
  window.print();
}
</script>
<template>
  <AccountingReportLayout
    title="Balance sheet"
    subtitle="Assets, liabilities and equity from all postings through the selected date."
  >
    <section class="panel form-section">
      <form
        class="print:hidden"
        @submit.prevent="router.get('/accounting/balance-sheet', { as_of: asOf })"
      >
        <FormErrors :errors="page.props.errors" />
        <div class="table-toolbar">
          <label>As of date<input v-model="asOf" type="date" required /></label>
          <button class="btn secondary">Apply date</button>
          <button
            type="button"
            class="btn secondary"
            @click="router.get('/accounting/balance-sheet')"
          >
            Today
          </button>
          <button type="button" class="btn secondary" @click="print">Print balance sheet</button>
        </div>
      </form>
      <div class="panel-heading">
        <div>
          <h2>Balance sheet ? {{ branch?.name }} · As of {{ props.asOf }}</h2>
          <p class="muted">Assets = Liabilities + Equity</p>
        </div>
        <span class="badge" :class="sheet.balanced ? 'green' : 'red'">{{
          sheet.balanced ? 'Balanced' : 'Out of balance'
        }}</span>
      </div>
    </section>
    <div class="metric-grid three">
      <div class="metric-card">
        <span>Total assets</span><strong>{{ amount(sheet.totals.assets) }}</strong>
      </div>
      <div class="metric-card">
        <span>Total liabilities</span><strong>{{ amount(sheet.totals.liabilities) }}</strong>
      </div>
      <div class="metric-card">
        <span>Total equity</span><strong>{{ amount(sheet.totals.equity) }}</strong>
      </div>
    </div>
    <section v-for="section in sections" :key="section.key" class="panel">
      <div class="panel-heading">
        <h2>{{ section.title }}</h2>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Code</th>
              <th>Account</th>
              <th class="number">Balance</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in sheet[section.key]" :key="row.code || row.name">
              <td>{{ row.code || '—' }}</td>
              <td>{{ row.name }}</td>
              <td class="number">{{ amount(row.amount) }}</td>
            </tr>
            <tr v-if="!sheet[section.key].length">
              <td colspan="3" class="empty">{{ section.empty }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <th colspan="2">Total {{ section.title.toLowerCase() }}</th>
              <th class="number">{{ amount(sheet.totals[section.key]) }}</th>
            </tr>
          </tfoot>
        </table>
      </div>
    </section>
    <section class="panel form-section">
      <h2>Balance check</h2>
      <p>
        Total assets: <strong>{{ amount(sheet.totals.assets) }}</strong>
      </p>
      <p>
        Total liabilities and equity:
        <strong>{{ amount(sheet.totals.liabilities_and_equity) }}</strong>
      </p>
      <p :class="['notice', sheet.balanced ? 'success' : 'error']">
        Difference: {{ amount(sheet.totals.difference) }}
      </p>
      <p class="muted">
        Accumulated earnings include all unclosed income, returns and expenses through this date.
        Balances reflect recorded entries; inventory includes quarantine. Negative balances appear
        in parentheses.
      </p>
    </section>
  </AccountingReportLayout>
</template>
