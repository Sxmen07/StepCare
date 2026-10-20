CREATE DATABASE IF NOT EXISTS stepcare DEFAULT CHARSET=utf8mb4;
USE stepcare;

/* ================= USERS ================= */
CREATE TABLE users (
  userID INT PRIMARY KEY AUTO_INCREMENT,
  firstName VARCHAR(50) NOT NULL,
  lastName VARCHAR(50) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
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
  failedAttempt INT NOT NULL DEFAULT 0,
  lockedUntil TIMESTAMP NULL DEFAULT NULL
);

/* ================= ADMINS ================= */
CREATE TABLE admins (
  adminID INT PRIMARY KEY AUTO_INCREMENT,
  firstName VARCHAR(50) NOT NULL,
  lastName VARCHAR(50) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  passwordHash VARCHAR(255) NOT NULL,
  adminPhotoURL VARCHAR(500) NULL,
  createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
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

/* ================= APPOINTMENTS ================= */
CREATE TABLE appointments (
  appointmentID INT PRIMARY KEY AUTO_INCREMENT,
  serviceID INT NOT NULL,
  userID INT NOT NULL,
  nurseID INT NULL,
  backupNurseID INT NULL,
  startDateTime DATETIME NOT NULL,
  endDateTime DATETIME NOT NULL,
  locationAddress VARCHAR(500) NOT NULL,
  specialInstructions TEXT NULL,
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
  paymentStatus ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
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

/* ================= NURSE REQUEST OFF DAY ================= */
CREATE TABLE nurseRequestOffDay (
  requestID INT PRIMARY KEY AUTO_INCREMENT,
  nurseID INT NOT NULL,
  adminID INT NULL,
  startTime DATETIME NOT NULL,
  endTime DATETIME NOT NULL,
  requestDate DATE NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  reason TEXT NULL,
  createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (nurseID) REFERENCES nurses(nurseID) ON DELETE CASCADE,
  FOREIGN KEY (adminID) REFERENCES admins(adminID) ON DELETE SET NULL
);

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
  recipientType ENUM('user', 'nurse', 'admin') NOT NULL,
  recipientID INT NOT NULL,
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

/* ================= DATA INSERTS ================= */

INSERT INTO services
  (serviceName, category, description, serviceImageURL, price, price_unit,
   average_rating, review_count, durationMin, durationMax, isTopRecommendation)
VALUES
  ('Elderly Mobility and Vitals Assessment', 'Elderly',
   'Comprehensive routine wellness triage for aging loved ones. Focus on fall prevention, mobility exercises, medication reconciliation, and heart monitoring.',
   '../../images/services/elderly.png',
   85.00, 'visit', 0.00, 0, 45, 60, 1),

  ('Post-Operative Wound Care', 'Post-op',
   'Full antiseptic dressing renewal, surgical drain management, infection surveillance, and direct encrypted report submission to your surgeon.',
   '../../images/services/woundcare.png',
   120.00, 'visit', 0.00, 0, 75, 90, 1),

  ('Stroke Recovery & Rehabilitation Care', 'Neuro',
   'Targeted post-acute stroke intervention. Includes cranial nerve assessments, swallowing reflex safety oversight, and gait-assistance physical therapy.',
   '../../images/services/stroke.png',
   160.00, 'visit', 0.00, 0, 90, 120, 0),

  ('Maternal & Newborn Well-Visit', 'Postnatal',
   'Expert dual assessment for recovering mothers and newborns. Includes lactation consulting, neonatal weight check, jaundice scan, and umbilical cord hygiene.',
   '../../images/services/maternal.png',
   110.00, 'visit', 0.00, 0, 60, 90, 0),

  ('Palliative & Comfort Care Package', 'Hospice',
   'Holistic pain titration and dignity management. Designed to relieve distress, breathlessness, and nausea while providing clinical guidance to close family members.',
   '../../images/services/palliative.png',
   190.00, 'visit', 0.00, 0, 90, 120, 0),

  ('Diabetic Foot Ulcer & Debridement', 'Chronic',
   'Targeted limb-salvage wound protocols for chronic diabetic patients. Incorporates localized bio-burden removal, enzymatic dressing, and blood perfusion evaluation.',
   '../../images/services/diabetic.png',
   145.00, 'visit', 0.00, 0, 60, 75, 0);


INSERT INTO serviceFeatures (serviceID, featureText) VALUES
  (1, 'Blood pressure, ECG spot-check & glucose'),
  (1, 'Prescription pillbox reconciliation'),
  (1, 'Passive range-of-motion physical therapy'),

  (2, 'Hospital-grade sterile vacuum dressing'),
  (2, 'Surgical drain output analysis & flush'),
  (2, 'Vital sign panel + EHR surgeon sync'),

  (3, 'Neuro-vital checks & pupillary response'),
  (3, 'Dysphagia swallowing assistance & feeding'),
  (3, 'Transfer technique coaching for family'),

  (4, 'Infant precision scale & transcutaneous jaundice'),
  (4, 'Post-C-section or episiotomy healing review'),
  (4, 'Certified lactation latch optimization'),

  (5, 'Continuous pain & breakthrough medication management'),
  (5, 'Comfort re-positioning & pressure injury defense'),
  (5, '24/7 Priority direct line to attending physician'),

  (6, 'Hydrogel & antimicrobial silver dressing'),
  (6, 'Portable Doppler pedal arterial blood flow test'),
  (6, 'Insulin pump check & HbA1c log sync');


INSERT INTO serviceBadges (serviceID, badgeText) VALUES
  (1, 'Top Surgeon Recommended'),
  (1, 'Geriatric Care'),
  (2, 'Top Surgeon Recommended'),
  (3, 'Neuro Specialized'),
  (4, 'Pediatric & Mom'),
  (4, 'Comfort First'),
  (5, 'Hospice & Relief'),
  (6, 'Endocrine Protocol'),
  (6, 'Chronic Care');


INSERT INTO admins (firstName, lastName, email, passwordHash, adminPhotoURL) 
VALUES (
    'jonsen', 
    'choong', 
    'jonsenchoong@admin.stepcare.com', 
    '$2y$10$d8TcKDDmXcXFk17gueIQvOKdOH1GOybkTtfBiw7ox/Zf897B7V/tq', 
    NULL
);