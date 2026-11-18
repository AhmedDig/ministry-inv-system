# Enterprise IT Service Management Platform
# وزارة السياحة والآثار - نظام إدارة خدمات تكنولوجيا المعلومات

## 1. Project Overview

### 1.1 Purpose
A comprehensive IT service management platform designed for government ministries and private enterprises to streamline IT support, inventory management, and service request processes.

### 1.2 Key Features
- **Multi-level User Authentication** (5 clearance levels)
- **Inventory Management** with real-time tracking
- **Service Request System** for IT support
- **Role-based Access Control**
- **Arabic/English Bilingual Interface**
- **Reporting and Analytics**
- **CSV Export Functionality**

### 1.3 Target Users
- Government Ministry Employees
- IT Department Staff
- System Administrators
- Department Managers

## 2. System Architecture

### 2.1 Technology Stack
**Frontend:**
- HTML5, CSS3, JavaScript (ES6+)
- Tailwind CSS for styling
- Font Awesome for icons
- Responsive design with RTL support

**Backend:**
- PHP 8.x (with XAMPP stack)
- MySQL Database with PDO
- Session-based authentication
- AJAX for dynamic content

**Development Environment:**
- XAMPP (Apache, MySQL, PHP)
- Git for version control

### 2.2 Database Architecture

#### Core Tables:
- `users` - User accounts and profiles
- `inventory_items` - Product inventory
- `inventory_categories` - Product categorization
- `item_attributes` - Dynamic product attributes
- `departments` - Organizational structure
- `roles` - Permission levels

#### Key Relationships:
- Users → Departments (Many-to-One)
- Inventory Items → Categories (Many-to-One)
- Items → Attributes (One-to-Many)

### 2.3 Security Model

#### Clearance Levels:
1. **Level 1** - Regular User (Basic access)
2. **Level 2** - Service Agent (Ticket management)
3. **Level 3** - Moderator (Content oversight)
4. **Level 4** - Department Admin (User management)
5. **Level 5** - Super Admin (Full system access)

## 3. Database Schema Details

### 3.1 Users Table Structure
```sql
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    full_name_ar VARCHAR(100) NOT NULL,
    full_name_en VARCHAR(100),
    department_id INT,
    clearance_level INT DEFAULT 1,
    is_verified BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);



# وزارة السياحة والآثار - نظام إدارة خدمات تكنولوجيا المعلومات

## Ministry of Tourism & Antiquities - IT Service Management System

A comprehensive IT service management platform for government ministries and enterprises.

### 🌟 Features
- Multi-level user authentication (5 clearance levels)
- Inventory management with real-time tracking  
- Service request system
- Role-based access control
- Bilingual interface (Arabic/English)
- Reporting and analytics
- CSV export functionality

### 🛠 Technology Stack
- **Frontend**: HTML5, CSS3, JavaScript, Tailwind CSS
- **Backend**: PHP, MySQL
- **Server**: XAMPP/Apache
- **Database**: MySQL with PDO

### 📦 Installation

#### Prerequisites
- XAMPP 8.0+ (Apache, MySQL, PHP)
- Web browser with JavaScript support

#### Setup Steps
1. Clone this repository to `C:/xampp/htdocs/ministry/`
2. Import the database schema from `database/ministry_db.sql`
3. Update database credentials in `includes/config.php`
4. Access via `http://localhost/ministry/public/`

#### Default Admin Account
- Username: `ahmed`
- Password: `123`

### 📁 Project Structure
ministry/
├── public/ # Web-accessible files
│ ├── index.php # Main application
│ ├── assets/ # CSS, JS, Images
│ └── ajax/ # AJAX endpoints
├── src/controllers/ # PHP controllers
├── includes/ # Configuration & core files
└── docs/ # Documentation


### 👥 User Roles
1. **Level 1** - Regular User
2. **Level 2** - Service Agent  
3. **Level 3** - Moderator
4. **Level 4** - Department Admin
5. **Level 5** - Super Admin

### 🚀 Quick Start
1. Start Apache and MySQL in XAMPP
2. Navigate to `http://localhost/ministry/public/`
3. Login with admin credentials
4. Start managing inventory and users

### 📄 License
[Specify your license - suggest MIT]

### 🤝 Contributing
This is a proprietary system developed for the Ministry of Tourism & Antiquities.