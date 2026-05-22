# CleckBasket: Freshness at Your Fingertips

CleckBasket is an e-commerce website designed to provide a convenient online shopping experience. Users can browse products, place orders, track deliveries, and make secure payments, while sellers can manage inventory, orders, and sales analytics.

## Overview

CleckBasket offers:

* Access to locally produced goods.
* Secure checkout via PayPal.
* User registration, order placement, and email notifications.
* Inventory and order management for sellers.
* Scheduled delivery and pick-up options.
* Admin panel for managing users, sellers, and transactions.

The goal is to create a user-friendly platform that improves convenience for both buyers and sellers.

## Key Features

* Responsive design for all devices.
* Secure login and registration.
* PayPal-integrated checkout.
* Order tracking and delivery management.
* Seller dashboard to manage products, inventory, and orders.
* Admin panel for monitoring system operations.
* Notifications and email alerts for users.

## System Architecture

* **Customer Portal:** Browse products, add to cart/wishlist, place orders, and track orders.
* **Seller Portal:** Manage products, inventory, orders, and reports.
* **Admin Portal:** Manage users, sellers, products, and transactions.
* **Payment Gateway:** Secure transactions using PayPal.
* **Database:** Stores all user, product, and transaction data.

Workflow: Customer selects product → adds to cart → checks out → selects delivery slot → order confirmed → seller manages order → admin monitors system.

## Technology Stack

* Frontend: Laravel Blade templates, Tailwind CSS, HTML, JavaScript
* Backend: Laravel PHP
* Database: Oracle Cloud Infrastructure (OCI)
* Payment Integration: PayPal Sandbox
* Notifications: Email via SMTP
* Collaboration Tools: GitHub
* OS: Cross-platform
* Version Control: Git/GitHub

## Installation & Setup

### Clone the repository

```bash
git clone https://github.com/gkaushik22/CleckBasket.git
cd CleckBasket
```

### Set up environment

1. Ensure PHP, Composer, and Oracle APEX are installed.
2. Create a `.env` file:

```env
DB_USER=<your_oracle_user>
DB_PASSWORD=<your_password>
DB_HOST=<oracle_host>
DB_PORT=<oracle_port>
DB_NAME=<database_name>
PAYPAL_CLIENT_ID=<your_paypal_client_id>
PAYPAL_SECRET=<your_paypal_secret>
```

### Install dependencies

```bash
composer install
```

### Run the application

```bash
php artisan serve
```

* Access the website at `http://localhost:8000`

## User Guidelines

### Customer Portal

* Register and login to browse products.
* Add products to cart and wishlist.
* Place orders and choose delivery/pick-up slots.
* Track order status and history.
* Receive notifications.

### Seller Portal

* Register and login to manage products.
* Add, edit, or remove products.
* Manage orders and inventory.
* Track sales and generate reports.

### Admin Portal

* Manage users, sellers, products, and transactions.
* Monitor overall system operations.

## Project Structure

```text
CleckBasket/
├── app/                   # Laravel backend application
├── resources/             # Blade templates and assets
├── database_scripts/      # Database scripts
├── public/                # Public assets (CSS, JS)
├── .env                   # Environment configuration
├── composer.json          # PHP dependencies
└── README.md              # Project documentation
```
