# 🩺 StepCare — Doorstep Healthcare & Nursing Provider

> **Course:** 5027CMD Web Development  
> **Project Type:** Group Web Application  

StepCare is a doorstep nursing and home-care platform designed to deliver convenient home nursing, physiotherapy, and personal care services. 

---

## 📌 Project Overview

Bringing loved ones to hospitals or care centers can be challenging for busy families, the elderly, individuals recovering from illness, or people living with disabilities. StepCare bridges this gap by delivering quality care directly to the user's doorstep, giving families peace of mind.

### Core Capabilities
* **For Patients & Families:** Create patient profiles, browse and filter care packages, receive tailored package recommendations, book appointments with precise dates/times/locations, process online payments, and receive timely notifications.
* **For Care Nurses:** Build professional profiles, upload verification credentials (licenses and certificates), set availability, handle booking requests, and log care documentation upon visit check-in/out.
* **For Administrators:** Manage care packages, oversee appointment disputes and refunds, verify nurse qualifications, trigger automated backup nurse systems, and monitor overall system activity via an analytics dashboard.

---


## 👥 Group Contributions & Module Breakdown

### 🛠️ Jonsen Choong Zhe Yi

* **Idea & System Architecture:** Conceptualized the doorstep healthcare service platform to assist elderly, disabled individuals, and busy family members who struggle with hospital travel. Designed the overall system workflows and the primary database table schemas (with add-ons and collaborative modifications).
* **Authentication:** Login and Sign Up modules (handling email/password authentication and user registration data collection).
* **Customer Platform:**
   * **Topbar & Footer / About Us (Homepage):** Platform overview, service transparency metrics, live statistics (completed services, qualified nurses, user feedback), and service introductions.
   * **Services & Booking:** Service filtering (by type and search), detailed service cards with ratings/comments, and appointment booking flows with patient details and payment proof uploading.
   * **FamilyCare:** A dedicated tracking page allowing users to view family members' appointment activities to stay updated without constant checking.
   * **My Appointments:** Personal booking details, assigned nurse tracking, and live activity status views.
   * **Notifications:** Real-time updates for appointment receipts, payment approvals, nurse assignments, activity changes, and system alerts.
* **Nurse Platform:**
   * **Appointment Management:** Calendar view of assigned appointments, with activity status updates ('on the way', 'reached', 'start service', 'service completed').
   * **Appointment History:** Filterable history log for completed and canceled appointments categorized by service type and time range.
* **Admin Platform:**
   * **Service Management:** Superadmin-exclusive management console featuring a live user preview on the left and service configuration tools on the right (add, edit, delete services).
* **Testing & Quality Assurance:** Conducted integration and system testing to verify logical flow robustness and overall system requirements.

### 🎨 Ooi Yun Pei

* **Design & Layout Consultant:** Handled UI/UX layout design, styling elements, and visual consistency across all platform pages.
* **Customer Platform:**
   * **My Profile:** Personal information editing and appointment history management.
* **Nurse Platform (Topbar, Sidebar & Core Pages):**
   * **Dashboard:** Overview of earnings, completed services, today's upcoming appointments, and notifications.
   * **Applications:** Leave application management with transparent viewable leave quotas and verification status tracking.
   * **My Profile:** Personal information editing and daily appointment schedule view.
   * **Notifications:** Notification center for nurse alerts.
* **Admin Platform (Topbar, Sidebar & Core Pages):**
   * **Dashboard:** Overview of total revenue, active bookings, registered nurses, unscheduled bookings, recent service bookings, and fast-action queues.
   * **Appointment Verification:** Reviewing and approving/rejecting customer payment proofs followed by nurse assignment workflows.
   * **Application Management:** Reviewing nurse leave requests and applications (with superadmin control over leave policies).
   * **Appointment & Nurse Reviews:** Managing post-service reviews and applying moderation actions for sensitive feedback.
   * **User Management:** Access control and account status handling (e.g., restricting users creating fake/damaging appointments).
   * **Staff Management:** Creating and editing nurse and admin accounts (with superadmin privileges for creation, editing, and deletion).
   * **My Profile:** Admin profile editing and appointment schedule overview.
   * **Notifications:** Broadcasting notifications to targeted custom lists of customers, nurses, or admins.

---

## 🛠️ Tech Stack

* **Frontend:** HTML5, Tailwind CSS, JavaScript
* **Backend:** PHP (8.0 or newer), PHPMailer for email
* **Database:** MySQL / MariaDB (via XAMPP)
* **Local Server:** Apache (via XAMPP)
* **Version Control:** Git & GitHub

---

## 🚀 Getting Started

Follow these steps to run StepCare on your own computer.

### Prerequisites

| Tool | Purpose | Download |
| --- | --- | --- |
| **Git** | Clone the repository | https://git-scm.com/downloads |
| **XAMPP** (PHP 8.0 or newer) | Apache web server + MySQL database | https://www.apachefriends.org |
| **Web browser** | Chrome, Edge or Firefox | – |
| **Gmail account + App Password** *(optional)* | Only needed for email notifications and password reset | https://myaccount.google.com/apppasswords |

### Installation

#### Step 1 — Install XAMPP

1. Download and install XAMPP with the default options (make sure **Apache**, **MySQL** and **phpMyAdmin** are ticked).
2. Open the **XAMPP Control Panel**.

#### Step 2 — Clone the repository into XAMPP's `htdocs` folder

The project must be inside `htdocs`, and the folder must be named **`StepCare`** (the app uses `/StepCare/...` paths).

| OS | `htdocs` location |
| --- | --- |
| Windows | `C:\xampp\htdocs` |
| macOS | `/Applications/XAMPP/xamppfiles/htdocs` |
| Linux | `/opt/lampp/htdocs` |

Open a terminal (Command Prompt / PowerShell on Windows) and run:

```bash
cd C:\xampp\htdocs
git clone https://github.com/Sxmen07/StepCare.git StepCare
```

*(Replace `C:\xampp\htdocs` with the path for your OS from the table above.)*

You should now have `C:\xampp\htdocs\StepCare\StepCare.sql` and the `user`, `nurse` and `admin` folders.

#### Step 3 — Start Apache and MySQL

In the XAMPP Control Panel, click **Start** next to **Apache** and **MySQL**. Both should turn green.

#### Step 4 — Create the database with `StepCare.sql`

1. Open phpMyAdmin: http://localhost/phpmyadmin
2. Click the **Import** tab at the top.
3. Click **Choose File** and select `StepCare.sql` from the project folder (`htdocs/StepCare/StepCare.sql`).
4. Scroll down and click **Import** (or **Go**).

The file creates a database named `stepcare`, all of its tables, the sample services, and one super admin account. You do not need to create the database by hand.

*(Command-line alternative: `C:\xampp\mysql\bin\mysql -u root < C:\xampp\htdocs\StepCare\StepCare.sql`)*

#### Step 5 — Check the database connection

Open `config/db_connect.php`. It is already set to the XAMPP defaults, so no change is needed unless you changed your MySQL password:

```php
$servername = 'localhost';
$username   = 'root';
$password   = '';          // XAMPP default is empty
$dbname     = 'stepcare';
```

#### Step 6 — Set up email (optional)

StepCare sends email notifications and password-reset emails through Gmail. Without this step the rest of the system still works, but emails will not be sent.

1. Turn on 2-Step Verification for your Google account, then create an **App Password** at https://myaccount.google.com/apppasswords.
2. In the project root, create or edit the file named `.env` and add:

   ```env
   GMAIL_USER=your_email@gmail.com
   GMAIL_PASS=your_16_character_app_password
   ```

> ⚠️ Use an App Password, not your normal Gmail password, and never share or commit your real `.env` values.

#### Step 7 — Run the application

Open your browser and go to:

**http://localhost/StepCare/user/aboutUs/aboutUs.php**

You should see the StepCare home page (About Us).

### Using the system

| Role | Where to start | Account |
| --- | --- | --- |
| **Customer** | http://localhost/StepCare/loginNsignup/login/login.html | Choose **Sign Up** to register a new account |
| **Admin** | Same login page (redirects to the admin dashboard) | Super admin is seeded by `StepCare.sql`: `jonsenchoong@admin.stepcare.com`,password: Admin@123|
| **Nurse** | Same login page (redirects to the nurse dashboard) | Created by an admin under **Staff Management** |


The login page sends each user to the right dashboard automatically, based on their account type.

**Set your own admin password:** open http://localhost/StepCare/passwordHASH_management/make_hash.php, change the password inside that file first if you want a different one, copy the hash it prints, then paste it into the `passwordHash` column of your admin row in phpMyAdmin (`stepcare` → `admins`).
