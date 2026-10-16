<script setup lang="ts">
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '../Components/AppLayout.vue';
import Pagination from '../Components/Pagination.vue';
import { useBusiness } from '../composables';
import type { Sale, Pagination as Paged } from '../types';
const props = defineProps<{ sales: Paged<Sale>; q: string }>();
const q = ref(props.q);
const { can, money, date } = useBusiness();
</script>
<template>
  <AppLayout title="Sales & returns" subtitle="Every invoice, payment and return in one place."
    ><template #actions
      ><a v-if="can('reports.view')" class="btn secondary" href="/exports/sales">Export CSV</a
      ><Link v-if="can('sales.create')" href="/pos" class="btn primary">New sale</Link></template
    >
    <section class="panel">
      <form class="table-toolbar" @submit.prevent="router.get('/sales', { q })">
        <input
          v-model="q"
          placeholder="Search invoice number"
          aria-label="Search invoices"
        /><button class="btn secondary">Search</button>
      </form>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Invoice</th>
              <th>Customer</th>
              <th>Cashier</th>
              <th>Date</th>
              <th class="number">Total</th>
              <th class="number">Remaining</th>
              <th>Payment status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="sale in sales.data" :key="sale.id">
              <td class="strong">{{ sale.invoice_number }}</td>
              <td>{{ sale.customer?.name || 'Walk-in' }}</td>
              <td>{{ sale.cashier.name }}</td>
              <td>{{ date(sale.created_at) }}</td>
              <td class="number">{{ money(sale.total) }}</td>
              <td class="number">{{ money(sale.payment_summary.due) }}</td>
              <td>{{ sale.payment_summary.status }}</td>
              <td>
                <Link class="text-link" :href="'/sales/' + sale.id">View / pay / return →</Link>
              </td>
            </tr>
            <tr v-if="!sales.data.length">
              <td colspan="8" class="empty">No sales found.</td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pagination :links="sales.links" :total="sales.total" /></section
  ></AppLayout>
</template>
