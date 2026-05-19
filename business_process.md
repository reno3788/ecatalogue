# Business Process Documentation
## B2B E-Procurement System

---

## 1. Overview

This system is a **B2B Electronic Procurement Platform** that enables buyer companies to browse a digital product catalog, place requests for quotation (RFQs), negotiate pricing with suppliers, and manage the full order lifecycle from purchase order through shipment and invoice.

The platform supports two distinct checkout modes:
- **Direct Order** — Buyers browse and checkout directly through the portal.
- **PunchOut** — External procurement systems (e.g., SAP, Coupa, Ariba) launch the catalog through a cXML/OCI gateway, and the cart is returned to the external system.

---

## 2. Actors & Roles

| Role | Description |
|---|---|
| **Admin** | Full system access. Can manage all settings, users, products, companies, orders, workflows, and configurations. |
| **Buyer** | Belongs to a company. Browses catalog, adds to cart, places RFQs, views/negotiates quotations, uploads POs. |
| **Buyer Requester** | Same as Buyer, typically a limited sub-role for requesting purchases on behalf of others. |
| **Supplier Admin** | Manages supplier-side operations, reviews POs. |
| **Supplier Approver** | Internal approver in the supplier side; signs off on quotation offers before they reach the buyer. |
| **Supplier Processor** | Handles day-to-day quotation processing and shipment management. |

---

## 3. System Modules

```
┌─────────────────────────────────────────────────────────────────┐
│                    E-PROCUREMENT PLATFORM                        │
├──────────────┬──────────────┬───────────────┬───────────────────┤
│   Catalog    │    Cart &    │    Order &    │     Admin Panel   │
│  Management  │  Checkout    │  Negotiation  │   (Full Control)  │
├──────────────┼──────────────┼───────────────┼───────────────────┤
│  Products    │  Cart Items  │  RFQ → PO     │  User Management  │
│  Categories  │  Checkout    │  Quotation    │  Company Config   │
│  Pricing     │  PunchOut    │  Bargaining   │  Workflow Engine  │
│  Visibility  │  Gateway     │  Shipment     │  Product/Catalog  │
│              │              │  Invoice      │  Price Lists      │
│              │              │  Audit Logs   │  Settings/SMTP    │
└──────────────┴──────────────┴───────────────┴───────────────────┘
```

---

## 4. Catalog & Pricing

### 4.1 Product Catalog

1. Products are created and managed by the Admin with fields: name, SKU, brand, description, base price, images, and categories.
2. Products can be marked **Active/Inactive**. Only active products are visible in the buyer catalog.
3. Products are grouped into a **hierarchical category tree** (parent → child categories), each with a URL-friendly slug.
4. Products may have a **tolerance percentage** — the maximum discount allowed during buyer bargaining.

### 4.2 B2B Dynamic Pricing

The pricing engine (`DynamicPricingService`) resolves the effective product price for each buyer company:

```
[Base Price]
    ↓
[Company-Specific Price List?] → Yes → Use custom_price from ClientPriceList
    ↓ No
[Use Base Price]
```

- Admin can set a **Client Price List** per company per product, overriding the base catalog price.
- When a buyer views the catalog or product detail page, they see only their company's negotiated price.

### 4.3 Catalog Visibility

- Admin can control which products or categories are visible per company through `CatalogVisibility` rules.

---

## 5. Cart & Checkout Process

### 5.1 Standard (Direct Order) Flow

```
[Buyer browses Catalog]
        ↓
[Adds products to Cart]
        ↓
[Proceeds to Checkout]
        ↓
[System creates Order with status = "RFQ"]
[Cart is cleared]
        ↓
[Order Confirmation email sent to Buyer]
        ↓
[Buyer Dashboard shows new order awaiting review]
```

- Price is **frozen at time of cart creation**, not recalculated at checkout.
- Cart items are transferred to `OrderItems` during checkout.

### 5.2 PunchOut Flow (External Procurement Systems)

```
[External System (ERP/ERP) launches PunchOut session]
        ↓
[Buyer is redirected into catalog with punchout_user flag]
        ↓
[Buyer browses and adds to cart normally]
        ↓
[Buyer clicks "Checkout" / "Return Cart"]
        ↓
[System builds cXML/OCI return payload via gateway adapter]
        ↓
[Cart payload is returned to external system]
        ↓
[External system creates a PO using the payload]
        ↓
[PO Reference is sent back into this system as a PunchOut Order]
```

- PunchOut gateway is configured **per company** (gateway name, URL, identity, shared secret).
- Bargaining is **not allowed** for PunchOut orders.
- PunchOut orders carry a `punchout_po_reference` field on the Order.

---

## 6. Order Lifecycle

### 6.1 Order Status Flow

```
                    ┌───────────┐
                    │    RFQ    │ ← Buyer places order
                    └─────┬─────┘
                          │
            ┌─────────────┼──────────────┐
            ▼             ▼              ▼
      [No Workflow]  [Has Workflow]   [Supplier offers directly]
            │             │
            ▼             ▼
       Quotation     Submitted
                          │
                    [Approval Steps]
                          │ All steps complete
                          ▼
                      Quotation ←────────────── Supplier submits offer
                          │
              ┌───────────┼──────────────┐
              ▼           ▼              ▼
       [Buyer Accepts] [Buyer Bargains] [Buyer Rejects]
              │           │
              ▼           ▼
             PO         RFQ (returns for new offer)
              │
    ┌─────────┴──────────┐
    ▼                    ▼
Partially Shipped     Shipped
              │
              ▼
           Invoiced
              │
              ▼
           Completed
```

> Orders can also be **Rejected** at any stage by an Admin or Supplier.

### 6.2 Status Definitions

| Status | Description |
|---|---|
| **RFQ** | Request for Quotation. Buyer submitted an order; awaiting supplier pricing. |
| **Submitted** | Supplier has submitted an offer; routed through internal approval workflow. |
| **Approved** | Internal workflow fully approved. Order is ready for Quotation. |
| **Quotation** | Supplier's official price offer sent to Buyer as a PDF quotation email. |
| **PO** | Buyer accepted the quotation and uploaded a Purchase Order document. |
| **Partially Shipped** | Some items have been shipped, but not all. |
| **Shipped** | All items have been dispatched. Buyer receives delivery note email. |
| **Invoiced** | Supplier uploaded invoice. Buyer is notified. |
| **Completed** | Order fully closed. |
| **Rejected** | Order rejected with a reason; buyer is notified. |

---

## 7. Quotation & Negotiation Process

### 7.1 Supplier Submits Offer (RFQ → Quotation)

```
Admin/Supplier opens Order in Dashboard
        ↓
Reviews items and sets [Supplier Offered Price] per item
        ↓
Optionally adds a comment
        ↓
Submits offer
        ↓
[System checks: Is there an active Approval Workflow?]
    Yes → Status = "Submitted" (routes through workflow)
    No  → Status = "Quotation"
        ↓
[If Quotation] → Email with PDF Quotation sent to Buyer
               → Buyer notified in-app
```

### 7.2 Approval Workflow (Submitted → Quotation)

The system supports **configurable multi-step approval workflows** per company:

```
[Order enters "Submitted" status]
        ↓
[WorkflowService resolves applicable workflow]
  - Criteria: company_id, min_amount threshold
        ↓
[Workflow steps resolved in order (sort_order)]
  Each step has a designated approver role/user
        ↓
[Sequential or parallel approval depending on config]
        ↓
[When all steps approved] → Order promoted to "Quotation"
```

- Workflows are configured by Admin per company.
- Each Workflow has a **minimum order amount** threshold.
- Steps can be configured as sequential or parallel (`require_sequential`).
- `WorkflowService::canUserApprove()` enforces role-based step restrictions.
- Each approval step is logged in `OrderApprovalLog`.

### 7.3 Buyer Bargains (Counter-Offer)

```
Buyer views Quotation
        ↓
Enters requested price per item (must be ≤ supplier offered price)
Enters mandatory comment/reason
        ↓
[System validates:]
  - Price > 0
  - Price ≤ supplier_offered_price
  - Price ≥ minimum tolerance (offered_price × (1 - tolerance%))
        ↓
Order reverts to "RFQ" status for supplier to review
Buyer's requested prices saved on each OrderItem
        ↓
[Supplier reviews bargain request and re-submits offer]
```

> **Note:** Bargaining is disabled for companies with `bargaining_enabled = false` and for all PunchOut orders.

### 7.4 Buyer Accepts Offer (Quotation → PO)

```
Buyer reviews final quotation pricing
        ↓
Uploads official PO document (PDF/Image, max 5MB)
        ↓
[System:]
  - Freezes supplier offered prices as final item prices
  - Recalculates order total
  - Saves PO attachment path
  - Updates status to "PO"
        ↓
[Admin and all Supplier roles notified via email and in-app notification]
  - Email contains PO document as attachment
```

---

## 8. Shipment Process

```
[Admin/Supplier opens PO order in Dashboard]
        ↓
Clicks "Create Shipment"
        ↓
Selects Carrier (with optional tracking URL pattern)
Enters Tracking Number
Optionally adds Supplier Notes
Specifies shipped quantity per item
        ↓
[System validates:]
  - At least one item qty > 0
  - Shipped qty ≤ remaining unshipped qty per item
        ↓
[System creates Shipment record]
[Creates ShipmentItems for each included item]
        ↓
[PDF Delivery Note auto-generated and saved]
        ↓
[Order status updated:]
  - All items fully shipped → "Shipped"
  - Some items remaining   → "Partially Shipped"
        ↓
[Buyer receives email with Delivery Note PDF attached]
[Buyer notified in-app]
```

- Multiple shipments can be created for a single order (partial fulfillment).
- Each shipment has its own Carrier and Tracking Number.
- Shipments are listed in the order detail view under a "Shipping" tab.

---

## 9. Invoicing Process

```
[Order is in "Shipped" status]
        ↓
Admin/Supplier uploads:
  - Primary Invoice File (PDF/Image/XLSX/DOCX, max 10MB)
  - Optional Additional Supporting Documents
        ↓
[System stores documents and updates status to "Invoiced"]
[Approval log entry created]
        ↓
[All buyers belonging to the company are notified in-app]
```

---

## 10. Notification System

### 10.1 In-App Notifications

All notifications are stored in the database (`notifications` table) and surfaced in the user's notification bell/panel.

| Trigger Event | Who is Notified |
|---|---|
| Order placed (RFQ) | — (confirmation email to buyer) |
| Offer submitted → Quotation | Buyer + Buyer Requester of the company |
| Workflow step approved | — |
| Order rejected | Buyer + Buyer Requester of the company |
| PO submitted by Buyer | Admin + all Supplier roles |
| Order partially shipped | Buyer + Buyer Requester |
| Order shipped | Buyer + Buyer Requester |
| Invoice uploaded | Buyer + Buyer Requester |

### 10.2 Email Notifications

| Email | Recipient | Trigger |
|---|---|---|
| **Order Received** | Buyer | After checkout (Direct Order) |
| **Quotation PDF** | Buyer | When order status moves to Quotation |
| **PO Submitted** | Admin + Supplier roles | When buyer uploads PO and accepts offer |
| **Delivery Note PDF** | Buyer | When a shipment is created |

> All email links dynamically resolve to the active server URL (Cloudflare Tunnel or custom domain) rather than a hardcoded localhost.

---

## 11. Admin Order Management

### 11.1 Manual Status Override

Admins can manually override any order's status directly from the Admin Orders Dashboard:

- Valid statuses: `RFQ → Submitted → Approved → Quotation → PO → Partially Shipped → Shipped → Invoiced → Completed → Rejected`
- Forward-only progression is enforced in bulk operations.
- Rejection requires a mandatory reason string.
- Status updates are logged in `OrderApprovalLog`.

### 11.2 Bulk / Batch Status Update

Admin can select multiple orders and apply a status change simultaneously:
- Workflow restrictions are respected per-order.
- Incompatible orders (e.g., backward transitions) are skipped and reported.

### 11.3 Price Sync

Admin can sync/update the latest catalog price into existing order line items directly from the order detail panel.

---

## 12. Admin Configuration

### 12.1 Company Management

Admins configure each buyer company with:

| Setting | Purpose |
|---|---|
| **Name / Address / Tax ID** | Company identification and billing info |
| **Status** | Active or Inactive |
| **Bargaining Enabled** | Enables or disables buyer price counter-offers |
| **PunchOut Enabled** | Enables the external PunchOut integration |
| **PunchOut Gateway** | Adapter type (e.g., `abeta`) |
| **PunchOut URL / Identity / Secret** | Authentication credentials for the external system |

### 12.2 Approval Workflows

Admins can create multi-step approval workflows per company:

| Setting | Purpose |
|---|---|
| **Name** | Human-readable workflow name |
| **Company** | Target company the workflow applies to |
| **Min Amount** | Minimum order value that triggers this workflow |
| **Steps** | Ordered list of approver roles/users |
| **Sequential** | Whether steps must be approved one-by-one |
| **Active** | Enable/disable the workflow |

### 12.3 Application Settings

Admins manage global platform configuration:

| Setting | Purpose |
|---|---|
| **Company Name** | System branding name |
| **Logo** | Displayed in the catalog header and emails |
| **Currency Symbol** | Displayed in orders and emails |
| **SMTP Settings** | Host, port, username, password, encryption, from address |

> SMTP password is stored encrypted in the database using Laravel's built-in encryption cast.

### 12.4 Carriers

Admins manage a list of shipping carriers with optional tracking URL patterns (e.g., `https://track.carrier.com/track/{tracking_number}`).

---

## 13. Audit & History Trail

Every key action in the system is logged:

### OrderHistory
Tracks all negotiation actions per order:
- Supplier Offer submissions
- Buyer Bargain requests
- PO Acceptances
- Previous and new status at each point

### OrderApprovalLog
Tracks administrative and workflow approval decisions:
- Workflow step approvals
- Manual status promotions
- Rejection events with reasons
- Shipment creations
- Invoice uploads

### AuditLog (Global)
System-wide audit trail on any model using the `Auditable` trait (Users, Orders, Products, Companies, etc.):
- Records `created`, `updated`, `deleted` events
- Stores before/after values
- Excluded sensitive fields (e.g., `smtp_password`)

---

## 14. Data Model Summary

```
User ──belongs to──► Company
Company ──has many──► ClientPriceList (per Product)
Company ──has many──► CatalogVisibility
Company ──has many──► Workflow ──has many──► WorkflowStep

Order ──belongs to──► Company
Order ──belongs to──► User (Buyer)
Order ──has many──►  OrderItem ──belongs to──► Product
Order ──has many──►  OrderHistory
Order ──has many──►  OrderApprovalLog
Order ──has many──►  Shipment ──has many──► ShipmentItem ──belongs to──► OrderItem

Product ──has many──► ProductImage
Product ──belongs to many──► Category (hierarchical tree)

Shipment ──belongs to──► Carrier
```
