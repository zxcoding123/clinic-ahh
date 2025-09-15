# WMSU Health Services Management System

A comprehensive web-based platform for managing medical and dental records, appointments, document uploads, certificate issuance, and health consultations for the Western Mindanao State University (WMSU) community.

## Table of Contents

- [System Overview](#system-overview)
- [Features](#features)
- [User Roles & Permissions](#user-roles--permissions)
- [How It Works](#how-it-works)
- [Main Modules](#main-modules)
- [Technology Stack](#technology-stack)
- [Installation & Setup](#installation--setup)
- [Database Schema](#database-schema)
- [Security Features](#security-features)
- [File Structure](#file-structure)
- [API Endpoints](#api-endpoints)
- [Troubleshooting](#troubleshooting)
- [FAQ](#faq)

## System Overview

The WMSU Health Services Management System is designed to streamline healthcare operations for students, employees, and their dependents. It provides a complete digital solution for medical document management, appointment scheduling, consultation tracking, and certificate issuance.

### Key Benefits
- **Digital Transformation**: Eliminates paper-based processes
- **Centralized Management**: All health records in one secure location
- **Role-Based Access**: Different interfaces for different user types
- **Automated Workflows**: Streamlined approval and certificate processes
- **Real-time Analytics**: Dashboard with insights and reporting
- **Mobile-Friendly**: Responsive design for all devices

## Features

### Core Features
- **User Authentication & Registration**: Secure login with role-based access
- **Profile Management**: Complete user profile and health information
- **Document Upload System**: Secure file uploads with validation
- **Appointment Scheduling**: Medical and dental appointment booking
- **Certificate Management**: Request, approve, and issue medical certificates
- **Consultation Tracking**: Record and track medical/dental consultations
- **Admin Dashboard**: Analytics, reporting, and system management
- **Content Management**: Dynamic content for landing pages and announcements
- **Email Notifications**: Automated email alerts and confirmations
- **PDF Generation**: Automated certificate and report generation

### Advanced Features
- **CSRF Protection**: Cross-site request forgery prevention
- **Session Management**: Secure session handling with timeouts
- **File Security**: Secure file serving with access controls
- **Data Validation**: Comprehensive input validation and sanitization
- **Error Logging**: Detailed error tracking and debugging
- **Print Reports**: Printable analytics and consultation reports

## User Roles & Permissions

### Admin Roles

#### Super Admin
- **Full System Access**: Complete control over all features
- **User Management**: Create, edit, and manage all user accounts
- **Content Management**: Update landing pages, announcements, FAQs
- **Analytics & Reporting**: Access to all system analytics and reports
- **System Configuration**: Modify system settings and configurations

#### Medical Admin
- **Medical Records**: Manage all medical-related documents and requests
- **Medical Appointments**: Schedule and manage medical consultations
- **Certificate Issuance**: Approve and issue medical certificates
- **Patient Profiles**: View and update patient medical information
- **Medical Analytics**: Access medical consultation analytics

#### Dental Admin
- **Dental Records**: Manage all dental-related documents and requests
- **Dental Appointments**: Schedule and manage dental consultations
- **Dental Certificates**: Approve and issue dental certificates
- **Dental Analytics**: Access dental consultation analytics

### User Roles

#### Parent
- **Child Registration**: Register children for Kindergarten/Elementary
- **Document Upload**: Upload required documents for children
- **Appointment Booking**: Schedule appointments for children
- **Certificate Requests**: Request medical certificates for children
- **Profile Management**: Manage family health information

#### Students (Highschool, Senior High, College)
- **Document Upload**: Upload required medical documents
- **Appointment Booking**: Schedule medical and dental appointments
- **Certificate Requests**: Request medical certificates for various purposes
- **Profile Management**: Update personal and health information
- **Consultation History**: View past consultations and records

#### Employee
- **Document Upload**: Upload required employment-related documents
- **Appointment Booking**: Schedule medical and dental appointments
- **Certificate Requests**: Request medical certificates for employment
- **Profile Management**: Update employment and health information

#### Incoming Freshman
- **Special Requirements**: Multiple required medical tests and documents
- **Document Tracking**: Track completion of all required documents
- **Certificate Issuance**: Special certificate upon completion
- **Restricted Appointments**: Cannot book appointments until documents complete

## How It Works

### User Journey

#### 1. Registration & Onboarding
```
User Registration → Email Verification → User Type Selection → Document Upload → Profile Completion
```

#### 2. Document Management
```
Document Upload → Admin Review → Approval/Rejection → Certificate Issuance (if applicable)
```

#### 3. Appointment Process
```
Appointment Request → Admin Approval → Consultation → Record Keeping → Follow-up
```

#### 4. Certificate Process
```
Certificate Request → Document Verification → Admin Approval → PDF Generation → Email Delivery
```

### System Flow

#### For Regular Users
1. **Registration**: Create account with email verification
2. **User Type Selection**: Choose appropriate user category
3. **Document Upload**: Upload required medical documents
4. **Profile Completion**: Fill out personal and health information
5. **Appointment Booking**: Schedule consultations as needed
6. **Certificate Requests**: Request certificates for various purposes
7. **Consultation Tracking**: View and manage consultation history

#### For Admins
1. **Dashboard Access**: View system analytics and user counts
2. **Document Management**: Review and approve uploaded documents
3. **Appointment Management**: Schedule and manage consultations
4. **Certificate Issuance**: Generate and send medical certificates
5. **Content Management**: Update website content and announcements
6. **Reporting**: Generate reports and analytics

### Data Flow

```
User Input → Validation → Database Storage → Processing → Output (PDF/Email/Display)
```

## Main Modules

### Authentication Module
- **Files**: `login.php`, `signup.php`, `logout.php`, `verify_email.php`
- **Features**: User registration, login, email verification, password reset
- **Security**: CSRF protection, session management, input validation

### User Management Module
- **Files**: `profile.php`, `StudentProfileUpdate.php`, `admin-account.php`
- **Features**: Profile creation, updates, user type management
- **Validation**: Form validation, data sanitization

### Document Management Module
- **Files**: `uploaddocs.php`, `upload.php`, `uploadmedcert.php`, `serve_file.php`
- **Features**: File upload, validation, secure serving, document tracking
- **Security**: File type validation, size limits, secure storage

### Appointment Module
- **Files**: `appointment.php`, `MedicalRequest.php`, `DentalRequest.php`, `appointmentCalendar.php`
- **Features**: Appointment booking, scheduling, management
- **Integration**: Calendar view, time slot management

### Certificate Module
- **Files**: `medcertRequest.php`, `send_certificate.php`, `getMedCertHistory.php`
- **Features**: Certificate requests, PDF generation, email delivery
- **Technology**: TCPDF for PDF generation, PHPMailer for emails

### Consultation Module
- **Files**: `consultationForm.php`, `record_consultation.php`, `view-consultation.php`
- **Features**: Consultation recording, history tracking, medical notes
- **Integration**: Patient data, medical history

### Admin Dashboard Module
- **Files**: `adminhome.php`, `medical-documents.php`, `dental-appointments.php`, `medical-appointments.php`
- **Features**: Analytics, user management, document approval
- **Charts**: Patient distribution, consultation trends

### Content Management Module
- **Files**: `cms_index.php`, `cms_homepage.php`, `cms_upload.php`, `editAnnouncement.php`
- **Features**: Dynamic content management, announcements, FAQs
- **Database**: Content stored in database for easy updates

### Walk-in Module
- **Files**: `walkin.php`
- **Features**: Register walk-in patients, quick consultation setup
- **Integration**: Patient registration, consultation scheduling

## Technology Stack

### Backend
- **PHP**: 7.2+ (Object-oriented programming)
- **Database**: MySQL/MariaDB
- **Web Server**: Apache (with mod_rewrite)

### Frontend
- **HTML5**: Semantic markup
- **CSS3**: Tailwind CSS + Bootstrap for responsive design
- **JavaScript**: Vanilla JS for interactivity
- **Charts**: Chart.js for analytics visualization

### Libraries & Dependencies
- **TCPDF**: PDF generation for certificates and reports
- **PHPMailer**: Email functionality for notifications
- **phpdotenv**: Environment variable management
- **Font Awesome**: Icons and UI elements

### Security
- **CSRF Protection**: Cross-site request forgery prevention
- **Session Management**: Secure session handling
- **Input Validation**: Comprehensive data validation
- **File Security**: Secure file upload and serving
- **SQL Injection Prevention**: Prepared statements

## Installation & Setup

### Prerequisites
- PHP 7.2 or higher
- MySQL 5.7+ or MariaDB 10.2+
- Apache web server with mod_rewrite
- Composer (for dependency management)
- SSL certificate (for production)

### Step-by-Step Installation

#### 1. Server Setup
```bash
# Install required PHP extensions
sudo apt-get install php-mysql php-mbstring php-xml php-gd php-zip

# Enable Apache modules
sudo a2enmod rewrite
sudo systemctl restart apache2
```

#### 2. Project Setup
```bash
# Clone or download project to web directory
cd /var/www/html/
git clone [repository-url] wmsu

# Set proper permissions
sudo chown -R www-data:www-data wmsu/
sudo chmod -R 755 wmsu/
sudo chmod -R 777 wmsu/Uploads/
```

#### 3. Database Setup
```bash
# Create database
mysql -u root -p
CREATE DATABASE clinic;
USE clinic;

# Import schema
mysql -u root -p clinic < final.sql
```

#### 4. Configuration
```php
// Edit config.php with your database credentials
$servername = "localhost";
$username = "your_db_user";
$password = "your_db_password";
$dbname = "clinic";
```

#### 5. Dependencies
```bash
# Install Composer dependencies
composer install

# Verify TCPDF installation
php test_tcpdf.php
```

#### 6. Email Configuration
```php
// Configure mailer.php with your SMTP settings
$mail->Host = 'smtp.gmail.com';
$mail->Username = 'your-email@gmail.com';
$mail->Password = 'your-app-password';
```

### Environment Configuration

#### Development
- Set `display_errors = On` in php.ini
- Use localhost database
- Enable error logging

#### Production
- Set `display_errors = Off` in php.ini
- Use production database
- Configure SSL certificate
- Set up proper backup procedures

## Database Schema

### Core Tables

#### Users & Authentication
- `users`: Main user accounts and authentication
- `patients`: Patient-specific health information
- `children`: Child records for parent users
- `employees`: Employee-specific information

#### Documents & Files
- `medical_documents`: Uploaded medical documents
- `certificate_logs`: Issued certificate tracking
- `file_requirements`: Document requirements by user type

#### Appointments & Consultations
- `appointments`: Scheduled appointments
- `consultations`: Completed consultation records
- `consultation_advice`: Admin advice for consultations

#### Content Management
- `content`: Dynamic website content
- `announcements`: System announcements
- `faqs`: Frequently asked questions

#### Analytics & Tracking
- `csrf_tokens`: CSRF protection tokens
- Various tracking tables for analytics

### Key Relationships
```
users (1) ←→ (many) medical_documents
users (1) ←→ (many) appointments
users (1) ←→ (many) consultations
users (1) ←→ (many) children (for parents)
```

## Security Features

### Authentication Security
- **Password Hashing**: Bcrypt encryption for passwords
- **Session Security**: Secure session handling with timeouts
- **CSRF Protection**: Tokens to prevent cross-site request forgery
- **Input Validation**: Comprehensive data validation and sanitization

### File Security
- **Upload Validation**: File type and size restrictions
- **Secure Storage**: Files stored outside web root
- **Access Control**: Secure file serving with authentication
- **Virus Scanning**: Recommended for production

### Database Security
- **Prepared Statements**: SQL injection prevention
- **User Permissions**: Database user with minimal privileges
- **Data Encryption**: Sensitive data encryption (recommended)

### Application Security
- **HTTPS Enforcement**: SSL/TLS encryption
- **Headers Security**: Security headers implementation
- **Error Handling**: Secure error messages
- **Rate Limiting**: Request rate limiting (recommended)

## File Structure

```
wmsu/
├── index.php                 # Landing page
├── login.php                 # User authentication
├── signup.php                # User registration
├── homepage.php              # User dashboard
├── adminhome.php             # Admin dashboard
├── medical-documents.php     # Medical document management
├── dental-appointments.php   # Dental appointment management
├── medical-appointments.php  # Medical appointment management
├── profile.php               # User profile management
├── uploaddocs.php            # Document upload interface
├── appointment.php           # Appointment booking
├── medcertRequest.php        # Certificate request processing
├── send_certificate.php      # Certificate generation and email
├── serve_file.php            # Secure file serving
├── config.php                # Database configuration
├── composer.json             # Dependencies
├── .htaccess                 # URL routing and security
├── final.sql                 # Database schema
├── css/                      # Stylesheets
├── js/                       # JavaScript files
├── images/                   # Image assets
├── Uploads/                  # Uploaded files
└── vendor/                   # Composer dependencies
```

## Backend Scripts & API Code

### Core Backend Scripts

#### Authentication & Security Scripts
- **`login.php`** - User authentication with session management and CSRF protection
- **`signup.php`** - User registration with email verification and validation
- **`logout.php`** - Secure session termination and cleanup
- **`verify_email.php`** - Email verification token validation
- **`forgot_password.php`** - Password reset functionality
- **`reset_password.php`** - Password reset token validation and update

#### Document Management Scripts
- **`upload.php`** - Main file upload handler with validation and security
- **`uploaddocs.php`** - Document upload interface with user type validation
- **`uploadmedcert.php`** - Medical certificate specific upload processing
- **`serve_file.php`** - Secure file serving with authentication and access control
- **`Elemform.php`** - Elementary student form processing with file uploads

#### Certificate & PDF Generation Scripts
- **`send_certificate.php`** - Certificate generation and email delivery
- **`getMedCertHistory.php`** - Medical certificate history API endpoint
- **`medcertRequest.php`** - Certificate request processing and validation
- **`medcert.php`** - Certificate management interface

#### Appointment & Consultation Scripts
- **`appointment.php`** - Appointment booking and scheduling
- **`appointmentCalendar.php`** - Calendar data and availability management
- **`cancel_consultation.php`** - Consultation cancellation with email notification
- **`consultationForm.php`** - Consultation form processing and data validation
- **`record_consultation.php`** - Consultation recording with database transaction
- **`submit-consultation.php`** - Consultation submission with medical data
- **`submit-prescription.php`** - Prescription data processing
- **`view-consultation.php`** - Consultation history viewing

#### Email & Communication Scripts
- **`mailer.php`** - PHPMailer configuration and email sending
- **`send_consult_email.php`** - Consultation advice email delivery
- **`send_link.php`** - Email link generation and sending
- **`resend_email.php`** - Email resend functionality with validation

#### Admin & Management Scripts
- **`adminhome.php`** - Admin dashboard with analytics and user management
- **`medical-documents.php`** - Medical document management interface
- **`dental-appointments.php`** - Dental appointment management
- **`medical-appointments.php`** - Medical appointment management
- **`patient-profile.php`** - Patient profile management
- **`admin-account.php`** - Admin account management

#### Content Management Scripts
- **`cms_index.php`** - Landing page content management
- **`cms_homepage.php` - Homepage content management with image uploads
- **`cms_upload.php`** - File upload management for content
- **`editAnnouncement.php`** - Announcement editing and management
- **`postAnnouncement.php`** - Announcement posting functionality

#### User Management Scripts
- **`profile.php`** - User profile management and updates
- **`StudentProfileUpdate.php`** - Student profile update processing
- **`walkin.php`** - Walk-in patient registration
- **`wmsuwaiver.php`** - Waiver form processing

#### Form Processing Scripts
- **`form.php`** - Main form processing with validation and file uploads
- **`MedicalRequest.php`** - Medical request form processing
- **`DentalRequest.php`** - Dental request form processing
- **`dentalForm.php`** - Dental form processing
- **`dentalForm2.php`** - Additional dental form processing
- **`dentalConsent.php`** - Dental consent form processing

### API Endpoints

#### Authentication Endpoints
- `POST /login` - User login with session creation
- `POST /signup` - User registration with email verification
- `GET /logout` - User logout and session cleanup
- `POST /verify_email` - Email verification token validation
- `POST /forgot_password` - Password reset request
- `POST /reset_password` - Password reset with token validation

#### Document Management Endpoints
- `POST /uploaddocs` - Document upload with validation
- `GET /serve_file.php?id={id}` - Secure file serving with authentication
- `POST /medcertRequest` - Certificate request processing
- `POST /upload` - General file upload processing
- `POST /uploadmedcert` - Medical certificate upload

#### Certificate Endpoints
- `POST /send_certificate` - Certificate generation and email delivery
- `GET /getMedCertHistory.php?user_id={id}` - Certificate history API
- `POST /medcertRequest` - Certificate request submission

#### Appointment Endpoints
- `POST /appointment` - Create new appointment
- `GET /appointmentCalendar` - Get calendar availability data
- `POST /cancel_consultation` - Cancel consultation with email notification
- `POST /record_consultation` - Record consultation data
- `POST /submit-consultation` - Submit consultation form

#### Admin Endpoints
- `GET /adminhome` - Admin dashboard data
- `POST /send_consult_email` - Send consultation advice email
- `POST /record_consultation` - Record consultation with transaction
- `POST /mark_consultation_complete` - Mark consultation as complete

#### Content Management Endpoints
- `POST /cms_index` - Update landing page content
- `POST /cms_homepage` - Update homepage content
- `POST /cms_upload` - Upload content files
- `POST /editAnnouncement` - Edit announcements
- `POST /postAnnouncement` - Post new announcements

### Backend Architecture

#### Security Implementation
- **CSRF Protection**: All forms use CSRF tokens stored in database
- **Session Management**: Secure session handling with timeouts
- **Input Validation**: Comprehensive sanitization and validation
- **File Security**: Secure file upload with type and size validation
- **SQL Injection Prevention**: Prepared statements throughout
- **Authentication**: Role-based access control

#### Database Transactions
- **Atomic Operations**: Critical operations use database transactions
- **Rollback Support**: Error handling with automatic rollback
- **Data Integrity**: Foreign key constraints and validation

#### Error Handling
- **Comprehensive Logging**: Detailed error logging to files
- **Graceful Degradation**: User-friendly error messages
- **Debug Information**: Development mode with detailed errors

#### Email System
- **PHPMailer Integration**: Professional email delivery
- **SMTP Configuration**: Secure email transmission
- **Template System**: HTML email templates
- **Attachment Support**: PDF certificate attachments

#### File Management
- **Secure Upload**: File type and size validation
- **Secure Storage**: Files stored outside web root
- **Access Control**: Authentication required for file access
- **Virus Protection**: File scanning recommendations

### API Response Format

#### Success Response
```json
{
  "success": true,
  "message": "Operation completed successfully",
  "data": {...},
  "redirect": "optional_redirect_url"
}
```

#### Error Response
```json
{
  "success": false,
  "message": "Error description",
  "error_code": "optional_error_code"
}
```

#### Authentication Response
```json
{
  "success": true,
  "user_id": 123,
  "user_type": "College",
  "redirect": "homepage.php"
}
```

### Backend Dependencies

#### PHP Libraries
- **PHPMailer**: Email functionality
- **TCPDF**: PDF generation
- **HTML2PDF**: Alternative PDF generation
- **phpdotenv**: Environment variable management

#### Database
- **MySQL/MariaDB**: Primary database
- **Prepared Statements**: SQL injection prevention
- **Transactions**: Data integrity

#### File System
- **Upload Directories**: Organized file storage
- **Security**: File access controls
- **Backup**: File backup recommendations

## Complete PHP File Functions (68 Files)

### Core System Files

#### 1. **`index.php`** - Landing Page
- **Function**: Main landing page with dynamic content
- **Features**: Hero section, services overview, announcements
- **Database**: Fetches content from `content` table
- **Security**: Session management, redirect logic

#### 2. **`config.php`** - Database Configuration
- **Function**: Central database connection and configuration
- **Features**: MySQL connection, error handling
- **Security**: Prepared statements, connection validation

#### 3. **`router.php`** - URL Routing
- **Function**: Clean URL routing and redirection
- **Features**: .php extension removal, static file serving
- **Security**: 404 handling, request validation

#### 4. **`.htaccess`** - Server Configuration
- **Function**: Apache server configuration and security
- **Features**: HTTPS redirection, clean URLs, security headers
- **Security**: Access control, CORS headers

### Authentication & Security Files

#### 5. **`login.php`** - User Authentication
- **Function**: User login with session management
- **Features**: CSRF protection, role-based redirects, remember me
- **Security**: Password validation, session security, admin restrictions

#### 6. **`signup.php`** - User Registration
- **Function**: New user registration with validation
- **Features**: Email verification, user type selection, form validation
- **Security**: Input sanitization, duplicate email prevention

#### 7. **`logout.php`** - Session Termination
- **Function**: Secure user logout and session cleanup
- **Features**: Session destruction, cookie cleanup
- **Security**: Complete session termination

#### 8. **`verify_email.php`** - Email Verification
- **Function**: Email verification token validation
- **Features**: Token verification, account activation
- **Security**: Token expiration, secure validation

#### 9. **`forgot_password.php`** - Password Reset Request
- **Function**: Password reset initiation
- **Features**: Email sending, token generation
- **Security**: Rate limiting, email validation

#### 10. **`reset_password.php`** - Password Reset
- **Function**: Password reset with token validation
- **Features**: Token verification, password update
- **Security**: Token expiration, password strength validation

### User Interface Files

#### 11. **`homepage.php`** - User Dashboard
- **Function**: Main user dashboard after login
- **Features**: Profile status checking, navigation, user info
- **Security**: Session validation, role-based access

#### 12. **`adminhome.php`** - Admin Dashboard
- **Function**: Comprehensive admin dashboard with analytics
- **Features**: User counts, consultation statistics, charts
- **Security**: Admin-only access, data validation

#### 13. **`profile.php`** - User Profile Management
- **Function**: User profile viewing and editing
- **Features**: Profile updates, photo upload, information display
- **Security**: Input validation, file upload security

#### 14. **`StudentProfileUpdate.php`** - Student Profile Updates
- **Function**: Student-specific profile management
- **Features**: Academic information, health data, photo upload
- **Security**: Form validation, file upload validation

### Document Management Files

#### 15. **`uploaddocs.php`** - Document Upload Interface
- **Function**: Main document upload system
- **Features**: File validation, user type requirements, progress tracking
- **Security**: File type validation, size limits, CSRF protection

#### 16. **`upload.php`** - File Upload Handler
- **Function**: Core file upload processing
- **Features**: Multi-file upload, validation, secure storage
- **Security**: File type checking, virus scanning preparation

#### 17. **`uploadmedcert.php`** - Medical Certificate Upload
- **Function**: Medical certificate specific uploads
- **Features**: Certificate validation, reason tracking
- **Security**: Document verification, access control

#### 18. **`serve_file.php`** - Secure File Serving
- **Function**: Secure file access and download
- **Features**: Authentication required, file type detection
- **Security**: Access control, path validation, download tracking

### Certificate Management Files

#### 19. **`medcertRequest.php`** - Certificate Request Processing
- **Function**: Medical certificate request handling
- **Features**: Request validation, document verification
- **Security**: Admin-only access, request validation

#### 20. **`send_certificate.php`** - Certificate Generation & Email
- **Function**: PDF certificate generation and email delivery
- **Features**: TCPDF integration, email sending, file attachment
- **Security**: Admin validation, email security

#### 21. **`getMedCertHistory.php`** - Certificate History API
- **Function**: Medical certificate history retrieval
- **Features**: JSON API, history tracking
- **Security**: User validation, data sanitization

#### 22. **`medcert.php`** - Certificate Management Interface
- **Function**: Certificate management dashboard
- **Features**: Certificate listing, status tracking
- **Security**: Admin access control

### Appointment & Consultation Files

#### 23. **`appointment.php`** - Appointment Booking
- **Function**: Appointment scheduling system
- **Features**: Calendar integration, time slot management
- **Security**: User validation, appointment validation

#### 24. **`appointmentCalendar.php`** - Calendar Management
- **Function**: Appointment calendar data and availability
- **Features**: Calendar view, availability checking
- **Security**: Data validation, access control

#### 25. **`consultationForm.php`** - Consultation Form Processing
- **Function**: Medical consultation form handling
- **Features**: Form validation, medical data recording
- **Security**: Input sanitization, medical data protection

#### 26. **`record_consultation.php`** - Consultation Recording
- **Function**: Consultation data recording with transactions
- **Features**: Database transactions, medical notes
- **Security**: Data integrity, medical confidentiality

#### 27. **`submit-consultation.php`** - Consultation Submission
- **Function**: Consultation form submission
- **Features**: Medical data processing, appointment updates
- **Security**: Medical data validation, access control

#### 28. **`submit-prescription.php`** - Prescription Processing
- **Function**: Prescription data handling
- **Features**: Medication tracking, dosage recording
- **Security**: Medical data protection, validation

#### 29. **`view-consultation.php`** - Consultation History
- **Function**: Consultation history viewing
- **Features**: History display, medical records
- **Security**: User access control, medical confidentiality

#### 30. **`cancel_consultation.php`** - Consultation Cancellation
- **Function**: Consultation cancellation with notifications
- **Features**: Email notifications, status updates
- **Security**: User validation, cancellation confirmation

### Email & Communication Files

#### 31. **`mailer.php`** - Email Configuration
- **Function**: PHPMailer setup and configuration
- **Features**: SMTP configuration, email templates
- **Security**: Email authentication, secure transmission

#### 32. **`send_consult_email.php`** - Consultation Email Delivery
- **Function**: Consultation advice email sending
- **Features**: Email templates, consultation notifications
- **Security**: Email validation, user verification

#### 33. **`send_link.php`** - Email Link Generation
- **Function**: Email link creation and sending
- **Features**: Link generation, email delivery
- **Security**: Link validation, email security

#### 34. **`resend_email.php`** - Email Resend Functionality
- **Function**: Email resend with validation
- **Features**: Resend logic, email verification
- **Security**: Rate limiting, email validation

### Admin Management Files

#### 35. **`medical-documents.php`** - Medical Document Management
- **Function**: Comprehensive medical document administration
- **Features**: Document approval, certificate issuance, analytics
- **Security**: Admin-only access, document security

#### 36. **`dental-appointments.php`** - Dental Appointment Management
- **Function**: Dental appointment administration
- **Features**: Appointment scheduling, dental records
- **Security**: Admin access control, dental data protection

#### 37. **`medical-appointments.php`** - Medical Appointment Management
- **Function**: Medical appointment administration
- **Features**: Appointment management, medical records
- **Security**: Admin access control, medical data protection

#### 38. **`patient-profile.php`** - Patient Profile Management
- **Function**: Patient profile administration
- **Features**: Profile management, health records
- **Security**: Admin access control, patient data protection

#### 39. **`admin-account.php`** - Admin Account Management
- **Function**: Admin account administration
- **Features**: Admin user management, account settings
- **Security**: Admin access control, account security

### Content Management Files

#### 40. **`cms_index.php`** - Landing Page Content Management
- **Function**: Landing page content administration
- **Features**: Content editing, image management
- **Security**: Admin access control, content validation

#### 41. **`cms_homepage.php`** - Homepage Content Management
- **Function**: Homepage content administration
- **Features**: Content editing, image uploads
- **Security**: Admin access control, file upload security

#### 42. **`cms_upload.php`** - Content File Upload Management
- **Function**: Content file upload administration
- **Features**: File management, content organization
- **Security**: Admin access control, file upload validation

#### 43. **`editAnnouncement.php`** - Announcement Editing
- **Function**: Announcement management and editing
- **Features**: Announcement CRUD operations
- **Security**: Admin access control, content validation

#### 44. **`editAnnouncements.php`** - Bulk Announcement Management
- **Function**: Multiple announcement management
- **Features**: Bulk operations, announcement listing
- **Security**: Admin access control, bulk operation validation

#### 45. **`postAnnouncement.php`** - Announcement Posting
- **Function**: New announcement creation
- **Features**: Announcement creation, publishing
- **Security**: Admin access control, content validation

#### 46. **`announcements.php`** - Announcement Display
- **Function**: Public announcement viewing
- **Features**: Announcement listing, public access
- **Security**: Public access, content sanitization

#### 47. **`announcement1.php`** - Announcement Template
- **Function**: Announcement display template
- **Features**: Template system, content display
- **Security**: Content sanitization, template security

### Form Processing Files

#### 48. **`form.php`** - Main Form Processing
- **Function**: Comprehensive form handling system
- **Features**: Multi-step forms, file uploads, validation
- **Security**: CSRF protection, input validation, file security

#### 49. **`MedicalRequest.php`** - Medical Request Form
- **Function**: Medical request form processing
- **Features**: Medical data collection, request tracking
- **Security**: Medical data protection, form validation

#### 50. **`DentalRequest.php`** - Dental Request Form
- **Function**: Dental request form processing
- **Features**: Dental data collection, request tracking
- **Security**: Dental data protection, form validation

#### 51. **`dentalForm.php`** - Dental Form Processing
- **Function**: Dental form data handling
- **Features**: Dental information collection, form validation
- **Security**: Dental data protection, input validation

#### 52. **`dentalForm2.php`** - Additional Dental Form
- **Function**: Extended dental form processing
- **Features**: Additional dental data collection
- **Security**: Dental data protection, form validation

#### 53. **`dentalConsent.php`** - Dental Consent Form
- **Function**: Dental consent form processing
- **Features**: Consent collection, legal compliance
- **Security**: Consent validation, legal protection

#### 54. **`Elemform.php`** - Elementary Student Form
- **Function**: Elementary student registration form
- **Features**: Student data collection, photo upload
- **Security**: Student data protection, file upload security

#### 55. **`wmsuwaiver.php`** - Waiver Form Processing
- **Function**: Waiver form handling
- **Features**: Waiver collection, legal compliance
- **Security**: Waiver validation, legal protection

### User Management Files

#### 56. **`walkin.php`** - Walk-in Patient Registration
- **Function**: Walk-in patient registration system
- **Features**: Quick registration, consultation setup
- **Security**: Registration validation, access control

#### 57. **`dent.php`** - Dental Patient Management
- **Function**: Dental patient administration
- **Features**: Dental patient management, records
- **Security**: Dental data protection, access control

### Utility & Testing Files

#### 58. **`test_tcpdf.php`** - TCPDF Testing
- **Function**: PDF generation testing
- **Features**: TCPDF verification, PDF testing
- **Security**: Testing environment, error handling

#### 59. **`test_login.php`** - Login Testing
- **Function**: Login system testing
- **Features**: Login verification, testing utilities
- **Security**: Testing environment, error handling

#### 60. **`generate_passwords.php`** - Password Generation
- **Function**: Password generation utility
- **Features**: Secure password generation
- **Security**: Password security, generation validation

#### 61. **`mark_consultation_completed.php`** - Consultation Completion
- **Function**: Mark consultations as completed
- **Features**: Status updates, completion tracking
- **Security**: Status validation, access control

#### 62. **`mark_consultation_complete.php`** - Alternative Completion
- **Function**: Alternative consultation completion
- **Features**: Status management, completion handling
- **Security**: Status validation, access control

### Temporary Files

#### 63. **`TempV.php`** - Temporary Version 1
- **Function**: Temporary development file
- **Features**: Development testing, temporary functionality
- **Security**: Development environment, temporary access

#### 64. **`TempV2.php`** - Temporary Version 2
- **Function**: Temporary development file
- **Features**: Development testing, temporary functionality
- **Security**: Development environment, temporary access

#### 65. **`TempV3.php`** - Temporary Version 3
- **Function**: Temporary development file
- **Features**: Development testing, temporary functionality
- **Security**: Development environment, temporary access

### Additional Files

#### 66. **`slip.php`** - Slip Generation
- **Function**: Slip/document generation
- **Features**: Document creation, slip printing
- **Security**: Document security, access control

#### 67. **`landing (1).html`** - Landing Page Template
- **Function**: HTML landing page template
- **Features**: Static landing page, template system
- **Security**: Static content, template security

#### 68. **`debug.log`** - Debug Logging
- **Function**: Debug information logging
- **Features**: Error tracking, debug information
- **Security**: Log security, error handling

### File Categories Summary

#### **Core System (4 files)**
- Landing page, configuration, routing, server setup

#### **Authentication & Security (6 files)**
- Login, registration, logout, email verification, password reset

#### **User Interface (4 files)**
- Dashboards, profile management, user interfaces

#### **Document Management (4 files)**
- File upload, serving, validation, security

#### **Certificate Management (4 files)**
- Certificate generation, history, requests, management

#### **Appointment & Consultation (7 files)**
- Booking, scheduling, consultation recording, cancellation

#### **Email & Communication (4 files)**
- Email configuration, sending, templates, notifications

#### **Admin Management (5 files)**
- Document management, appointment administration, patient profiles

#### **Content Management (8 files)**
- CMS functionality, announcements, content editing

#### **Form Processing (8 files)**
- Various form handlers for different user types and purposes

#### **User Management (2 files)**
- Walk-in registration, dental patient management

#### **Utility & Testing (5 files)**
- Testing, password generation, completion tracking

#### **Temporary Files (3 files)**
- Development and testing files

#### **Additional Files (3 files)**
- Slip generation, landing template, debug logging

### Security Features Across All Files

#### **Authentication & Authorization**
- Session management in all user-facing files
- Role-based access control throughout
- CSRF protection in all forms
- Admin-only access restrictions

#### **Data Validation & Sanitization**
- Input validation in all form processing files
- SQL injection prevention with prepared statements
- File upload security in upload-related files
- XSS prevention with output sanitization

#### **File Security**
- Secure file serving with authentication
- File type and size validation
- Path traversal prevention
- Access control for sensitive files

#### **Error Handling**
- Comprehensive error logging
- Graceful error handling
- User-friendly error messages
- Debug information for development

## Troubleshooting

### Common Issues

#### Database Connection
```php
// Check config.php settings
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
```

#### File Upload Issues
```bash
# Check upload directory permissions
sudo chmod -R 777 Uploads/
sudo chown -R www-data:www-data Uploads/
```

#### Email Issues
```php
// Test email configuration
php mailer.php
```

#### PDF Generation Issues
```php
// Test TCPDF installation
php test_tcpdf.php
```

### Error Logs
- Check `error.log` for PHP errors
- Check `error_log.txt` for detailed logs
- Check Apache error logs for server issues

### Performance Optimization
- Enable PHP OPcache
- Configure MySQL query cache
- Use CDN for static assets
- Implement caching strategies

## FAQ

### General Questions

**Q: How do I register as a new user?**
A: Visit the signup page, fill in your information, verify your email, and complete your profile.

**Q: What documents do I need to upload?**
A: Requirements vary by user type. Check the upload page for your specific requirements.

**Q: How do I book an appointment?**
A: Log in to your account, go to the appointment page, and select your preferred date and time.

**Q: How do I request a medical certificate?**
A: Upload required documents first, then request a certificate through the medical documents page.

### Technical Questions

**Q: How do I fill up forms and annotate signatures electronically?**
A: Use any PDF reader/editor (Adobe Acrobat, Foxit, Xodo, Microsoft Edge). Insert an image of your signature or use the "draw" tool.

**Q: What if I don't have a laptop or phone with internet access?**
A: Visit the College of Engineering Computer Laboratory (Campus A) or the Health Services Center for assistance.

**Q: How secure is my data?**
A: All data is encrypted, validated, and stored securely. We use industry-standard security practices.

### Admin Questions

**Q: How do I access the admin dashboard?**
A: Log in with admin credentials and you'll be redirected to the admin dashboard.

**Q: How do I issue a medical certificate?**
A: Go to Medical Documents → Find the request → Review documents → Click "Release MedCert".

**Q: How do I manage announcements?**
A: Use the Content Management → Announcements section to create and edit announcements.

### Support

For technical support or questions:
- Contact the WMSU Health Services IT team
- Check the system documentation
- Review error logs for specific issues
- Contact system administrators

---

## License

This project uses open-source libraries:
- [PHPMailer](https://github.com/PHPMailer/PHPMailer) (LGPL)
- [TCPDF](https://github.com/tecnickcom/TCPDF) (LGPL)
- [HTML2PDF](https://github.com/spipu/html2pdf) (LGPL)
- [phpdotenv](https://github.com/vlucas/phpdotenv) (BSD)

See individual library licenses for details.

---

## Credits

Developed for Western Mindanao State University Health Services.

**System Version**: 1.0  
**Last Updated**: July 2025  
**Maintained By**: WMSU Health Services IT Team

---

*For questions, support, or feature requests, please contact the WMSU Health Services IT team.* 

---

## PHP File Function Reference

Below is a comprehensive list of all PHP files in the WMSU Health Services Management System, with a brief description of each file's purpose and function:

### Core System & Routing
- **index.php**: Landing page. Displays public content and redirects logged-in users to their homepage.
- **config.php**: Central database connection and configuration file.
- **router.php**: Handles URL routing and redirects to appropriate modules/pages.

### Authentication & User Management
- **login.php**: User login, session management, and access control. Handles admin/user redirection and session validation.
- **signup.php**: User registration, input validation, and email verification. Integrates with mailer for verification links.
- **logout.php**: Destroys user session and logs out the user.
- **verify_email.php**: Handles email verification via token after signup.
- **forgot_password.php**: Initiates password reset process via email.
- **reset_password.php**: Handles password reset using a secure token.
- **resend_email.php**: Resends verification email if the user did not receive it.

### User Profile & Forms
- **form.php**: Main profile form for students, employees, and incoming freshmen. Handles profile data and file uploads.
- **Elemform.php**: Profile form for parents/guardians and their children (Kindergarten/Elementary).
- **StudentProfileUpdate.php**: Allows users to update their profile information.
- **profile.php**: Displays and manages the user's profile page.
- **patient-profile.php**: Displays patient profile for admin/staff.

### Document Management
- **uploaddocs.php**: Handles uploading of required documents (IDs, certificates, etc.) for all user types.
- **upload.php**: General file upload handler.
- **uploadmedcert.php**: Handles uploading of medical certificates.
- **cms_upload.php**: Admin content/document upload for CMS.
- **serve_file.php**: Securely serves uploaded files to authorized users.

### Medical & Dental Certificates
- **medcert.php**: User interface for requesting/viewing medical certificates.
- **medcertRequest.php**: Handles medical certificate requests and admin approval.
- **getMedCertHistory.php**: Fetches the history of issued medical certificates for a user.
- **send_certificate.php**: Admin endpoint to generate and send medical certificates as PDFs via email.

### Appointments & Consultations
- **appointment.php**: User interface for booking medical/dental appointments.
- **appointmentCalendar.php**: Displays appointment calendar for users/admins.
- **submit-consultation.php**: Handles submission of consultation forms.
- **submit-prescription.php**: Handles submission of prescriptions by staff.
- **view-consultation.php**: Displays consultation details to users/admins.
- **record_consultation.php**: Admin/staff endpoint to record consultation details.
- **mark_consultation_complete.php**: Marks a consultation as complete.
- **mark_consultation_completed.php**: Alternate endpoint for marking consultations as completed.
- **cancel_consultation.php**: Allows users/admins to cancel a scheduled consultation.

### Admin & Dashboard
- **adminhome.php**: Admin dashboard with analytics, user counts, and quick access to management modules.
- **admin-account.php**: Admin account management page.
- **cms_homepage.php**: Admin CMS for editing homepage content.
- **cms_index.php**: Admin CMS for editing landing/index page content.
- **editAnnouncement.php**: Admin interface for editing announcements.
- **editAnnouncements.php**: Alternate/legacy admin announcement editor.
- **postAnnouncement.php**: Admin endpoint to post new announcements.

### Announcements & Content
- **announcements.php**: Displays all announcements to users.
- **announcement1.php**: Alternate/legacy announcement display page.

### Medical & Dental Modules
- **medical-documents.php**: Admin interface for managing medical document submissions, certificate requests, and consultation advice.
- **medical-appointments.php**: Admin interface for managing medical appointments.
- **dental-appointments.php**: Admin interface for managing dental appointments.
- **dentalForm.php**: Dental consultation form for users.
- **dentalForm2.php**: Alternate/legacy dental form.
- **DentalRequest.php**: Handles dental certificate requests and admin approval.
- **dentalConsent.php**: Dental consent form for minors.
- **dent.php**: General dental module entry point.

### Email & Communication
- **mailer.php**: Centralized email sending logic using PHPMailer.
- **send_consult_email.php**: Sends consultation advice to users via email.
- **send_link.php**: Sends password reset or verification links via email.

### Miscellaneous & Utilities
- **generate_passwords.php**: Utility for generating random passwords (admin use).
- **slip.php**: Generates printable consultation/appointment slips.
- **wmsuwaiver.php**: Displays/downloads the WMSU waiver form.
- **test_login.php**: Test page for login functionality.
- **test_tcpdf.php**: Test page for PDF generation using TCPDF.
- **TempV.php**, **TempV2.php**, **TempV3.php**: Temporary/test files for development.

### Error Handling & Logs
- **error_log**, **error_log.txt**, **debug.log**: Log files for error and debug output.

---

> **Note:** Some files may have legacy or alternate versions (e.g., `dentalForm2.php`, `editAnnouncements.php`, `announcement1.php`) retained for backward compatibility or reference.

For more details on each module, refer to the in-code documentation and comments within each file. 

---

## PHP Files for Super Admin Only

The following PHP files are intended for exclusive or primary use by the Super Admin role. These files provide access to high-level administrative, content management, and system-wide settings:

### Admin Dashboard & Management
- **adminhome.php**: Main dashboard for all admin users, with Super Admin having access to all analytics, user management, and system-wide controls.
- **admin-account.php**: Manage admin accounts, including adding, editing, or removing other admin users (Super Admin only).

### Content Management System (CMS)
- **cms_index.php**: Edit and manage the content of the landing/index page. Super Admin can update banners, text, and images.
- **cms_homepage.php**: Edit and manage the homepage content, including announcements, staff, and core values.
- **cms_upload.php**: Upload and manage documents and images for the CMS.

### Announcement Management
- **editAnnouncement.php**: Edit, update, or delete announcements. Super Admin can manage all announcements.
- **editAnnouncements.php**: Alternate/legacy announcement editor for bulk or advanced editing.
- **postAnnouncement.php**: Post new announcements to the system.

### User & System Management
- **generate_passwords.php**: Utility for generating random passwords for new admin or user accounts (Super Admin only).
- **router.php**: System routing logic; Super Admin may have access for advanced configuration.

### Other Files with Super Admin Privileges
While the following files are accessible to all admin types, the Super Admin has the highest level of access and can perform all actions within them:
- **adminhome.php** (full access)
- **cms_index.php**, **cms_homepage.php**, **cms_upload.php** (full content control)
- **admin-account.php** (can manage all admin users, including Medical and Dental Admins)
- **editAnnouncement.php**, **editAnnouncements.php**, **postAnnouncement.php** (can manage all announcements)

> **Note:** Super Admin can also access all files available to Medical Admin and Dental Admin, but the files listed above are either exclusive to Super Admin or where Super Admin has the highest level of control. 