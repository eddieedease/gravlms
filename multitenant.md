# Multi-tenancy Implementation Guide

## Overview
GravLMS now supports a **Database-per-Tenant** architecture. This allows multiple organizations to use the same application instance while keeping their data completely isolated in separate databases.

## Architecture

### Backend
- **Master Database (`lms_master`)**: Stores the `tenants` table which maps a tenant's slug (e.g., `acme`) to their specific database credentials.
- **Tenant Databases**: Each tenant has their own separate database containing the application schema (`users`, `courses`, etc.).
- **Dynamic Connection**: The application checks the `X-Tenant-ID` header on each request. If present, it resolves the connection details from the Master DB and connects to the specific Tenant DB.

### Frontend
- **Routing**: Users log in via a tenant-specific URL: `/login/:tenant` (e.g., `http://localhost:4200/login/acme`).
- **Context**: The application captures this tenant slug and stores it in the customized `X-Tenant-ID` header for all subsequent API requests.

## Setup & usage

### 1. Initialize the Master Database
You can initialize the database using the web installer or the command line.

**Option A: Web Installer (Recommended for Shared Hosting)**
1. Navigate to: `http://your-domain.com/install.php`
2. Select **Master Database** from the dropdown.
3. Enter your database credentials.
4. Click "Initialize Database".

**Option B: Command Line**
Run the initialization script and select **Option 1**:
```bash
php html/init_db.php
# Select Option 1 (Master Database)
# Enter credentials (defaults: host=db, user=root, pass=root)
# Default DB Name: lms_master
```

### 2. Register a New Tenant
You must manually insert a record into the `tenants` table in the Master Database to register a new organization.
```sql
-- Connect to lms_master
INSERT INTO tenants (name, slug, db_host, db_name, db_user, db_password) 
VALUES ('Acme Corp', 'acme', 'db', 'lms_acme', 'root', 'root');
```

### 3. Initialize the Tenant Database
Run the initialization script to set up the schema for the new tenant.

**Option A: Web Installer**
1. Navigate to: `http://your-domain.com/install.php`
2. Select **Tenant Database** from the dropdown.
3. Enter the **Tenant Database Name** (e.g., `lms_acme`) and credentials.
4. Click "Initialize Database".

**Option B: Command Line**
```bash
php html/init_db.php
# Select Option 2 (Tenant Database)
# Enter the DB Name you defined above: lms_acme
# This will create the users, courses, etc. tables in lms_acme.
```

### 4. Accessing the Tenant
Navigate to: `http://localhost:4200/login/acme`
- The system will detect 'acme'.
- It will verify 'acme' against `lms_master`.
- It will connect to `lms_acme` for all login and data operations.
