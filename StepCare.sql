CREATE DATABASE IF NOT EXISTS stepcare DEFAULT CHARSET=utf8mb4;
USE stepcare;

/* ================= USERS ================= */
CREATE TABLE users (
  userID INT PRIMARY KEY AUTO_INCREMENT,
  firstName VARCHAR(50) NOT NULL,
  lastName VARCHAR(50) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  accountStatus ENUM('Active', 'Banned') NOT NULL DEFAULT 'Active',
  banReason TEXT NULL DEFAULT NULL,
  passwordHash VARCHAR(255) NOT NULL,
  userPhotoURL VARCHAR(500) NULL,
  IC VARCHAR(20) NULL,
  phoneNumber VARCHAR(20) NULL,
  gender ENUM('male','female','rather not to say') NULL,
  dateOfBirth DATE NULL,
  address VARCHAR(500) NULL,
  emergencyContactName VARCHAR(100) NOT NULL,
  emergencyContactGender ENUM('male','female','rather not to say') NULL,
  emergencyContactRelationship VARCHAR(50) NULL,
  emergencyContactPhone VARCHAR(20) NOT NULL,
  createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  failedAttempt INT NOT NULL DEFAULT 0,
  lockedUntil TIMESTAMP NULL DEFAULT NULL
);

/* ================= NURSES ================= */
CREATE TABLE nurses (
  nurseID INT PRIMARY KEY AUTO_INCREMENT,
  firstName VARCHAR(50) NOT NULL,
  lastName VARCHAR(50) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  passwordHash VARCHAR(255) NOT NULL,
  nursePhotoURL VARCHAR(500) NULL,
  phoneNumber VARCHAR(20) NULL,
  gender ENUM('male','female','rather not to say') NULL,
  dateOfBirth DATE NULL,
  specialization VARCHAR(100) NULL,
  bio TEXT NULL,
  averageRating DECIMAL(3,2) NOT NULL DEFAULT 0.00,
  totalSessionsCompleted INT NOT NULL DEFAULT 0,
  isVerified TINYINT(1) NOT NULL DEFAULT 0,
  createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  createdByAdminID INT(11) NULL DEFAULT 1,
  failedAttempt INT NOT NULL DEFAULT 0,
  lockedUntil TIMESTAMP NULL DEFAULT NULL
);

/* ================= ADMINS ================= */
CREATE TABLE admins (
  adminID INT PRIMARY KEY AUTO_INCREMENT,
  firstName VARCHAR(50) NOT NULL,
  lastName VARCHAR(50) NOT NULL,
  adminRole ENUM('Super Admin', 'Admin') NOT NULL DEFAULT 'Admin',
  email VARCHAR(255) NOT NULL UNIQUE,
  passwordHash VARCHAR(255) NOT NULL,
  adminPhotoURL VARCHAR(500) NULL,
  createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  createdByAdminID INT(11) NULL DEFAULT 1,
  failedAttempt INT NOT NULL DEFAULT 0,
  lockedUntil TIMESTAMP NULL DEFAULT NULL
);

/* ================= FAMILY CARE ================= */
CREATE TABLE familyCare (
  familyCareID INT PRIMARY KEY AUTO_INCREMENT,
  userID INT NOT NULL,
  accessCode VARCHAR(50) NOT NULL,
  codeExpiredTime TIMESTAMP NULL,
  isUsed TINYINT(1) NOT NULL DEFAULT 0,
  createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (accessCode),
  FOREIGN KEY (userID) REFERENCES users(userID) ON DELETE CASCADE
);

/* ================= FAMILY CONNECTIONS ================= */
CREATE TABLE familyConnections (
  connectionID INT PRIMARY KEY AUTO_INCREMENT,
  ownerID INT NOT NULL,
  viewerID INT NOT NULL,
  relationship VARCHAR(50) NULL,
  createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  isPinned TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY unique_pair (ownerID, viewerID),
  FOREIGN KEY (ownerID) REFERENCES users(userID) ON DELETE CASCADE,
  FOREIGN KEY (viewerID) REFERENCES users(userID) ON DELETE CASCADE
);

/* ================= SERVICES ================= */
CREATE TABLE services (
  serviceID INT PRIMARY KEY AUTO_INCREMENT,
  serviceName VARCHAR(255) NOT NULL,
  category VARCHAR(100) NULL,
  description TEXT NULL,
  serviceImageURL VARCHAR(500) NULL,
  price DECIMAL(10,2) NOT NULL,
  price_unit VARCHAR(50) NOT NULL DEFAULT 'visit',
  average_rating DECIMAL(3,2) NOT NULL DEFAULT 0.00,
  review_count INT NOT NULL DEFAULT 0,
  durationMin INT NULL,
  durationMax INT NULL,
  slotConfig VARCHAR(100) DEFAULT '08:00 - 18:00',
  isTopRecommendation TINYINT(1) NOT NULL DEFAULT 0,
  createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE serviceFeatures (
  serviceFeatureID INT PRIMARY KEY AUTO_INCREMENT,
  serviceID INT NOT NULL,
  featureText VARCHAR(255) NOT NULL,
  createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (serviceID) REFERENCES services(serviceID) ON DELETE CASCADE
);

CREATE TABLE serviceBadges (
  serviceBadgeID INT PRIMARY KEY AUTO_INCREMENT,
  serviceID INT NOT NULL,
  badgeText VARCHAR(255) NOT NULL,
  createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (serviceID) REFERENCES services(serviceID) ON DELETE CASCADE
);

CREATE TABLE serviceTimeSlots (
  slotID INT PRIMARY KEY AUTO_INCREMENT,
  serviceID INT NOT NULL,
  timeSlotLabel VARCHAR(50) NOT NULL,
  isAvailable TINYINT(1) NOT NULL DEFAULT 1,
  maxCapacity INT NOT NULL DEFAULT 5,
  currentBookings INT NOT NULL DEFAULT 0,
  FOREIGN KEY (serviceID) REFERENCES services(serviceID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* ================= APPOINTMENTS ================= */
CREATE TABLE appointments (
  appointmentID INT PRIMARY KEY AUTO_INCREMENT,
  serviceID INT NOT NULL,
  userID INT NOT NULL,
  patientName VARCHAR(100) NULL,
  nurseID INT NULL,
  backupNurseID INT NULL,
  startDateTime DATETIME NOT NULL,
  endDateTime DATETIME NOT NULL,
  locationAddress VARCHAR(500) NOT NULL,
  specialInstructions TEXT NULL,
  nurseNotes TEXT NULL,
  documentURL VARCHAR(500) NULL,
  appointmentStatus ENUM('pending','confirmed','in_progress','completed','cancelled','declined') NOT NULL DEFAULT 'pending',
  cancellationReason TEXT NULL,
  dayCount INT NOT NULL DEFAULT 1,
  createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (serviceID) REFERENCES services(serviceID),
  FOREIGN KEY (userID) REFERENCES users(userID),
  FOREIGN KEY (nurseID) REFERENCES nurses(nurseID) ON DELETE SET NULL,
  FOREIGN KEY (backupNurseID) REFERENCES nurses(nurseID) ON DELETE SET NULL
);

/* ================= NURSE ON DUTY ================= */
CREATE TABLE nurseOnDuty (
  id INT PRIMARY KEY AUTO_INCREMENT,
  appointmentID INT NOT NULL,
  nurseID INT NOT NULL,
  checkInTime TIMESTAMP NULL,
  checkOutTime TIMESTAMP NULL,
  FOREIGN KEY (appointmentID) REFERENCES appointments(appointmentID) ON DELETE CASCADE,
  FOREIGN KEY (nurseID) REFERENCES nurses(nurseID)
);

/* ================= PAYMENTS ================= */
CREATE TABLE payments (
  paymentID INT PRIMARY KEY AUTO_INCREMENT,
  appointmentID INT NOT NULL,
  paymentDate TIMESTAMP NULL DEFAULT NULL,
  totalAmount DECIMAL(10,2) NOT NULL,
  paymentMethod VARCHAR(50) NULL,
  transactionID VARCHAR(100) NULL,
  paymentStatus ENUM('pending','pending_verification','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  rejectionReason TEXT NULL DEFAULT NULL,
  paymentProofURL VARCHAR(500) NULL,
  createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (appointmentID),
  FOREIGN KEY (appointmentID) REFERENCES appointments(appointmentID) ON DELETE CASCADE
);

/* ================= NURSE AVAILABILITY ================= */
CREATE TABLE nurseAvailability (
  availabilityID INT PRIMARY KEY AUTO_INCREMENT,
  nurseID INT NOT NULL,
  dayOfWeek ENUM('Mon','Tue','Wed','Thu','Fri','Sat','Sun') NOT NULL,
  startTime TIME NOT NULL,
  endTime TIME NOT NULL,
  createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (nurseID) REFERENCES nurses(nurseID) ON DELETE CASCADE
);

/* ================= LEAVE TYPES ================= */
CREATE TABLE leave_types (
  leaveTypeID INT(11) NOT NULL AUTO_INCREMENT,
  leaveName VARCHAR(100) NOT NULL,
  targetRole ENUM('all', 'nurse', 'admin') NOT NULL DEFAULT 'all',
  daysPerYear INT(11) NOT NULL DEFAULT 14,
  isCustom TINYINT(1) NOT NULL DEFAULT 0,
  createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (leaveTypeID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* ================= NURSE REQUEST OFF DAY ================= */
CREATE TABLE nurseRequestOffDay (
  requestID INT PRIMARY KEY AUTO_INCREMENT,
  nurseID INT NOT NULL,
  adminID INT NULL,
  leaveTypeID INT(11) NULL,
  startTime DATETIME NOT NULL,
  endTime DATETIME NOT NULL,
  requestDate DATE NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  reason TEXT NULL,
  rejectionReason TEXT NULL DEFAULT NULL,
  proofDocumentURL VARCHAR(255) NULL DEFAULT NULL,
  createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (nurseID) REFERENCES nurses(nurseID) ON DELETE CASCADE,
  FOREIGN KEY (adminID) REFERENCES admins(adminID) ON DELETE SET NULL
);

/* ================= ADMIN REQUEST OFF DAY ================= */
CREATE TABLE adminRequestOffDay (
  requestID INT(11) NOT NULL AUTO_INCREMENT,
  adminID INT(11) NOT NULL,
  leaveTypeID INT(11) NULL,
  requestDate DATE NOT NULL,
  startTime TIME NOT NULL,
  endTime TIME NOT NULL,
  reason TEXT NOT NULL,
  status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  rejectionReason TEXT NULL,
  reviewedBySuperAdminID INT(11) NULL,
  proofDocumentURL VARCHAR(255) NULL,
  createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (requestID),
  INDEX (adminID),
  INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* ================= SERVICE COMMENTS ================= */
CREATE TABLE serviceComments (
  serviceCommentID INT PRIMARY KEY AUTO_INCREMENT,
  serviceID INT NOT NULL,
  userID INT NOT NULL,
  appointmentID INT NULL,
  rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
  helpfulCount INT NOT NULL DEFAULT 0,
  comment TEXT NULL,
  isHidden TINYINT(1) NOT NULL DEFAULT 0,
  createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (serviceID) REFERENCES services(serviceID) ON DELETE CASCADE,
  FOREIGN KEY (userID) REFERENCES users(userID) ON DELETE CASCADE,
  FOREIGN KEY (appointmentID) REFERENCES appointments(appointmentID) ON DELETE SET NULL
);

/* ================= NURSE REVIEWS ================= */
CREATE TABLE nurseReviews (
  nurseReviewID INT PRIMARY KEY AUTO_INCREMENT,
  nurseID INT NOT NULL,
  appointmentID INT NOT NULL,
  userID INT NOT NULL,
  rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
  comment TEXT NULL,
  isHidden TINYINT(1) NOT NULL DEFAULT 0,
  createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (nurseID) REFERENCES nurses(nurseID) ON DELETE CASCADE,
  FOREIGN KEY (appointmentID) REFERENCES appointments(appointmentID) ON DELETE CASCADE,
  FOREIGN KEY (userID) REFERENCES users(userID) ON DELETE CASCADE
);

/* ================= TRACK SERVICE STATUS ================= */
CREATE TABLE trackServiceStatus (
  id INT PRIMARY KEY AUTO_INCREMENT,
  appointmentID INT NOT NULL,
  serviceID INT NOT NULL,
  userID INT NOT NULL,
  nurseID INT NULL,
  status ENUM('pending', 'assigned', 'on_the_way', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
  message TEXT NOT NULL,
  createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (appointmentID) REFERENCES appointments(appointmentID) ON DELETE CASCADE,
  FOREIGN KEY (serviceID) REFERENCES services(serviceID) ON DELETE CASCADE,
  FOREIGN KEY (userID) REFERENCES users(userID) ON DELETE CASCADE,
  FOREIGN KEY (nurseID) REFERENCES nurses(nurseID) ON DELETE SET NULL,
  INDEX idx_appointment_timeline (appointmentID, createdAt DESC)
);

/* ================= NOTIFICATIONS ================= */
CREATE TABLE notifications (
  notificationID INT PRIMARY KEY AUTO_INCREMENT,
  recipientType VARCHAR(100) NOT NULL DEFAULT 'admin',
  recipientID INT NOT NULL,
  senderType ENUM('admin', 'nurse', 'system') NOT NULL DEFAULT 'system',
  senderID INT NULL,
  title VARCHAR(150) NOT NULL,
  message TEXT NOT NULL,
  type VARCHAR(50) NULL,
  linkURL VARCHAR(500) NULL,
  isRead TINYINT(1) NOT NULL DEFAULT 0,
  createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_recipient (recipientType, recipientID, isRead, createdAt DESC)
);

/* ================= PASSWORD RESETS ================= */
CREATE TABLE passwordResets (
  resetID INT PRIMARY KEY AUTO_INCREMENT,
  userID INT NOT NULL,
  tokenHash VARCHAR(255) NOT NULL,
  expiresAt TIMESTAMP NOT NULL,
  usedAt TIMESTAMP NULL DEFAULT NULL,
  createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (tokenHash),
  FOREIGN KEY (userID) REFERENCES users(userID) ON DELETE CASCADE
);

/* ================= APPOINTMENT TIMELINE ================= */
CREATE TABLE appointment_timeline (
    timelineID    INT AUTO_INCREMENT PRIMARY KEY,
    appointmentID INT         NOT NULL,
    status        VARCHAR(50) NOT NULL,
    title         VARCHAR(150) NOT NULL,
    message       TEXT NULL,
    createdBy     ENUM('system','user','nurse','admin') NOT NULL DEFAULT 'system',
    createdByID   INT NULL,
    createdAt     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_appt_time (appointmentID, createdAt),
    CONSTRAINT fk_timeline_appt
        FOREIGN KEY (appointmentID)
        REFERENCES appointments(appointmentID)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


/* ================= DATA INSERTS (Exact Match to stepcare (2).sql) ================= */

INSERT INTO users (`userID`, `firstName`, `lastName`, `email`, `accountStatus`, `banReason`, `passwordHash`, `userPhotoURL`, `IC`, `phoneNumber`, `gender`, `dateOfBirth`, `address`, `emergencyContactName`, `emergencyContactGender`, `emergencyContactRelationship`, `emergencyContactPhone`, `createdAt`, `failedAttempt`, `lockedUntil`) VALUES
(1, 'Jonsen', 'Choong', 'jonsenchoong@gmail.com', 'Active', NULL, '$2y$10$ZcXz974a0/JnMfLAKwpHaeoBMhHYpxQ/.RoXnu/nbeQyc2URUAGzi', '/StepCare/images/profiles/avatar_user_1_1793349079.jpg', '031109070021', '60 16-4099038', 'male', '2003-11-09', '22, Lorong Hijau 8', 'Choong', NULL, NULL, '60 12-3456789', '2026-10-11 16:29:09', 0, NULL),
(2, 'Joker', 'C', 'sxmen07@gmail.com', 'Active', NULL, '$2y$10$l3UWOMSWEhsSOM6hAUzyfeykyS5y0Em1Ed4mQf/n462WXuJXME1sK', '/StepCare/images/profiles/avatar_user_2_1794389642.jpeg', '031109070021', '0123456789', 'male', '2003-11-09', '123,Testing', 'Testing', NULL, NULL, '0123456789', '2026-10-22 15:24:25', 0, NULL),
(3, 'Sarah', 'Connor', 'sarah@gmail.com', 'Active', NULL, '$2y$10$ZcXz974a0/JnMfLAKwpHaaoBMhHYpxQ/.RoXnu/hbeQ...', NULL, '031109070022', NULL, NULL, NULL, NULL, 'John Connor', NULL, NULL, '0123456789', '2026-10-31 16:58:02', 0, NULL),
(4, 'Michael', 'Scofield', 'michael@gmail.com', 'Banned', 'Violated community terms', '$2y$10$ZcXz974a0/JnMfLAKwpHaaoBMhHYpxQ/.RoXnu/hbeQ...', NULL, '0123456789', NULL, NULL, NULL, NULL, 'Lincoln Burrows', NULL, NULL, '0129876543', '2026-10-31 16:58:02', 0, NULL);

INSERT INTO admins (`adminID`, `firstName`, `lastName`, `adminRole`, `email`, `passwordHash`, `adminPhotoURL`, `createdAt`, `createdByAdminID`, `failedAttempt`, `lockedUntil`) VALUES
(1, 'jonsen', 'choong', 'Super Admin', 'jonsenchoong@admin.stepcare.com', '$2y$10$0iyqxou94sL9576ucfmWZ.KgFAsdnk15P6oVu4QG7qAy5Xbo/OQo2', NULL, '2026-10-13 03:01:37', 1, 0, NULL),
(2, 'testing', '123', 'Admin', 'testing123@admin.stepcare.com', '$2y$10$rNwj0EwEIw9Rux6eUqXzyuIdhcKk.jL0kEzCRiU3no8VgNQ4EQ3Ea', NULL, '2026-11-07 15:07:08', 1, 0, NULL);

INSERT INTO nurses (`nurseID`, `firstName`, `lastName`, `email`, `passwordHash`, `nursePhotoURL`, `phoneNumber`, `gender`, `dateOfBirth`, `specialization`, `bio`, `averageRating`, `totalSessionsCompleted`, `isVerified`, `createdAt`, `createdByAdminID`, `failedAttempt`, `lockedUntil`) VALUES
(1, 'Jonsen', 'Choong', 'jonsenchoong@nurse.stepcare.com', '$2y$10$XxJPzjcGFtenSMauJiDPteGW0DeEmmSMwFXJAtvIlQFUE8OgOes1e', NULL, '+60123456789', 'male', NULL, 'General Nursing & Elderly Care', 'Certified professional nurse with extensive experience in clinical care, patient triage, and health monitoring.', 3.50, 0, 1, '2026-10-31 14:33:44', 1, 0, NULL);

INSERT INTO services (`serviceID`, `serviceName`, `category`, `description`, `serviceImageURL`, `price`, `price_unit`, `average_rating`, `review_count`, `durationMin`, `durationMax`, `slotConfig`, `isTopRecommendation`, `createdAt`) VALUES
(1, 'Elderly Mobility and Vitals Assessment', 'Elderly', 'Comprehensive routine wellness triage for aging loved ones. Focus on fall prevention, mobility exercises, medication reconciliation, and heart monitoring.', '../../images/services/1794390992_stroke.jpg', 85.00, 'visit', 3.50, 2, 45, 60, '08:00 - 18:00', 1, '2026-10-12 15:53:17'),
(2, 'Post-Operative Wound Care', 'Post-op', 'Full antiseptic dressing renewal, surgical drain management, infection surveillance, and direct encrypted report submission to your surgeon.', '../../images/services/woundcare.png', 120.00, 'visit', 0.00, 0, 75, 90, '08:00 - 18:00', 1, '2026-10-12 15:53:17'),
(3, 'Stroke Recovery & Rehabilitation Care', 'Neuro', 'Targeted post-acute stroke intervention. Includes cranial nerve assessments, swallowing reflex safety oversight, and gait-assistance physical therapy.', '../../images/services/stroke.png', 160.00, 'visit', 0.00, 0, 90, 120, '08:00 - 18:00', 0, '2026-10-12 15:53:17'),
(4, 'Maternal & Newborn Well-Visit', 'Postnatal', 'Expert dual assessment for recovering mothers and newborns. Includes lactation consulting, neonatal weight check, jaundice scan, and umbilical cord hygiene.', '../../images/services/maternal.png', 110.00, 'visit', 0.00, 0, 60, 90, '08:00 - 18:00', 0, '2026-10-12 15:53:17'),
(5, 'Palliative & Comfort Care Package', 'Hospice', 'Holistic pain titration and dignity management. Designed to relieve distress, breathlessness, and nausea while providing clinical guidance to close family members.', '../../images/services/palliative.png', 190.00, 'visit', 0.00, 0, 90, 120, '08:00 - 18:00', 0, '2026-10-12 15:53:17'),
(6, 'Diabetic Foot Ulcer & Debridement', 'Chronic', 'Targeted limb-salvage wound protocols for chronic diabetic patients. Incorporates localized bio-burden removal, enzymatic dressing, and blood perfusion evaluation.', '../../images/services/diabetic.png', 145.00, 'visit', 0.00, 0, 60, 75, '08:00 - 18:00', 0, '2026-10-12 15:53:17');

INSERT INTO serviceFeatures (`serviceFeatureID`, `serviceID`, `featureText`, `createdAt`) VALUES
(7, 3, 'Neuro-vital checks & pupillary response', '2026-10-12 15:53:17'),
(8, 3, 'Dysphagia swallowing assistance & feeding', '2026-10-12 15:53:17'),
(9, 3, 'Transfer technique coaching for family', '2026-10-12 15:53:17'),
(10, 4, 'Infant precision scale & transcutaneous jaundice', '2026-10-12 15:53:17'),
(11, 4, 'Post-C-section or episiotomy healing review', '2026-10-12 15:53:17'),
(12, 4, 'Certified lactation latch optimization', '2026-10-12 15:53:17'),
(13, 5, 'Continuous pain & breakthrough medication management', '2026-10-12 15:53:17'),
(14, 5, 'Comfort re-positioning & pressure injury defense', '2026-10-12 15:53:17'),
(15, 5, '24/7 Priority direct line to attending physician', '2026-10-12 15:53:17'),
(16, 6, 'Hydrogel & antimicrobial silver dressing', '2026-10-12 15:53:17'),
(17, 6, 'Portable Doppler pedal arterial blood flow test', '2026-10-12 15:53:17'),
(18, 6, 'Insulin pump check & HbA1c log sync', '2026-10-12 15:53:17'),
(25, 2, 'Hospital-grade sterile vacuum dressing', '2026-11-11 09:14:09'),
(26, 2, 'Surgical drain output analysis & flush', '2026-11-11 09:14:09'),
(27, 2, 'Vital sign panel + EHR surgeon sync', '2026-11-11 09:14:09'),
(28, 1, 'Blood pressure, ECG spot-check & glucose', '2026-11-11 09:56:32'),
(29, 1, 'Prescription pillbox reconciliation', '2026-11-11 09:56:32'),
(30, 1, 'Passive range-of-motion physical therapy', '2026-11-11 09:56:32');

INSERT INTO serviceBadges (`serviceBadgeID`, `serviceID`, `badgeText`, `createdAt`) VALUES
(1, 1, 'Top Surgeon Recommended', '2026-10-12 15:53:17'),
(2, 1, 'Geriatric Care', '2026-10-12 15:53:17'),
(3, 2, 'Top Surgeon Recommended', '2026-10-12 15:53:17'),
(4, 3, 'Neuro Specialized', '2026-10-12 15:53:17'),
(5, 4, 'Pediatric & Mom', '2026-10-12 15:53:17'),
(6, 4, 'Comfort First', '2026-10-12 15:53:17'),
(7, 5, 'Hospice & Relief', '2026-10-12 15:53:17'),
(8, 6, 'Endocrine Protocol', '2026-10-12 15:53:17'),
(9, 6, 'Chronic Care', '2026-10-12 15:53:17');

INSERT INTO servicetimeslots (`slotID`, `serviceID`, `timeSlotLabel`, `isAvailable`, `maxCapacity`, `currentBookings`) VALUES
(6, 2, '8:00 AM', 1, 5, 0),
(7, 2, '12:00 PM', 1, 5, 0),
(8, 1, '8:00 AM', 1, 5, 0),
(9, 1, '11:00 AM', 1, 5, 0),
(10, 1, '2:00 AM', 1, 5, 0),
(11, 1, '5:00 AM', 1, 5, 0);