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

/* ================= FAMILY CARE (access-code sharing) ================= */
CREATE TABLE familyCare (
  familyCareID INT PRIMARY KEY AUTO_INCREMENT,
  userID INT NOT NULL,
  accessCode VARCHAR(50) NOT NULL,
  codeExpiredTime TIMESTAMP NULL,
  createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (accessCode),
  FOREIGN KEY (userID) REFERENCES users(userID) ON DELETE CASCADE
);

/* ================= SERVICES ================= */
CREATE TABLE services (
  serviceID INT PRIMARY KEY AUTO_INCREMENT,
  serviceName VARCHAR(255) NOT NULL,
  category VARCHAR(100) NULL,
  description TEXT NULL,
  price DECIMAL(10,2) NOT NULL,
  price_unit VARCHAR(50) NOT NULL DEFAULT 'visit',
  average_rating DECIMAL(3,2) NOT NULL DEFAULT 0.00,
  review_count INT NOT NULL DEFAULT 0,
  durationMin INT NULL,                 -- minutes
  durationMax INT NULL,                 -- minutes
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
  appointmentStatus ENUM('confirmed','in_progress','completed','cancelled','declined')
      NOT NULL DEFAULT 'confirmed',
  cancellationReason TEXT NULL,
  dayCount INT NOT NULL DEFAULT 1,
  createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (serviceID)     REFERENCES services(serviceID),
  FOREIGN KEY (userID)        REFERENCES users(userID),
  FOREIGN KEY (nurseID)       REFERENCES nurses(nurseID) ON DELETE SET NULL,
  FOREIGN KEY (backupNurseID) REFERENCES nurses(nurseID) ON DELETE SET NULL
);

/* ================= NURSE ON DUTY (check-in / check-out per appointment) ================= */
CREATE TABLE nurseOnDuty (
  id INT PRIMARY KEY AUTO_INCREMENT,
  appointmentID INT NOT NULL,
  nurseID INT NOT NULL,
  checkInTime TIMESTAMP NULL,
  checkOutTime TIMESTAMP NULL,
  FOREIGN KEY (appointmentID) REFERENCES appointments(appointmentID) ON DELETE CASCADE,
  FOREIGN KEY (nurseID)       REFERENCES nurses(nurseID)
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
  UNIQUE (appointmentID),               -- one payment per appointment
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
  FOREIGN KEY (serviceID)     REFERENCES services(serviceID) ON DELETE CASCADE,
  FOREIGN KEY (userID)        REFERENCES users(userID) ON DELETE CASCADE,
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
  FOREIGN KEY (nurseID)       REFERENCES nurses(nurseID) ON DELETE CASCADE,
  FOREIGN KEY (appointmentID) REFERENCES appointments(appointmentID) ON DELETE CASCADE,
  FOREIGN KEY (userID)        REFERENCES users(userID) ON DELETE CASCADE
);