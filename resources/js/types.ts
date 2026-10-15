export interface Warehouse {
  id: number;
  name: string;
  branch_id: number;
}
export interface Branch {
  id: number;
  name: string;
  code: string;
  warehouses: Warehouse[];
}
export interface Shared {
  [key: string]: unknown;
  auth: {
    user: { id: number; name: string; email: string };
    permissions: string[];
    branches: Branch[];
    branch_id: number;
  };
  flash: { success?: string };
  business: { name: string; currency: string; timezone: string };
}
export interface Pagination<T> {
  data: T[];
  current_page: number;
  last_page: number;
  total: number;
  links: { url: string | null; label: string; active: boolean }[];
}
export interface Medicine {
  id: number;
  name: string;
  sku: string;
  barcode?: string;
  generic_name?: string;
  selling_price: string;
  tax_basis_points: number;
  prescription_required: boolean;
  available_stock?: number;
}
export interface Party {
  id: number;
  name: string;
}
export interface Batch {
  id: number;
  lot_number: string;
  expires_at: string | null;
  quantity: number;
  received_quantity: number;
  quarantined_quantity: number;
  unit_cost: string;
  status: string;
  medicine: Medicine;
  warehouse: Warehouse;
}
export interface Allocation {
  id: number;
  quantity: number;
  returned_quantity: number;
  unit_cost: string;
  batch: Batch;
}
export interface SaleItem {
  id: number;
  description: string;
  quantity: number;
  unit_price: string;
  unit_discount: string;
  unit_tax: string;
  total: string;
  allocations: Allocation[];
}
export interface Sale {
  id: number;
  invoice_number: string;
  total: string;
  subtotal: string;
  discount: string;
  tax: string;
  cost: string;
  change: string;
  created_at: string;
  customer: Party | null;
  cashier: Party;
  branch: Branch;
  items: SaleItem[];
  payments: { id: number; method: string; amount: string }[];
  returns: { id: number; total: string; reason: string }[];
}
export interface OrderItem {
  id: number;
  medicine_id: number;
  quantity: number;
  received_quantity: number;
  unit_cost: string;
  medicine: Medicine;
}
export interface Order {
  id: number;
  status: string;
  created_at: string;
  supplier: Party;
  items: OrderItem[];
  receipts: { id: number; total: string; batches: Batch[] }[];
}
