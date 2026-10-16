<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '../Components/AppLayout.vue';
import Pagination from '../Components/Pagination.vue';
import SupplierPaymentForm from '../Components/SupplierPaymentForm.vue';
import { useBusiness } from '../composables';
import type { Order, Pagination as Paged } from '../types';
const props = defineProps<{
  orders: Paged<Order>;
  suppliers: { id: number; name: string; due: string; credit: string }[];
  history: Paged<{
    id: number;
    supplier_name: string;
    amount: string;
    method: string;
    created_at: string;
  }>;
  supplierId: string;
}>();
const { can, money, date } = useBusiness();
const supplierId = ref(props.supplierId);
const selected = computed(() => props.suppliers.find((s) => String(s.id) === props.supplierId));
</script>
<template>
  <AppLayout
    title="Supplier payments"
    subtitle="Pay purchases in full or in installments and track the remaining balance."
  >
    <section class="panel form-section">
      <form
        class="table-toolbar"
        @submit.prevent="router.get('/supplier-payments', { supplier_id: supplierId || undefined })"
      >
        <label
          >Supplier<select v-model="supplierId">
            <option value="">All suppliers</option>
            <option v-for="s in suppliers" :key="s.id" :value="String(s.id)">{{ s.name }}</option>
          </select></label
        >
        <button class="btn secondary">View supplier</button>
      </form>
      <template v-if="selected">
        <h2>{{ selected.name }}</h2>
        <p>
          Outstanding: <strong>{{ money(selected.due) }}</strong> · Available supplier credit:
          <strong>{{ money(selected.credit) }}</strong>
        </p>
        <SupplierPaymentForm
          v-if="can('accounting.manage') && Number(selected.due) > 0"
          :key="selected.id"
          :supplier-id="selected.id"
          :due="selected.due"
        />
        <p class="muted">
          Payments made here apply to this supplier's oldest outstanding purchases. To pay one
          purchase, use its payment action below.
        </p>
      </template>
      <p v-else class="muted">
        Choose a supplier to record a payment, or open a purchase below to pay that order.
      </p>
    </section>
    <section class="panel">
      <div class="panel-heading"><h2>Purchase balances</h2></div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Purchase</th>
              <th>Supplier</th>
              <th class="number">Received</th>
              <th class="number">Returns</th>
              <th class="number">Paid</th>
              <th class="number">Credit applied</th>
              <th class="number">Remaining</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="order in orders.data" :key="order.id">
              <td>PO-{{ order.id }}</td>
              <td>{{ order.supplier.name }}</td>
              <td class="number">{{ money(order.payment_summary.received) }}</td>
              <td class="number">{{ money(order.payment_summary.returned) }}</td>
              <td class="number">{{ money(order.payment_summary.paid) }}</td>
              <td class="number">{{ money(order.payment_summary.credit_applied) }}</td>
              <td class="number">{{ money(order.payment_summary.due) }}</td>
              <td>{{ order.payment_summary.status }}</td>
              <td>
                <Link
                  v-if="can('purchases.view')"
                  :href="'/purchases/' + order.id + '#payments'"
                  class="text-link"
                  >View / pay</Link
                >
              </td>
            </tr>
            <tr v-if="!orders.data.length">
              <td colspan="9" class="empty">No purchases for this selection.</td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pagination :links="orders.links" :total="orders.total" />
    </section>
    <section class="panel">
      <div class="panel-heading"><h2>Payment history</h2></div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Reference</th>
              <th>Supplier</th>
              <th class="number">Amount</th>
              <th>Method</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in history.data" :key="p.id">
              <td>SP-{{ p.id }}</td>
              <td>{{ p.supplier_name }}</td>
              <td class="number">{{ money(p.amount) }}</td>
              <td>{{ p.method }}</td>
              <td>{{ date(p.created_at) }}</td>
            </tr>
            <tr v-if="!history.data.length">
              <td colspan="5" class="empty">No supplier payments recorded.</td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pagination :links="history.links" :total="history.total" />
    </section>
  </AppLayout>
</template>
