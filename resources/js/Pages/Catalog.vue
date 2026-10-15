<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { Plus, Search, Pencil, X } from 'lucide-vue-next';
import AppLayout from '../Components/AppLayout.vue';
import Pagination from '../Components/Pagination.vue';
import FormErrors from '../Components/FormErrors.vue';
import { useBusiness } from '../composables';
import type { Pagination as Paged } from '../types';
type Value = string | number | boolean | null;
type Row = Record<string, Value>;
const props = defineProps<{
  kind: 'medicines' | 'suppliers' | 'customers';
  records: Paged<Row>;
  q: string;
}>();
const { can, money } = useBusiness();
const q = ref(props.q);
const open = ref(false);
const editing = ref<number | null>(null);
const fields = computed(() =>
  props.kind === 'medicines'
    ? [
        ['name', 'Medicine name', 'text'],
        ['sku', 'SKU', 'text'],
        ['barcode', 'Barcode', 'text'],
        ['generic_name', 'Generic name', 'text'],
        ['manufacturer', 'Manufacturer', 'text'],
        ['category', 'Category', 'text'],
        ['strength', 'Strength', 'text'],
        ['dosage_form', 'Dosage form', 'text'],
        ['unit', 'Dispensing unit', 'text'],
        ['selling_price', 'Selling price', 'number'],
        ['tax_basis_points', 'Tax in basis points (100 = 1%)', 'number'],
        ['reorder_level', 'Reorder level', 'number'],
        ['storage', 'Storage instructions', 'text'],
        ['prescription_required', 'Prescription required', 'checkbox'],
        ['active', 'Active', 'checkbox'],
      ]
    : [
        ['name', 'Name', 'text'],
        ['phone', 'Phone', 'text'],
        ['email', 'Email', 'email'],
        ['address', 'Address', 'text'],
        ['active', 'Active', 'checkbox'],
      ],
);
const defaults: Row = {
  name: '',
  sku: '',
  barcode: '',
  generic_name: '',
  manufacturer: '',
  category: 'Medicine',
  strength: '',
  dosage_form: '',
  unit: 'unit',
  selling_price: '0.00',
  tax_basis_points: 0,
  reorder_level: 10,
  storage: '',
  prescription_required: false,
  active: true,
  phone: '',
  email: '',
  address: '',
};
const form = useForm<Row>(defaults);
const title = computed(() =>
  props.kind === 'medicines' ? 'Medicines' : props.kind === 'suppliers' ? 'Suppliers' : 'Customers',
);
const allowed = (edit: boolean) =>
  can(
    props.kind === 'medicines'
      ? edit
        ? 'medicines.update'
        : 'medicines.create'
      : props.kind + '.manage',
  );
function edit(row?: Row) {
  editing.value = row ? Number(row.id) : null;
  form.reset();
  form.clearErrors();
  fields.value.forEach(([key]) => {
    form[key] = row?.[key] ?? defaults[key];
  });
  open.value = true;
}
function save() {
  const options = {
    preserveScroll: true,
    onSuccess: () => {
      open.value = false;
    },
  };
  if (editing.value) form.put('/' + props.kind + '/' + editing.value, options);
  else form.post('/' + props.kind, options);
}
function text(value: Value) {
  return value == null ? '—' : String(value);
}
</script>
<template>
  <AppLayout
    :title="title"
    :subtitle="
      kind === 'medicines'
        ? 'One shared catalog. Every product, clearly defined.'
        : 'Keep your business relationships organized.'
    "
    ><template #actions
      ><button v-if="allowed(false)" class="btn primary" @click="edit()">
        <Plus :size="17" /> Add
        {{ kind === 'medicines' ? 'medicine' : kind === 'suppliers' ? 'supplier' : 'customer' }}
      </button></template
    >
    <section class="panel">
      <form class="table-toolbar" @submit.prevent="router.get('/' + kind, { q: q })">
        <div class="search-field">
          <Search :size="18" /><input
            v-model="q"
            aria-label="Search records"
            placeholder="Search records…"
          />
        </div>
        <button class="btn secondary">Search</button>
      </form>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <template v-if="kind === 'medicines'"
                ><th>SKU / barcode</th>
                <th>Generic / manufacturer</th>
                <th class="number">Price</th>
                <th>Classification</th></template
              ><template v-else
                ><th>Phone</th>
                <th>Email</th></template
              >
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in records.data" :key="Number(row.id)">
              <td class="strong">{{ row.name }}</td>
              <template v-if="kind === 'medicines'"
                ><td>
                  {{ row.sku }}<small class="cell-small">{{ row.barcode || 'No barcode' }}</small>
                </td>
                <td>
                  {{ row.generic_name || '—'
                  }}<small class="cell-small">{{ row.manufacturer }}</small>
                </td>
                <td class="number">{{ money(String(row.selling_price)) }}</td>
                <td>
                  <span class="badge" :class="row.prescription_required ? 'amber' : 'neutral'">{{
                    row.prescription_required ? 'Prescription' : 'Unrestricted'
                  }}</span>
                </td></template
              ><template v-else
                ><td>{{ row.phone || '—' }}</td>
                <td>{{ row.email || '—' }}</td></template
              >
              <td>
                <span class="badge" :class="row.active ? 'green' : 'neutral'">{{
                  row.active ? 'Active' : 'Archived'
                }}</span>
              </td>
              <td>
                <button
                  v-if="allowed(true)"
                  class="icon-button"
                  aria-label="Edit record"
                  @click="edit(row)"
                >
                  <Pencil :size="16" />
                </button>
              </td>
            </tr>
            <tr v-if="!records.data.length">
              <td colspan="8" class="empty">
                No records found. Add your first record to get started.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pagination :links="records.links" :total="records.total" />
    </section>
    <div v-if="open" class="modal-backdrop" @click.self="open = false">
      <section role="dialog" aria-modal="true" aria-labelledby="catalog-title" class="modal">
        <div class="panel-heading">
          <h2 id="catalog-title">{{ editing ? 'Edit' : 'Add' }} {{ title.toLowerCase() }}</h2>
          <button class="icon-button" aria-label="Close" @click="open = false">
            <X :size="20" />
          </button>
        </div>
        <form @submit.prevent="save">
          <FormErrors :errors="form.errors" />
          <div class="form-grid">
            <label
              v-for="[key, label, type] in fields"
              :key="key"
              :class="{ 'check-label': type === 'checkbox' }"
              ><input v-if="type === 'checkbox'" v-model="form[key]" type="checkbox" /><span>{{
                label
              }}</span
              ><input
                v-if="type !== 'checkbox'"
                :value="text(form[key]) === '—' ? '' : text(form[key])"
                :type="type"
                :step="key === 'selling_price' ? '0.01' : '1'"
                :required="['name', 'sku', 'selling_price', 'category', 'unit'].includes(key)"
                @input="form[key] = ($event.target as HTMLInputElement).value"
            /></label>
          </div>
          <div class="modal-actions">
            <button type="button" class="btn secondary" @click="open = false">Cancel</button
            ><button class="btn primary" :disabled="form.processing">
              {{ form.processing ? 'Saving…' : 'Save record' }}
            </button>
          </div>
        </form>
      </section>
    </div></AppLayout
  >
</template>
