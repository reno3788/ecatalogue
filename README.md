# E-Catalogue & Procurement System

A comprehensive B2B e-procurement and e-catalogue management platform built with modern Laravel frameworks. This application enables multi-tenant B2B commerce, customized pricing per client, streamlined purchasing workflows, and efficient back-office administration.

## 🚀 Core Features

### 🛒 E-Catalogue & Shopping Experience
- **Product Browsing:** Category-organized exploration with rich detail views.
- **Shopping Cart:** Real-time cart management with localized persistence.
- **Streamlined Checkout:** Order creation and conversion optimized for procurement requirements.
- **Personal Dashboard:** Order history, profile management, and procurement status tracking.

### ⚙️ Comprehensive Admin Dashboard
- **Product Management:** Robust CRUD operations with dedicated template-based bulk CSV upload functionality.
- **Dynamic Categorization:** Multi-level, parent-child relationship management for product inventories.
- **User Administration:** Complete user, company, and granular role management framework.
- **Application Settings:** Centralized UI for SMTP configurations and live system notification diagnostics.

### 💼 Specialized B2B & Supplier Tools
- **Client Price Lists:** Specialized tooling for processors and approvers to upload and apply specialized contract pricing specific to discrete client accounts.
- **Order Fulfillment Control:** Management view allowing batch processing and individual review of inbound B2B orders.
- **Role-Based Workflows:** Tailored access layers defining strict segregation of duties between suppliers, processors, and standard platform users.

## 🛠 Tech Stack

- **Backend:** PHP 8+ & Laravel Framework
- **Frontend:** Vue.js & Inertia.js
- **Styling:** TailwindCSS
- **Database:** PostgreSQL / MySQL (via Eloquent ORM)

## 📦 Installation

1. **Clone repo and enter folder:**
   ```bash
   git clone https://github.com/reno3788/ecatalogue.git
   cd ecatalogue
   ```

2. **Install dependencies:**
   ```bash
   composer install
   npm install
   ```

3. **Configure environment:**
   Copy `.env.example` to `.env` and configure the database and mail services.
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Migrate and seed database:**
   ```bash
   php artisan migrate --seed
   ```

5. **Build assets & run application:**
   ```bash
   npm run dev
   # in another terminal
   php artisan serve
   ```

## 🔒 License

This project uses standard Laravel open-source distribution protocols. Check internal codebase notes for specific license adherence requirements.
