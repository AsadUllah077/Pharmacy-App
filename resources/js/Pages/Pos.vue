<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Search, ShoppingCart, Trash2, Plus, ScanLine, LockKeyhole } from 'lucide-vue-next';
import AppLayout from '../Components/AppLayout.vue';
import FormErrors from '../Components/FormErrors.vue';
import { getJson, useBusiness } from '../composables';
import type { Medicine, Party } from '../types';
const { branch, can, money } = useBusiness();
type CartItem = {
  medicine_id: number;
  quantity: number;
  unit_discount: string;
  name: string;
  price: string;
  tax_basis_points: number;
  available: number;
};
const form = useForm({
  warehouse_id: branch.value?.warehouses[0]?.id ?? '',
  customer_id: '' as string | number,
  allow_partial: false,
  request_key: crypto.randomUUID(),
  items: [] as CartItem[],
  payments: [{ method: 'cash', amount: '' }],
});
const search = ref('');
const products = ref<Medicine[]>([]);
const loading = ref(false);
const error = ref('');
const customerSearch = ref('');
const customers = ref<Party[]>([]);
const input = ref<HTMLInputElement>();
let timer: ReturnType<typeof setTimeout>;
let sequence = 0;
async function find(scan = false) {
  const current = ++sequence;
  loading.value = true;
  try {
    const data = await getJson<Medicine[]>(
      '/pos/search?warehouse_id=' + form.warehouse_id + '&q=' + encodeURIComponent(search.value),
    );
    if (current !== sequence) return;
    products.value = data;
    error.value = '';
    if (scan) {
      const match = data.find((m) => m.barcode === search.value || m.sku === search.value);
      if (match) {
        add(match);
        search.value = '';
      }
    }
  } catch (e) {
    error.value = (e as Error).message;
  } finally {
    if (current === sequence) loading.value = false;
  }
}
function schedule() {
  clearTimeout(timer);
  timer = setTimeout(() => find(), 200);
}
async function findCustomers() {
  try {
    customers.value = await getJson<Party[]>(
      '/pos/customers?q=' + encodeURIComponent(customerSearch.value),
    );
  } catch (e) {
    error.value = (e as Error).message;
  }
}
function add(m: Medicine) {
  if (m.prescription_required || Number(m.available_stock ?? 0) <= 0) return;
  const line = form.items.find((i) => i.medicine_id === m.id);
  if (line) {
    if (line.quantity < line.available) line.quantity++;
  } else
    form.items.push({
      medicine_id: m.id,
      name: m.name,
      quantity: 1,
      unit_discount: '0.00',
      price: m.selling_price,
      tax_basis_points: m.tax_basis_points,
      available: Number(m.available_stock),
    });
}
// UI estimates only. The server recalculates prices, discounts, tax, allocations and change.
const totals = computed(() =>
  form.items.reduce(
    (sum, i) => {
      const price = Math.round(Number(i.price) * 100);
      const discount = Math.round(Number(i.unit_discount || 0) * 100);
      const net = price - discount;
      const tax = Math.round((net * i.tax_basis_points) / 10000);
      return {
        subtotal: sum.subtotal + price * i.quantity,
        discount: sum.discount + discount * i.quantity,
        tax: sum.tax + tax * i.quantity,
        total: sum.total + (net + tax) * i.quantity,
      };
    },
    { subtotal: 0, discount: 0, tax: 0, total: 0 },
  ),
);
const paidEstimate = computed(() =>
  form.payments.reduce((sum, p) => sum + Math.round(Number(p.amount || 0) * 100), 0),
);
const dueEstimate = computed(() => Math.max(0, totals.value.total - paidEstimate.value) / 100);
function exact() {
  form.payments = [{ method: 'cash', amount: (totals.value.total / 100).toFixed(2) }];
}
function checkout() {
  form
    .transform((data) => ({
      ...data,
      customer_id: data.customer_id || null,
      payments: data.allow_partial ? data.payments.filter((p) => p.amount !== '') : data.payments,
      items: data.items.map((i) => ({
        medicine_id: i.medicine_id,
        quantity: i.quantity,
        unit_discount: i.unit_discount,
      })),
    }))
    .post('/checkout');
}
function keyboard(event: KeyboardEvent) {
  if (event.key === 'F2') {
    event.preventDefault();
    input.value?.focus();
  }
}
onMounted(() => {
  find();
  input.value?.focus();
  window.addEventListener('keydown', keyboard);
});
onUnmounted(() => {
  clearTimeout(timer);
  window.removeEventListener('keydown', keyboard);
});
</script>
<template>
  <AppLayout title="Point of sale" subtitle="Find. Dispense. Done."
    ><template #actions
      ><span class="keyboard-hint"
        ><ScanLine :size="17" /> Scan a barcode · F2 to search</span
      ></template
    ><FormErrors :errors="form.errors" />
    <div v-if="error" role="alert" class="notice error">{{ error }}</div>
    <div class="pos-grid">
      <section>
        <div class="panel pos-search">
          <div class="search-field">
            <Search :size="20" /><input
              ref="input"
              v-model="search"
              placeholder="Search medicine, generic, SKU or scan barcode…"
              aria-label="Search medicines"
              @input="schedule"
              @keydown.enter.prevent="find(true)"
            />
          </div>
          <label
            >Warehouse<select
              v-model="form.warehouse_id"
              :disabled="form.items.length > 0"
              @change="find()"
            >
              <option v-for="w in branch?.warehouses" :key="w.id" :value="w.id">
                {{ w.name }}
              </option>
            </select></label
          >
        </div>
        <div class="section-caption">
          <h2>Medicine catalog</h2>
          <span>{{ loading ? 'Searching…' : products.length + ' products' }}</span>
        </div>
        <div class="product-grid">
          <button
            v-for="m in products"
            :key="m.id"
            class="product-card"
            :disabled="m.prescription_required || !Number(m.available_stock)"
            @click="add(m)"
          >
            <span class="product-symbol">{{ m.name.slice(0, 1) }}</span
            ><span v-if="m.prescription_required" class="product-lock"
              ><LockKeyhole :size="16" /></span
            ><strong>{{ m.name }}</strong
            ><small>{{ m.generic_name || m.sku }}</small
            ><span class="product-bottom"
              ><b>{{ money(m.selling_price) }}</b
              ><span :class="Number(m.available_stock) > 0 ? 'stock-ok' : 'stock-out'">{{
                m.prescription_required
                  ? 'Rx blocked'
                  : Number(m.available_stock || 0) + ' in stock'
              }}</span></span
            >
          </button>
        </div>
        <div v-if="!products.length && !loading" class="panel empty">
          No medicines found. Add medicines and receive stock to begin.
        </div>
        <p class="muted note">
          Restricted products stay blocked until prescription review is implemented. Expired and
          quarantined stock is excluded automatically.
        </p>
      </section>
      <aside class="panel cart-panel">
        <div class="panel-heading">
          <h2><ShoppingCart :size="19" /> Current sale</h2>
          <span class="badge neutral">{{ form.items.length }} items</span>
        </div>
        <div class="customer-select">
          <label
            >Customer search<input
              v-model="customerSearch"
              placeholder="Search customers or leave as walk-in"
              @input="findCustomers" /></label
          ><select v-model="form.customer_id" aria-label="Customer">
            <option value="">Walk-in customer</option>
            <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>
        <div class="cart-items">
          <div v-for="(item, index) in form.items" :key="item.medicine_id" class="cart-item">
            <div class="cart-item-heading">
              <strong>{{ item.name }}</strong
              ><button
                class="icon-button"
                aria-label="Remove item"
                @click="form.items.splice(index, 1)"
              >
                <Trash2 :size="16" />
              </button>
            </div>
            <div class="cart-item-controls">
              <label
                >Quantity<input
                  v-model.number="item.quantity"
                  type="number"
                  min="1"
                  :max="item.available" /></label
              ><label v-if="can('sales.discount')"
                >Discount / unit<input
                  v-model="item.unit_discount"
                  type="number"
                  min="0"
                  step="0.01" /></label
              ><span>{{ money(item.price) }} / unit</span>
            </div>
          </div>
          <div v-if="!form.items.length" class="cart-empty">
            <ShoppingCart :size="34" /><strong>Your cart is ready</strong>
            <p>Choose a medicine or scan its barcode.</p>
          </div>
        </div>
        <div class="cart-summary">
          <div>
            <span>Subtotal</span><span>{{ money(totals.subtotal / 100) }}</span>
          </div>
          <div>
            <span>Discount</span><span>− {{ money(totals.discount / 100) }}</span>
          </div>
          <div>
            <span>Tax</span><span>{{ money(totals.tax / 100) }}</span>
          </div>
          <div class="grand-total">
            <strong>Total estimate</strong><strong>{{ money(totals.total / 100) }}</strong>
          </div>
        </div>
        <div class="payment-section">
          <label class="check-label"
            ><input v-model="form.allow_partial" type="checkbox" />Allow partial / unpaid
            sale</label
          >
          <p v-if="form.allow_partial" class="muted">
            Select a named customer for an unpaid balance. Leave the payment amount blank if nothing
            is paid now.
          </p>
          <p class="notice">Remaining after this payment: {{ money(dueEstimate) }}</p>
          <div class="section-caption">
            <h3>Payment</h3>
            <button class="text-link" @click="exact">Exact cash</button>
          </div>
          <div v-for="(payment, index) in form.payments" :key="index" class="payment-row">
            <select v-model="payment.method" aria-label="Payment method">
              <option value="cash">Cash</option>
              <option value="card">Card</option>
              <option value="bank">Bank transfer</option>
              <option value="wallet">Mobile wallet</option></select
            ><input
              v-model="payment.amount"
              type="number"
              min="0.01"
              step="0.01"
              placeholder="Amount"
              aria-label="Payment amount"
            /><button
              v-if="form.payments.length > 1"
              class="icon-button"
              aria-label="Remove payment"
              @click="form.payments.splice(index, 1)"
            >
              <Trash2 :size="15" />
            </button>
          </div>
          <button
            v-if="form.payments.length < 4"
            class="text-link"
            @click="form.payments.push({ method: 'card', amount: '' })"
          >
            <Plus :size="14" /> Split payment</button
          ><button
            class="btn primary checkout-button"
            :disabled="
              form.processing ||
              !form.items.length ||
              (!form.allow_partial && form.payments.some((p) => !p.amount))
            "
            @click="checkout"
          >
            {{ form.processing ? 'Completing sale…' : 'Complete sale' }}
            <span>{{ money(totals.total / 100) }}</span></button
          ><small class="checkout-note">Prices and stock are verified on the server.</small>
        </div>
      </aside>
    </div></AppLayout
  >
</template>
