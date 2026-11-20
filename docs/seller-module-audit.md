# Seller module audit

Date: 20 November 2026  
Branch: `feature/rebuild-seller-module`  
Runtime: Laravel 10, PHP 8.2, Spatie Laravel Permission 5.11

## Executive summary

The repository contains the marketplace's seller data model, public shop APIs, public registration form, seller route names, admin navigation hooks, payment entry points, refund support, product catalog, orders, commission columns, payout tables, and package tables. It does not contain the `App\Addons\Multivendor` controllers or seller Blade views referenced by `routes/seller.php`. This makes every authenticated seller route and most admin seller routes non-executable. Even `php artisan route:list --path=seller` currently fails while reflecting the missing `SellerController`.

The correct repair is a native restoration using the existing namespace and route contracts. No second seller system or replacement product/order schema is required.

## Baseline

The unmodified feature-branch baseline executed 180 tests:

- 176 passed.
- 4 failed before seller-module implementation.
- The failures are stale architecture assertions for the active SPA homepage, blog query implementation, super-admin permission migration implementation, and one category fixture path.
- The focused public shop suite already passes, including pending-shop registration and public visibility checks.

## Restored database inventory

The restored staging schema contains 105 tables. Relevant live record counts at audit time:

| Entity | Count |
| --- | ---: |
| Users | 7 |
| Seller users | 0 |
| Shops | 1 |
| Seller packages | 0 |
| Package payments | 0 |
| Seller payouts | 0 |
| Commission history | 0 |

The single shop is the in-house administrator shop. No seller data has to be overwritten or reclassified.

## Existing working functionality

- Public routes: `/register-shop`, `/register-shop-confirmation`, `/all-shops`, `/shop/{slug}`, shop coupons, and shop products.
- Public APIs: shop registration, visible-shop listing, shop details, shop home data, coupons, and filtered products.
- `ShopPublicService` consistently requires `published=1`, `approval=1`, and `verification_status=1` for public shops.
- `Product::frontendVisible()` requires `published=1` and `approved=1`.
- Existing product creation supports the current categories, brand, translations, variations, attributes, taxes, media IDs, shipping, SEO, pricing, stock, and digital-product fields.
- Orders are already split by `shop_id`, and order details retain product ownership.
- Upload records are tied to `user_id`; seller upload listing and deletion checks already use the authenticated owner.
- Refund policies and refund request tables are present and covered by passing tests.
- Notification classes already exist for seller registration, approval, verification, invoices, and orders.
- Spatie Permission is installed and the application has a `Super Admin` gate override.
- Existing permissions include `show_sellers`, `show_seller_products`, `show_seller_orders`, package, payout, commission, and offline-package-payment permissions.
- Existing payment routes include seller package IDs, but activation and idempotency logic must be restored before they can be exposed as complete.

## Missing components

- All controllers imported from `App\Addons\Multivendor\Http\Controllers\Admin`.
- All controllers imported from `App\Addons\Multivendor\Http\Controllers\Seller`.
- Seller layout, sidebar, dashboard, product, order, shop, package, payout, commission, coupon, query, review, notification, and upload views.
- Admin seller CRUD, lifecycle, verification, product review, package, commission, payout, and seller-order views.
- Policies for seller-owned products, orders, coupons, uploads, shops, and payouts.
- An explicit seller application status endpoint and pending-onboarding dashboard.
- Auditable admin action storage.
- Product review status/reason/history beyond legacy `approved` and `published` flags.
- Independent shop lifecycle status beyond the legacy three booleans.
- Package feature/status/currency/display-order fields and subscription activation safeguards.
- Seller notifications for product review, package expiry, payout transitions, and shop lifecycle changes.

## Verified schema and mapping

### Users

`users` already has `user_type`, `shop_id`, `email_verified_at`, `phone_verified_at`, `banned`, password and soft-delete fields. Email is uniquely indexed; phone is not unique at the database level.

### Shops

`shops` already has:

- owner linkage: `user_id`;
- legacy visibility: `approval`, `published`, `verification_status`;
- verification payload: `verification_info`;
- profile: name, slug, phone, address, logo, banners and metadata;
- package: `seller_package_id`, `product_upload_limit`, `package_invalid_at`;
- finance: `commission`, `current_balance`, cash/bank payout fields;
- soft deletes.

Lifecycle mapping will preserve the legacy booleans:

| New lifecycle | approval | published | Public |
| --- | ---: | ---: | --- |
| pending | 0 | 0 | no |
| approved | 1 | 0 | no |
| active | 1 | 1 | only when verified |
| inactive | 1 | 0 | no |
| suspended | unchanged | 0 | no |
| rejected | 0 | 0 | no |

`verification_status` remains an independent document-verification decision.

### Products

`products` already has `shop_id`, `published`, `approved`, digital/product catalog fields, and soft deletes. Legacy approved in-house products must remain approved. A nullable review status and rejection/review metadata can be added safely and backfilled from the two legacy flags.

### Orders and earnings

`orders` already has `shop_id`, `grand_total`, `admin_commission`, `seller_earning`, `commission_percentage`, `commission_calculated`, payment/delivery/refund fields, tracking fields, and soft deletes. `order_details` links each line to a product. `commission_histories` stores order/shop/admin commission/seller earning. Seller reporting must query the authenticated shop and use paid/eligible order state without recomputing or double-counting finalized commission rows.

### Packages and payouts

`seller_packages` has name, price (`amount`), commission, upload limit, logo, and duration. `seller_package_payments` has payment method/details, approval, receipt, transaction ID, and user/package IDs. `seller_payouts` has shop ownership, requested/paid amounts, status, method, transaction code, and notes.

## Broken routes and conflicts

- Every controller class imported from the missing multivendor add-on causes runtime failure.
- Several destructive routes use GET: seller/product deletion, digital-product deletion, coupon deletion, uploaded-file deletion, admin seller deletion, verification approve/reject, and package deletion.
- Seller routes call shared order mutation methods that fetch orders by arbitrary ID without seller ownership checks.
- The seller sidebar is absent.
- The admin Seller menu is hidden behind `addon_is_activated('multi_vendor')`, but the database has no multivendor add-on row; only Refund is active.
- `routes/admin.php` also contains a differently cased `App\Addons\MultiVendor` diagnostic controller reference.
- Seller upload views still use the absent `addon:multivendor` view namespace.

## Security findings

- `IsSeller` returns 404 for all pending sellers because it requires `approval=1`; this prevents the required restricted onboarding dashboard.
- Public registration currently sets `approval=1` and `email_verified_at=now()`. Both violate the required independent approval and verification lifecycle.
- Registration is not transactional and can leave an orphaned user if shop creation fails.
- Registration duplicate detection is application-only and is race-prone for phone values.
- `Product` and several financial models use unrestricted `$guarded=[]`; seller code must never mass-assign request payloads.
- Shared order mutation methods do not scope seller requests by shop and expose IDOR risk if reused directly.
- Existing GET mutation routes violate CSRF-safe HTTP semantics.
- `AizUploadController::uploaded_files()` contains obsolete remote activation code capable of issuing `DROP DATABASE`; it must be removed and never routed.
- Public registration needs a named rate limiter.
- Seller financial actions need explicit permissions, transactions, decimal-safe aggregation, allowed transitions, and idempotency keys.

## Migration risks

- Existing tables are populated production-shaped tables; migrations must only add nullable/defaulted columns and indexes.
- Existing approved products must be backfilled as approved without triggering re-review.
- Existing in-house shop visibility must remain unchanged.
- Package payment history may contain legacy gateway payloads in other databases; new idempotency constraints must tolerate null legacy transaction IDs.
- `users.phone` cannot be made unique until duplicates are audited in every deployment environment. Application-level locking and retry-safe registration will be used first.
- No migration may drop, truncate, recreate, or rename existing business tables.

## Implementation plan

1. Add lifecycle, review, audit, package-feature, and idempotency schema using additive migrations.
2. Add enums/services/policies that centralize shop lifecycle, product review, seller ownership, package activation, earnings, and payouts.
3. make registration transactional, pending by default, verification-aware, rate-limited, and notification-backed.
4. Restore seller and admin controllers in the compatibility namespace used by existing routes.
5. Replace unsafe GET mutations with POST/PATCH/DELETE while preserving route names where practical.
6. Build the seller layout/dashboard and modules using existing Blade/AIZ styling and real database aggregates.
7. Restore admin seller CRUD, lifecycle controls, product review queue, packages, orders, commissions, and payouts with Spatie permissions.
8. Enforce seller product limits and server-side approval state.
9. Add order/refund/coupon/upload ownership enforcement.
10. Add comprehensive feature and policy tests, then run the full regression suite and browser smoke tests.

## Completion boundary

Payment gateway success will never be simulated. Package activation will only trust an administrator action or an existing verified transaction/callback path. POS will remain hidden until every query and mutation can be proven shop-scoped; that limitation will be documented rather than exposing the current admin POS routes to sellers.
