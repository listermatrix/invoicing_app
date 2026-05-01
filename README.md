# Invoice API

A RESTful invoicing backend built with **Laravel 13** and **Laravel Sanctum**. Supports invoice management, a shared product/inventory catalog, customer management, role-based authorization, and inventory tracking to prevent overselling.

---

## Table of Contents

- [Requirements](#requirements)
- [Setup](#setup)
- [Authentication](#authentication)
- [Roles & Authorization](#roles--authorization)
- [API Endpoints](#api-endpoints)
- [Sample Requests](#sample-requests)
- [Inventory Tracking](#inventory-tracking)
- [Invoice Status Flow](#invoice-status-flow)
- [Assumptions](#assumptions)
- [Running Tests](#running-tests)
- [Test Coverage](#test-coverage)

---

## Requirements

- PHP 8.3+
- Composer
- MySQL 8+ or PostgreSQL 14+

---

## Setup

```bash
# 1. Install dependencies
composer install

# 2. Copy and configure environment
cp .env.example .env

# Configure your database in .env:
# DB_DATABASE=invoice_api
# DB_USERNAME=root
# DB_PASSWORD=

# 3. Generate app key
php artisan key:generate

# 4. Run migrations
php artisan migrate

# 5. Seed demo data (creates 1 admin + 9 staff users, 5 customers, 10 products)
php artisan db:seed

# 6. Serve
php artisan serve
```

API base URL: `http://localhost:8000/api/v1`

### Demo Credentials (after seeding)

| Role  | Email             | Password  |
|-------|-------------------|-----------|
| Admin | admin@example.com | password  |
| Staff | staff@example.com | password  |

> User accounts are created exclusively via the seeder. There is no public registration endpoint.

---

## Authentication

This API uses **Laravel Sanctum** token-based authentication.

After logging in, include the token in all subsequent requests:

```
Authorization: Bearer <your-token>
```

The token is returned in the `data.token` field on login only. It is not returned on the `GET /auth/me` endpoint.

---

## Roles & Authorization

The system has three roles: `admin`, `staff`, and `user`. Accounts are created via seeder with roles assigned at that point.

| Action | Staff | Admin |
|--------|-------|-------|
| Login | ✅ | ✅ |
| View own invoices | ✅ | ✅ |
| View all invoices | ❌ | ✅ |
| Create invoice | ✅ | ✅ |
| Update own invoice | ✅ | ✅ |
| Update any invoice | ❌ | ✅ |
| Delete any invoice | ❌ | ✅ |
| Manage customers | ✅ | ✅ |
| Manage products | ✅ | ✅ |

---

## API Endpoints

### Auth

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/api/v1/auth/login` | ❌ | Login and receive token |
| POST | `/api/v1/auth/logout` | ✅ | Revoke current token |
| GET | `/api/v1/auth/me` | ✅ | Get authenticated user |

### Customers *(shared catalog)*

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/api/v1/customers` | ✅ | List all customers |
| POST | `/api/v1/customers` | ✅ | Create a customer |
| GET | `/api/v1/customers/{id}` | ✅ | Get a customer |
| PUT | `/api/v1/customers/{id}` | ✅ | Update a customer |
| DELETE | `/api/v1/customers/{id}` | ✅ | Delete a customer |

### Products *(shared catalog with inventory)*

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/api/v1/products` | ✅ | List all products |
| POST | `/api/v1/products` | ✅ | Create a product |
| GET | `/api/v1/products/{id}` | ✅ | Get a product |
| PUT | `/api/v1/products/{id}` | ✅ | Update / restock a product |
| DELETE | `/api/v1/products/{id}` | ✅ | Delete a product |

### Invoices

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/api/v1/invoices` | ✅ | List invoices (admin sees all) |
| POST | `/api/v1/invoices` | ✅ | Create an invoice |
| GET | `/api/v1/invoices/{id}` | ✅ | Get invoice with items |
| PUT | `/api/v1/invoices/{id}` | ✅ | Update invoice / change status |
| DELETE | `/api/v1/invoices/{id}` | ✅ Admin only | Delete an invoice |

---

## Sample Requests

### Login

```json
POST /api/v1/auth/login
{
    "email": "admin@example.com",
    "password": "password"
}
```

### Create Product

```json
POST /api/v1/products
{
    "name": "Wireless Headphones",
    "description": "Noise-cancelling over-ear headphones",
    "unit_price": 149.99,
    "stock_quantity": 50
}
```

### Create Invoice (Draft — no stock deducted)

```json
POST /api/v1/invoices
{
    "customer_id": 1,
    "issue_date": "2024-01-15",
    "due_date": "2024-02-15",
    "status": "draft",
    "notes": "Payment via bank transfer",
    "items": [
        {
            "product_id": 1,
            "description": "Wireless Headphones x2",
            "unit_price": 149.99,
            "quantity": 2
        },
        {
            "description": "Delivery fee",
            "unit_price": 10.00,
            "quantity": 1
        }
    ]
}
```

### Send an Invoice (transitions draft → sent, deducts stock)

```json
PUT /api/v1/invoices/1
{
    "status": "sent"
}
```

### Cancel an Invoice (restores stock)

```json
PUT /api/v1/invoices/1
{
    "status": "cancelled"
}
```

### Filter Invoices by Status

```
GET /api/v1/invoices?status=paid
```

---

## Inventory Tracking

Stock is managed at the **product** level via `stock_quantity`.

| Event | Stock Effect |
|-------|-------------|
| Create invoice as `draft` | ❌ No deduction |
| Create invoice as `sent` | ✅ Deducted immediately |
| Update status `draft → sent` | ✅ Deducted |
| Update status `sent → cancelled` | ↩️ Restored |
| Update status `cancelled → sent` | ✅ Deducted again |
| Delete a `draft` invoice | ❌ No change |
| Delete a `sent` invoice | ↩️ Restored |
| Replace items on a `sent` invoice | ↩️ Old stock restored → ✅ New stock deducted |

If a product has insufficient stock when deduction is attempted, the entire request fails with `422 Unprocessable Content` and no stock is modified.

Stock deduction uses **pessimistic locking** (`lockForUpdate`) inside a database transaction to safely handle concurrent requests.

---

## Invoice Status Flow

```
draft → sent → paid
              ↓
           cancelled
```

- `draft` — Work in progress, not committed
- `sent` — Issued to customer, stock committed
- `paid` — Payment received
- `overdue` — Past due date (can be set manually)
- `cancelled` — Voided, stock released

---

## Assumptions

The following design decisions were made where the brief was silent:

1. **No public registration** — User accounts are managed internally via the seeder. This prevents unauthorized account creation.

2. **Shared product & customer catalog** — Products and customers belong to the business, not individual users. Any authenticated user can view and manage them. In a production system this would be scoped to an organisation/tenant.

3. **`user_id` on invoices is the creator** — Used both for scoping (staff see only their own invoices) and authorization (only the creator or admin can update).

4. **Stock is only deducted on `sent` status** — A `draft` invoice is a work in progress and does not commit inventory. This matches industry-standard invoicing behaviour (QuickBooks, Xero, etc.).

5. **`product_id` is optional on invoice items** — Items can be free-text (e.g. a service fee or delivery charge) without referencing a product. Stock tracking only applies when a `product_id` is provided.

6. **`amount` is computed and stored** — Each item's `amount = unit_price × quantity` is calculated automatically and stored for historical accuracy. If a product's price changes later, existing invoice lines are unaffected.

7. **Invoice numbers are auto-generated** — Format: `INV-YYYYMM-XXXX` (e.g. `INV-202401-0001`). They are not user-supplied.

8. **Deletion is admin-only** — Staff can manage their own invoices but cannot delete them, ensuring audit integrity.

---

## Running Tests

### Setup Test Environment

Create a `.env.testing` file for isolated test runs:

```env
APP_ENV=testing
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
```

Or use a separate MySQL database:

```env
DB_CONNECTION=mysql
DB_DATABASE=invoice_api_test
```

### Run All Tests

```bash
php artisan test
```

### Run with Pest directly

```bash
./vendor/bin/pest
```

### Run a Specific File

```bash
./vendor/bin/pest tests/Feature/InvoiceTest.php
```

### Run with Coverage

```bash
./vendor/bin/pest --coverage
```

---

## Test Coverage

| File | What is Tested |
|------|----------------|
| `AuthTest.php` | Login (valid/invalid credentials), logout, `me` endpoint |
| `CustomerTest.php` | CRUD operations, validation, authentication requirement |
| `ProductTest.php` | CRUD operations, stock validation, authentication requirement |
| `InvoiceTest.php` | Creation (draft vs sent stock behaviour), total calculation, validation, listing (staff vs admin scope), status filtering, authorization (creator update, admin update, staff delete blocked, admin delete), stock transitions (draft→sent, sent→cancelled, delete draft, delete sent) |

### Test Helpers (`tests/Pest.php`)

```php
actingAsUser();  // authenticates as a staff user
actingAsAdmin(); // authenticates as an admin user
```
