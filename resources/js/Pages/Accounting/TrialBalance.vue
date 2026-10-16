<script setup lang="ts">
import AccountingReportLayout from '../../Components/AccountingReportLayout.vue';
import ReportDateFilter from '../../Components/ReportDateFilter.vue';
import { useBusiness } from '../../composables';
import type { AccountBalance } from '../../accountingReports';
const props = defineProps<{ balances: AccountBalance[]; from: string; to: string }>();
const { money } = useBusiness();
const sum = (key: 'debit' | 'credit') =>
  props.balances.reduce((n, b) => n + Math.round(Number(b[key]) * 100), 0) / 100;
</script>
<template>
  <AccountingReportLayout
    title="Trial balance"
    subtitle="Account totals for the selected posting period."
  >
    <div class="metric-grid three">
      <div class="metric-card">
        <span>Total debits</span><strong>{{ money(sum('debit')) }}</strong>
      </div>
      <div class="metric-card">
        <span>Total credits</span><strong>{{ money(sum('credit')) }}</strong>
      </div>
      <div class="metric-card">
        <span>Difference</span><strong>{{ money(sum('debit') - sum('credit')) }}</strong>
      </div>
    </div>
    <section class="panel">
      <div class="panel-heading">
        <h2>Trial balance</h2>
        <span class="badge" :class="sum('debit') === sum('credit') ? 'green' : 'red'">{{
          sum('debit') === sum('credit') ? 'Balanced' : 'Out of balance'
        }}</span>
      </div>
      <ReportDateFilter :from="from" :to="to" url="/accounting/trial-balance" />
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
  </AccountingReportLayout>
</template>
