# Vendor workflow

This document describes the vendor workflow that is present in this repository and the restored staging database as of 20 November 2026.

## Current operational status

The public vendor-facing flow is implemented:

- Visitors can browse `/all-shops`.
- Visitors can open a published shop at `/shop/{slug}`, view its coupons, and filter its products.
- A prospective vendor can submit `/register-shop`.
- The API validates the owner and shop fields, rejects an existing email or phone number, hashes the password, creates a `seller` user, creates a uniquely slugged shop, and links the user to that shop.
- A registered shop starts unverified. Public shop APIs only expose shops where `published`, `approval`, and `verification_status` are all enabled.
- Web login recognizes a seller account and attempts to redirect it to `/seller`.

The restored database currently has one visible in-house shop and no seller users. Public shop browsing is therefore testable, while a pre-existing third-party vendor account is not available in the dump.

## Registration to storefront lifecycle

1. The vendor opens `/register-shop` and supplies their name, phone, email, password and confirmation, plus the shop name, phone, and address.
2. `POST /api/v1/shop/register` validates all fields. Passwords must be at least eight characters and the confirmation must match.
3. `ShopRegistrationService` rejects duplicate user email or phone values.
4. A user is created with `user_type=seller`, a hashed password, and a verified email timestamp.
5. A shop is created with a unique slug and `verification_status=0`, then its ID is saved on the seller user.
6. The frontend sends the vendor to `/register-shop-confirmation`.
7. Before the shop can be returned by public shop endpoints, an administrator must complete the intended approval, verification, and publishing steps.
8. Once visible, customers can use `/shop/{slug}`, `/shop/{slug}/coupons`, and `/shop/{slug}/products/{categorySlug?}`. The shop product endpoint supports category, brand, keyword, price, attribute, and sort filters.

## Intended authenticated seller workspace

`routes/seller.php` defines the intended seller capabilities:

- Dashboard and profile management.
- Shop settings and verification submission.
- Physical and digital product creation, editing, publishing, duplication, deletion, import, and export.
- Orders, pickup orders, invoices, delivery status, payment status, and tracking information.
- Coupons, product questions, product reviews, notifications, and uploaded files.
- Seller packages and purchase history.
- Payout requests, payout settings, and commission history.

It also defines the intended admin vendor controls:

- Create, edit, approve, publish, delete, and impersonate sellers.
- Review and approve or reject verification requests.
- Review seller products and orders.
- Process payout requests and inspect payout and commission history.
- Manage seller packages and global shop settings.

## Known blocker

The authenticated seller and admin vendor-management routes import controllers from `App\Addons\Multivendor`, but this repository contains no `app/Addons/Multivendor` directory. Consequently, those route-backed screens are not currently executable. Restoring the matching multivendor add-on source, or rebuilding those controllers and views against the existing schema, is required before the end-to-end vendor back office can be called functional.

The V1 registration service also does not send an administrator notification. An admin-notification step should be added when the vendor back office is restored so new applications cannot be missed.

## Public shop API

- `POST /api/v1/shop/register`
- `GET /api/v1/all-shops`
- `GET /api/v1/shop/{slug}`
- `GET /api/v1/shop/{slug}/home`
- `GET /api/v1/shop/{slug}/coupons`
- `GET /api/v1/shop/{slug}/products`
