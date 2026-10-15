<script setup lang="ts">
import { Activity as ActivityIcon } from 'lucide-vue-next';
import { ref, onMounted, onUnmounted, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import {
  Chart,
  LineController,
  LineElement,
  PointElement,
  CategoryScale,
  LinearScale,
  Tooltip,
  Filler,
} from 'chart.js';
import {
  ArrowUpRight,
  ShoppingCart,
  Boxes,
  Receipt,
  Clock,
  Plus,
  ArrowRight,
  AlertTriangle,
} from 'lucide-vue-next';
import AppLayout from '../Components/AppLayout.vue';
import { useBusiness } from '../composables';
import type { Sale } from '../types';
import { useTheme } from '../theme';
const props = defineProps<{
  stats: {
    today_sales: string;
    month_sales: string;
    transactions: number;
    inventory_value: string;
    expired_units: number;
  };
  days: { label: string; total: number }[];
  recent: Sale[];
  alerts: { id: number; message: string }[];
}>();
const { money, date, can, branch } = useBusiness();
const { isDark } = useTheme();
const canvas = ref<HTMLCanvasElement>();
let chart: Chart<'line'> | undefined;
function chartColors() {
  const style = getComputedStyle(document.documentElement);
  return {
    line: style.getPropertyValue('--accent-text').trim(),
    grid: style.getPropertyValue('--chart-grid').trim(),
    fill: style.getPropertyValue('--chart-fill').trim(),
    surface: style.getPropertyValue('--surface').trim(),
    text: style.getPropertyValue('--text-muted').trim(),
    title: style.getPropertyValue('--text').trim(),
  };
}
Chart.register(
  LineController,
  LineElement,
  PointElement,
  CategoryScale,
  LinearScale,
  Tooltip,
  Filler,
);
onMounted(() => {
  if (!canvas.value) return;
  const colors = chartColors();
  chart = new Chart(canvas.value, {
    type: 'line',
    data: {
      labels: props.days.map((d) => d.label),
      datasets: [
        {
          data: props.days.map((d) => Number(d.total)),
          borderColor: colors.line,
          backgroundColor: colors.fill,
          fill: true,
          tension: 0.35,
          pointRadius: 4,
          pointBackgroundColor: colors.surface,
          pointBorderWidth: 2,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? false : undefined,
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: colors.surface,
          titleColor: colors.title,
          bodyColor: colors.text,
          borderColor: colors.grid,
          borderWidth: 1,
        },
      },
      scales: {
        x: { grid: { display: false }, border: { display: false }, ticks: { color: colors.text } },
        y: {
          beginAtZero: true,
          border: { display: false },
          grid: { color: colors.grid },
          ticks: { color: colors.text },
        },
      },
    },
  });
});
onUnmounted(() => chart?.destroy());
watch(isDark, () => {
  if (!chart) return;
  const colors = chartColors();
  chart.data.datasets[0].borderColor = colors.line;
  chart.data.datasets[0].backgroundColor = colors.fill;
  chart.data.datasets[0].pointBackgroundColor = colors.surface;
  const scales = chart.options.scales;
  if (scales?.x) scales.x.ticks = { ...scales.x.ticks, color: colors.text };
  if (scales?.y) {
    scales.y.ticks = { ...scales.y.ticks, color: colors.text };
    scales.y.grid = { ...scales.y.grid, color: colors.grid };
  }
  if (chart.options.plugins?.tooltip)
    Object.assign(chart.options.plugins.tooltip, {
      backgroundColor: colors.surface,
      titleColor: colors.title,
      bodyColor: colors.text,
      borderColor: colors.grid,
    });
  chart.update('none');
});
watch(
  () => props.days,
  (days) => {
    if (!chart) return;
    chart.data.labels = days.map((d) => d.label);
    chart.data.datasets[0].data = days.map((d) => Number(d.total));
    chart.update();
  },
);
</script>
<template>
  <AppLayout title="Overview" subtitle="A clear picture of your pharmacy, all in one place."
    ><template #actions
      ><Link v-if="can('sales.create')" href="/pos" class="btn primary"
        ><Plus :size="17" /> New sale</Link
      ></template
    >
    <div class="welcome-banner">
      <div>
        <span class="pill-label">WORKSPACE OVERVIEW</span>
        <h2>Every detail. Better managed.</h2>
        <p>Keep {{ branch?.name }} running smoothly, from the first receipt to the final sale.</p>
      </div>
      <div class="banner-mark">
        <ActivityIcon /><span>CARE IN EVERY<br />CONNECTION</span>
      </div>
    </div>
    <div class="metric-grid">
      <div class="metric-card">
        <div class="metric-top">
          Today's sales <span class="metric-icon teal"><ShoppingCart :size="19" /></span>
        </div>
        <strong>{{ money(stats.today_sales) }}</strong
        ><small>Completed sales · before refunds</small>
      </div>
      <div class="metric-card">
        <div class="metric-top">
          Sales this month <span class="metric-icon blue"><Receipt :size="19" /></span>
        </div>
        <strong>{{ money(stats.month_sales) }}</strong
        ><small>Current calendar month</small>
      </div>
      <div class="metric-card">
        <div class="metric-top">
          Inventory value <span class="metric-icon purple"><Boxes :size="19" /></span>
        </div>
        <strong>{{ money(stats.inventory_value) }}</strong
        ><small>Acquisition cost · includes quarantine</small>
      </div>
      <div class="metric-card">
        <div class="metric-top">
          Completed transactions <span class="metric-icon amber"><Clock :size="19" /></span>
        </div>
        <strong>{{ stats.transactions }}</strong
        ><small>All time · current branch</small>
      </div>
    </div>
    <div class="dashboard-grid">
      <section class="panel chart-panel">
        <div class="panel-heading">
          <div>
            <h2>Sales performance</h2>
            <p>Your last seven business days</p>
          </div>
          <span class="legend-dot">Sales</span>
        </div>
        <div class="chart-wrap">
          <canvas ref="canvas" aria-label="Sales over the last seven days" role="img" />
        </div>
      </section>
      <section class="panel attention-panel">
        <div class="panel-heading">
          <div>
            <h2>Needs your attention</h2>
            <p>Stay a step ahead</p>
          </div>
          <span class="metric-icon amber"><AlertTriangle :size="18" /></span>
        </div>
        <div class="expiry-callout">
          <span class="expiry-number">{{ stats.expired_units }}</span>
          <div>
            <strong>Expired units</strong>
            <p>Blocked from sale automatically</p>
          </div>
        </div>
        <Link
          v-if="can('inventory.view')"
          href="/inventory?filter=expired"
          class="btn secondary full"
          >Review expired stock <ArrowRight :size="16"
        /></Link>
        <div class="alert-list">
          <p v-for="alert in alerts" :key="alert.id">
            <span class="status-dot amber-dot" />{{ alert.message }}
          </p>
          <p v-if="!alerts.length" class="muted">
            No open alerts. Scheduled checks keep this list up to date.
          </p>
        </div>
        <Link v-if="can('inventory.view')" href="/notifications" class="text-link"
          >View notifications <ArrowUpRight :size="14"
        /></Link>
      </section>
    </div>
    <div class="dashboard-grid">
      <section class="panel">
        <div class="panel-heading">
          <div>
            <h2>Recent sales</h2>
            <p>The latest activity at your branch</p>
          </div>
          <Link v-if="can('sales.view')" href="/sales" class="text-link"
            >View all <ArrowRight :size="15"
          /></Link>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Invoice</th>
                <th>Date & time</th>
                <th class="number">Total</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="sale in recent" :key="sale.id">
                <td>
                  <Link v-if="can('sales.view')" :href="'/sales/' + sale.id" class="table-link">{{
                    sale.invoice_number
                  }}</Link
                  ><span v-else>{{ sale.invoice_number }}</span>
                </td>
                <td class="muted">{{ date(sale.created_at) }}</td>
                <td class="number strong">{{ money(sale.total) }}</td>
                <td><span class="badge green">Completed</span></td>
              </tr>
              <tr v-if="!recent.length">
                <td colspan="4" class="empty">Your first completed sale will appear here.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
      <section class="panel quick-panel">
        <div class="panel-heading">
          <div>
            <h2>Quick actions</h2>
            <p>Keep your day moving</p>
          </div>
        </div>
        <Link v-if="can('purchases.create')" href="/purchases" class="quick-action"
          ><span class="metric-icon blue"><Receipt :size="20" /></span
          ><span
            ><strong>Order & receive stock</strong
            ><small>Purchases, batches and suppliers</small></span
          ><ArrowUpRight :size="18" /></Link
        ><Link v-if="can('inventory.view')" href="/inventory?filter=near" class="quick-action"
          ><span class="metric-icon amber"><Clock :size="20" /></span
          ><span
            ><strong>Check batch expiry</strong><small>Review approaching expiry dates</small></span
          ><ArrowUpRight :size="18" /></Link
        ><Link v-if="can('accounting.view')" href="/accounting" class="quick-action"
          ><span class="metric-icon purple"><Receipt :size="20" /></span
          ><span
            ><strong>Review the books</strong><small>Trial balance and reconciliation</small></span
          ><ArrowUpRight :size="18"
        /></Link>
      </section>
    </div>
  </AppLayout>
</template>
