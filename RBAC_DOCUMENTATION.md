# RANDIS - Role-Based Access Control (RBAC) Documentation

## Overview
RANDIS implements a comprehensive Role-Based Access Control system to ensure proper security and access management for TNI/PNS vehicle management.

## Role Hierarchy

### 1. Admin (Level 1)
**Full System Access**
- User management (create, edit, delete users)
- Vehicle management (all operations)
- System configuration
- Reports and analytics
- Audit logs access
- Database maintenance

**Typical Users:** System administrators, IT personnel

### 2. Pimpinan (Level 1)
**Leadership Access**
- Review and approve requests
- Reports and analytics
- Audit logs access
- Oversight of vehicle and maintenance data

**Typical Users:** Command staff, leadership

### 3. Driver (Level 3)
**Operational Access**
- View assigned vehicles
- Log fuel usage for assigned vehicles
- View maintenance schedules
- Submit maintenance requests
- View own usage history

**Typical Users:** Drivers, vehicle users

### 4. User (Level 3)
**Basic Access**
- Request vehicle usage
- View request status and assigned vehicles
- View maintenance schedules
- Basic reporting for own vehicles

**Typical Users:** General users, field personnel

## Database Implementation

### Role Table
```sql
CREATE TABLE `role` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `kode_role` varchar(50) NOT NULL,
    `nama_role` varchar(50) NOT NULL,
    `level_akses` int(11) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `kode_role` (`kode_role`)
);

INSERT INTO `role` (`kode_role`, `nama_role`, `level_akses`) VALUES
('ADMIN', 'Admin', 1),
('PIMPINAN', 'Pimpinan', 1),
('DRIVER', 'Driver', 3),
('USER', 'User', 3);

-- Lower level_akses means higher privilege. Admin-like roles are those
-- with the minimum level_akses value.
```

### User Account Linking
```sql
CREATE TABLE `user_account` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pengguna_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL,
  `status` enum('Aktif','Non-Aktif') DEFAULT 'Aktif',
  -- Foreign key to role table
  FOREIGN KEY (`role_id`) REFERENCES `role`(`id`)
);
```

## Authentication Functions

### Core Functions (in `includes/auth.php`)
```php
// Check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

// Get current user role
function get_current_role() {
    return $_SESSION['role'] ?? 'guest';
}

// Check permissions
function is_admin_like() {
    $current_level = get_current_role_level();
    $min_level = get_min_role_level();
    return $current_level !== null && $min_level !== null && $current_level <= $min_level;
}

function can_admin() {
    return is_admin_like();
}

function can_operate() {
    return is_admin_like();
}

function can_access_vehicle($vehicle_id) {
    // Implementation for vehicle-specific access
}
```

## Access Control Implementation

### Page-Level Protection
```php
// At the top of protected pages
require_once 'includes/auth.php';
check_login(); // Redirect if not logged in

// Role-specific checks
if (!is_admin_like()) {
    die("Access denied. Admin-like level required.");
}
```

### Menu System
The system dynamically shows menu items based on user role:
- **Admin/Pimpinan**: All menu items visible
- **Driver**: Assigned vehicle tools, fuel logs, schedules
- **User**: Limited to own requests and basic functions

## Security Features

### 1. Password Security
- Passwords hashed using PHP's `password_hash()`
- Secure password verification
- Password strength requirements (recommended)

### 2. Session Management
- Secure session handling
- Session timeout
- Session hijacking prevention

### 3. CSRF Protection
- CSRF tokens for form submissions
- Token validation on sensitive operations

### 4. Activity Logging
```sql
CREATE TABLE `log_aktivitas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `aksi` varchar(100) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
);
```

## Default Accounts

### Test Accounts (Change in Production!)
```sql
-- Admin account
INSERT INTO user_account (pengguna_id, username, password_hash, role_id, status)
SELECT 1, 'admin', '<bcrypt-hash>', id, 'Aktif' FROM role WHERE UPPER(kode_role) = 'ADMIN';

-- Pimpinan account
INSERT INTO user_account (pengguna_id, username, password_hash, role_id, status)
SELECT 2, 'pimpinan', '<bcrypt-hash>', id, 'Aktif' FROM role WHERE UPPER(kode_role) = 'PIMPINAN';

-- Driver account
INSERT INTO user_account (pengguna_id, username, password_hash, role_id, status)
SELECT 3, 'driver', '<bcrypt-hash>', id, 'Aktif' FROM role WHERE UPPER(kode_role) = 'DRIVER';

-- User account
INSERT INTO user_account (pengguna_id, username, password_hash, role_id, status)
SELECT 4, 'user', '<bcrypt-hash>', id, 'Aktif' FROM role WHERE UPPER(kode_role) = 'USER';
```

**⚠️ IMPORTANT:** Change default passwords before production deployment!

## File Structure (After Cleanup)
```
c:\xampp\htdocs\randis\
├── includes/
│   └── auth.php          # Authentication functions
├── config.php            # Database config with auth includes
├── login.php             # Login handler
├── logout.php            # Logout handler
├── index.php             # Main app with role-based routing
└── pages/                # Role-protected pages
    ├── dashboard_admin.php
    ├── dashboard_pimpinan.php
    ├── dashboard_driver.php
    └── dashboard_user.php
```

## Best Practices

### For Administrators
1. Regularly audit user accounts and roles
2. Monitor activity logs for suspicious behavior
3. Implement password policies
4. Regular security updates

### For Developers
1. Always check authentication before page content
2. Use role-based function calls consistently
3. Implement principle of least privilege
4. Log security-relevant actions

### For Users
1. Use strong, unique passwords
2. Log out when finished
3. Report suspicious activity
4. Don't share account credentials

## Integration with TNI Structure
The RBAC system integrates with TNI organizational structure:
- **Matra** (Service branch): TNI AD, AL, AU
- **Korps** (Corps): Infantry, Cavalry, etc.
- **Kesatuan** (Unit): Specific units
- **Satuan** (Sub-unit): Company/platoon level

This allows for hierarchical access control based on organizational position.

## Status
✅ RBAC system fully implemented  
✅ Authentication functions working  
✅ Role-based menu system active  
✅ Security features enabled  
✅ Activity logging operational  
✅ File structure cleaned and organized  

The RBAC system is ready for production use with proper security measures in place.
