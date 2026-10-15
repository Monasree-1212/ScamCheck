# ScamCheck — Job and Internship Scam Detection Platform (Redis Edition)

A web-based platform built with **PHP 8.1+** and **Redis** (via Memurai on Windows) designed to identify, report, assess, and moderate fraudulent job and internship offerings targeting college students and early-career professionals.

---

## 1. Overview & Objectives

College students and fresh graduates are increasingly targeted by fraudulent recruitment schemes, including:
- **Fake Offer Letters** demanding laptop or equipment security deposits.
- **Task and Investment Scams** conducted over Telegram and WhatsApp requiring prepaid recharge fees.
- **Identity Phishing** soliciting bank account credentials, OTPs, or government IDs.
- **Exploitative Unpaid Traineeships** charging upfront "registration" or "training certification" fees.

**ScamCheck** provides a centralized, transparent platform where job seekers can report suspicious recruitment activities, view verified scam reports, calculate objective risk scores based on concrete red flags, and access administrator-moderated community alerts.

---

## 2. Technology Stack & Architecture

- **Backend:** PHP 8.1+ (CLI & Apache SAPI)
- **Primary Database:** Redis (Memurai 8.2.10 on Windows)
- **Redis Client:** Predis `^2.2` (PHP Redis Client)
- **Web Server:** XAMPP Apache 2.4 HTTP Server
- **Frontend / Styling:** Vanilla CSS (Light-themed, clean typography, responsive design, zero third-party UI framework bloat)
- **Security:** Standard PHP `password_hash()` (bcrypt), CSRF token generation & `hash_equals()` validation, session fixation protection, and strict HTML output escaping.

---

## 3. Redis Data Modeling & Namespace Design

ScamCheck uses **Redis as its primary database** without relying on SQL or document databases. All keys are strictly scoped under the `scamcheck_redis:*` prefix to ensure 100% namespace isolation and prevent collision with any other services.

### Redis Key Schema

| Key Pattern | Redis Type | Description |
| :--- | :--- | :--- |
| `scamcheck_redis:users:counter` | `STRING` (INCR) | Auto-incrementing sequence counter for user IDs |
| `scamcheck_redis:users:{id}` | `HASH` | User profile: `id`, `username`, `email`, `password_hash`, `full_name`, `role`, `status`, `created_at` |
| `scamcheck_redis:user_by_email:{email}` | `STRING` | Inverted index mapping unique email to user ID |
| `scamcheck_redis:user_by_username:{username}` | `STRING` | Inverted index mapping unique username to user ID |
| `scamcheck_redis:users:all` | `SET` | Set of all registered user IDs |
| `scamcheck_redis:reports:counter` | `STRING` (INCR) | Auto-incrementing sequence counter for report IDs |
| `scamcheck_redis:reports:{id}` | `HASH` | Complete report details: `company_name`, `job_title`, `platform`, `scam_type`, `contact_info`, `payment_demanded`, `description`, `red_flags`, `risk_score`, `risk_level`, `status`, etc. |
| `scamcheck_redis:reports:all` | `ZSET` | All reports sorted by Unix timestamp score |
| `scamcheck_redis:reports:by_status:{status}` | `SET` | Index sets for report statuses: `Pending`, `Under Review`, `Approved for Display`, `Rejected`, `Duplicate` |
| `scamcheck_redis:reports:by_user:{user_id}` | `ZSET` | Reports submitted by specific user (timestamp score) |
| `scamcheck_redis:reports:by_type:{scam_type}` | `SET` | Index set filtered by scam category |
| `scamcheck_redis:reports:by_risk:{risk_level}` | `SET` | Index set filtered by risk tier: `Low`, `Medium`, `High`, `Critical` |
| `scamcheck_redis:reports:reviews:{report_id}` | `LIST` | Chronological audit log of status changes, reviewer actions, and admin notes |
| `scamcheck_redis:search:tokens:{word}` | `SET` | Inverted token index for high-performance keyword search across company names, roles, and descriptions |

---

## 4. Key Platform Features

1. **User Authentication & Session Management:**
   - Registration with duplicate email/username checking in Redis.
   - Secure login with bcrypt hash verification and session regeneration.
   - Role-based authorization (`user` vs `admin`).
2. **Scam Report Submission:**
   - Structured submission form capturing company name, job role, recruiter email, phone, website, and payment demands.
   - Interactive checklist of standardized red flags.
   - Real-time client-side and server-side risk calculation engine.
3. **Public Verified Directory:**
   - Display only reports with `Approved for Display` status.
   - Keyword search across inverted token index in Redis.
   - Filters by scam category, severity tier, and sorting options (Newest, Oldest, Highest Risk).
4. **Moderator Dashboard & Lifecycle:**
   - Dedicated administrative queue to inspect incoming reports.
   - Status transitions supported:
     - `Pending` (initial submission state)
     - `Under Review` (investigation in progress)
     - `Approved for Display` (published to public directory)
     - `Rejected` (unsubstantiated or non-fraudulent submission)
     - `Duplicate` (consolidated into existing master report)
   - Moderator assessment notes and immutable audit trail.
5. **User Dashboard:**
   - Personal dashboard tracking report submissions, review statuses, and approval feedback.
   - Edit privileges for reports in `Pending` or `Under Review` status.
   - Secure deletion of own reports.

---

## 5. Risk Assessment Algorithm

The platform uses a deterministic, rule-based scoring engine with cumulative weights up to 100 points:

| Indicator / Red Flag | Category | Weight |
| :--- | :--- | :--- |
| **Payment Demanded** (Registration, Laptop, Security Deposit, Certificate Fee) | Financial | +35 pts |
| **Direct Offer Issued without Formal Screening or Interview** | Process | +20 pts |
| **Recruiter Uses Generic Free Email** (`@gmail.com`, `@yahoo.com`, etc.) | Verification | +15 pts |
| **Hiring/Interview Exclusively via Messaging Apps** (Telegram, WhatsApp, Signal) | Communication | +15 pts |
| **Sensitive Financial / Identity Info Demanded Upfront** (OTP, Bank Credentials, Aadhaar) | Security | +15 pts |
| **High-Pressure 24-Hour Acceptance Deadline** | Behavioral | +15 pts |
| **Spoofed Domain or Mismatched Official URL** | Verification | +15 pts |
| **Vague Tasks / YouTube Video Rating / Prepaid Recharge Tasks** | Nature of Work | +15 pts |
| **Unrealistically High Salary for Entry-Level or Unqualified Role** | Compensation | +10 pts |

### Severity Tiers:
- **0 – 29:** Low Risk (Informational)
- **30 – 59:** Medium Risk (Suspicious)
- **60 – 84:** High Risk (Dangerous)
- **85 – 100:** Critical Risk (Confirmed Scam Archetype)

---

## 6. Default Accounts (Seeded Data)

The database seeder provisions default accounts for demonstration and evaluation:

| Role | Username | Email | Password |
| :--- | :--- | :--- | :--- |
| **Administrator** | `admin` | `admin@scamcheck.local` | `Admin@123` |
| **Standard User (Student)** | `student` | `student@college.edu` | `Student@123` |
| **Standard User (Priya)** | `priyasharma` | `priya.sharma@example.com` | `User@123` |

---

## 7. Setup & Execution Guide

### Prerequisites
1. **XAMPP** installed with Apache and PHP 8.1+ at `C:\xampp`.
2. **Memurai / Redis** running on Windows port `6379`.
3. Project directory located at `C:\xampp\htdocs\ScamCheck_Redis`.

### Step 1: Verify Redis / Memurai Service
Verify that Memurai is running:
```powershell
Get-Service Memurai
```
If stopped:
```powershell
Start-Service Memurai
```

### Step 2: Verify Apache Web Server
Start Apache in XAMPP:
```powershell
Start-Process -FilePath "C:\xampp\apache\bin\httpd.exe" -WorkingDirectory "C:\xampp" -WindowStyle Hidden
```

### Step 3: Populate Demo Seed Data
Run the seeder from the project root:
```powershell
C:\xampp\php\php.exe seed.php
```

### Step 4: Run the Automated Test Suite
Execute the test runner:
```powershell
C:\xampp\php\php.exe tests/run_tests.php
```
All 23 unit and integration assertions should report `[PASS]`.

### Step 5: Access the Web Application
Open your web browser and navigate to:
```
http://localhost/ScamCheck_Redis/
```
- **Public Homepage:** `http://localhost/ScamCheck_Redis/index.php`
- **Browse Reports Directory:** `http://localhost/ScamCheck_Redis/reports.php`
- **Submit Scam Report:** `http://localhost/ScamCheck_Redis/submit-report.php`
- **Login:** `http://localhost/ScamCheck_Redis/login.php`
- **Registration:** `http://localhost/ScamCheck_Redis/register.php`
- **User Dashboard:** `http://localhost/ScamCheck_Redis/dashboard.php`
- **Admin Moderation:** `http://localhost/ScamCheck_Redis/admin-dashboard.php`
- **User Management:** `http://localhost/ScamCheck_Redis/admin-users.php`
- **Automated Test Report:** `http://localhost/ScamCheck_Redis/tests/run_tests.php`
