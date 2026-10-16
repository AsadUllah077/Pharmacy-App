<script setup lang="ts">
import AccountingReportLayout from '../../Components/AccountingReportLayout.vue';
import ReportDateFilter from '../../Components/ReportDateFilter.vue';
import Pagination from '../../Components/Pagination.vue';
import type { Pagination as Paged } from '../../types';
defineProps<{
  journals: Paged<{ id: number; source: string; description: string; posting_date: string }>;
  from: string;
  to: string;
}>();
</script>
<template>
  <AccountingReportLayout
    title="Journal entries"
    subtitle="Source transactions and their posting dates."
  >
    <section class="panel">
      <div class="panel-heading">
        <h2>Journal entries</h2>
        <span class="muted">Selected posting period</span>
      </div>
      <ReportDateFilter :from="from" :to="to" url="/accounting/journal-entries" />
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
            <tr v-if="!journals.data.length">
              <td colspan="3" class="empty">No journal entries for this posting period.</td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pagination :links="journals.links" :total="journals.total" />
    </section>
  </AccountingReportLayout>
</template>
