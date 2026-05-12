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

### Option 1: Local Setup
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

### Option 2: Docker (Laravel Sail)
This project leverages Laravel Sail for simple container management.

1. **Install composer packages (via Docker if local php isn't present):**
   ```bash
   docker run --rm \
       -u "$(id -u):$(id -g)" \
       -v "$(pwd):/var/www/html" \
       -w /var/www/html \
       laravelsail/php85-composer:latest \
       composer install --ignore-platform-reqs
   ```

2. **Start Docker Containers:**
   Copy the env file and start the server.
   ```bash
   cp .env.example .env
   ./vendor/bin/sail up -d
   ```

3. **Generate keys and provision database:**
   ```bash
   ./vendor/bin/sail artisan key:generate
   ./vendor/bin/sail artisan migrate --seed
   ```

4. **Build and start UI compiler:**
   ```bash
   ./vendor/bin/sail npm install
   ./vendor/bin/sail npm run dev
   ```

Your application is now accessible at [http://localhost](http://localhost).

## 🔒 License

This project uses standard Laravel open-source distribution protocols. Check internal codebase notes for specific license adherence requirements.
