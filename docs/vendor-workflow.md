# Vendor workflow

Verified 27 November 2026 on `feature/rebuild-seller-module` against the restored local database. The seller and administrator workspaces are native Laravel/Blade modules and preserve the existing marketplace schema.

## 1. Application and shop onboarding

1. A vendor registers through `/register-shop`.
2. Registration validates the owner and shop details, rejects duplicate email or phone values, hashes the password, and creates the user and shop in one database transaction.
3. New shops begin in `pending`, unpublished, unapproved, and unverified state. The vendor may sign in and use the restricted onboarding dashboard, but the shop and products are not public.
4. The vendor completes their profile, shop settings, and verification submission from the seller workspace.
5. An administrator reviews the application and verification information. Approval, rejection, activation, suspension, deactivation, and verification decisions are recorded in the seller audit trail.
6. A shop becomes publicly discoverable only when its lifecycle is active and the legacy approval, publishing, and verification flags all permit visibility.

## 2. Seller workspace

The seller workspace is available at `/seller` and exposes only records belonging to the authenticated seller's shop.

- **Dashboard:** product, order, sales, balance, package, review, and fulfilment summaries.
- **Profile and shop:** owner profile, shop identity, contact details, media, policies, social links, payout details, and verification submission.
- **Products:** create and maintain physical or digital products, translations, media, variations, stock, price, shipping, tax, SEO, and downloadable files.
- **Product review:** new or edited seller products remain draft/pending until submitted and approved. Rejected or change-requested products expose the review reason and can be corrected and resubmitted.
- **Orders:** view only orders containing the seller's shop items, update allowed forward fulfilment states, add tracking information, and download invoices.
- **Refunds:** inspect shop-owned refund requests and accept or reject through CSRF-protected POST actions.
- **Coupons:** create, edit, and delete coupons limited to products owned by the seller's shop.
- **Customers:** respond to product questions, reviews, and conversations, and manage notifications.
- **Files:** upload, list, and delete only files owned by the authenticated user.
- **Packages:** review available plans, payment history, current expiry and upload allowance, and initiate supported online or offline payment flows.
- **Earnings and payouts:** inspect commission history and available balance, maintain payout details, and submit payout requests.

Bulk product import is intentionally not exposed because the previous route targeted a missing controller and could not guarantee ownership or review-state enforcement.

## 3. Package activation

1. The seller selects an active package.
2. Online payments are activated only by a verified gateway success path; offline payments remain pending for administrator review.
3. Activation is transactional and idempotent, applies the package's duration, commission, product limit, and features, and records the payment reference.
4. Replaying the same successful transaction does not extend the subscription twice.
5. Expired packages remain visible to the seller and prevent actions that exceed the applicable plan limits.

## 4. Product publication

1. A seller creates a physical or digital product for their own shop.
2. The product begins as a draft or pending-review record and cannot be forced into an approved state by request payloads.
3. Submission creates a review event for administrators.
4. An administrator can approve, reject, or request changes with a reason.
5. Only approved, published products from an active, approved, verified, and published shop are visible on public marketplace endpoints.
6. Every review transition is retained in product review history.

## 5. Orders, commission, and payouts

1. Customer checkout keeps order lines associated with their product and shop.
2. Seller order queries are constrained by the authenticated shop, including detail, invoice, fulfilment, payment-view, and refund actions.
3. Fulfilment transitions move forward through the allowed states; sellers cannot arbitrarily rewrite completed or unrelated orders.
4. Finalized commission data is read from the order and commission history rather than recomputed on every dashboard request.
5. A payout request atomically reserves available funds, preventing duplicate withdrawal of the same balance.
6. Administrators can approve, pay, reject, or cancel using allowed transitions. Settlement data and actor history remain auditable.

## 6. Administrator workflow

The seller administration area is under `/admin` and uses the existing permission system.

- Seller application list, creation, profile editing, detail view, status filters, and shop settings.
- Lifecycle approval, activation, deactivation, suspension, rejection, verification approval/rejection, and publication controls.
- Seller product review queue and review history.
- Seller order and commission inspection.
- Package creation, editing, ordering, activation, features, and payment review.
- Offline package-payment approval.
- Payout queue, transition actions, payment reference, and finance history.
- Impersonation is constrained to authorized administrators and is audit logged.

Destructive and state-changing actions use POST, PATCH, or DELETE semantics with CSRF protection. The obsolete upload activation endpoint containing remote database-deletion code has been removed.

## 7. Public marketplace

- `POST /api/v1/shop/register`
- `GET /api/v1/all-shops`
- `GET /api/v1/shop/{slug}`
- `GET /api/v1/shop/{slug}/home`
- `GET /api/v1/shop/{slug}/coupons`
- `GET /api/v1/shop/{slug}/products`

Public shop and product services apply lifecycle and approval scopes consistently. Visitors can browse active shops, use filters, view coupons, and purchase visible products without gaining access to vendor back-office data.

## 8. Local verification accounts

These accounts exist only in the restored local staging database and are not application defaults:

- Administrator: `admin.local@elite.test`
- Seller: `seller.local@elite.test`

Passwords are documented in the local handoff, not committed to source control.
