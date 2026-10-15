<?php

return ['timezone' => env('BUSINESS_TIMEZONE', 'Asia/Karachi'), 'currency' => env('BUSINESS_CURRENCY', 'PKR'), 'expiry_warning_days' => (int) env('EXPIRY_WARNING_DAYS', 90), 'max_discount_basis_points' => 2000];
