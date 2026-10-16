<script setup lang="ts">
import { computed } from 'vue';
import AccountingReportLayout from '../../Components/AccountingReportLayout.vue';
import ReportDateFilter from '../../Components/ReportDateFilter.vue';
import { useBusiness } from '../../composables';
import type { AccountBalance } from '../../accountingReports';
const props = defineProps<{ balances: AccountBalance[]; from: string; to: string }>();
const { money } = useBusiness();
const accounts = computed(() =>
  props.balances.filter((b) => ['income', 'contra_income', 'expense'].includes(b.type)),
);
const result = (b: AccountBalance) =>
  Math.round(Number(b.credit) * 100) - Math.round(Number(b.debit) * 100);
const netIncome = computed(() => accounts.value.reduce((n, b) => n + result(b), 0) / 100);
</script>
<template>
  <AccountingReportLayout
    title="Operating result"
    subtitle="Revenue less returns, cost of goods sold and expenses for the selected posting period."
  >
    <div class="metric-card">
      <span>Operating result</span><strong>{{ money(netIncome) }}</strong
      ><small>Income / (loss)</small>
    </div>
    <section class="panel">
      <ReportDateFilter :from="from" :to="to" url="/accounting/operating-result" />
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Code</th>
              <th>Account</th>
              <th>Type</th>
              <th class="number">Income / (cost)</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="b in accounts" :key="b.code">
              <td>{{ b.code }}</td>
              <td>{{ b.name }}</td>
              <td>{{ b.type }}</td>
              <td class="number">{{ money(result(b) / 100) }}</td>
            </tr>
            <tr v-if="!accounts.length">
              <td colspan="4" class="empty">No income or expenses for this posting period.</td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <th colspan="3">Operating result</th>
              <th class="number">{{ money(netIncome) }}</th>
            </tr>
          </tfoot>
        </table>
      </div>
    </section>
  </AccountingReportLayout>
</template>
