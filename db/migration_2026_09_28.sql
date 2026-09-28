-- Migration for databases created before 2026-09-28.
--
-- schema.sql uses CREATE TABLE IF NOT EXISTS, so re-running it will NOT add
-- these to a database that already exists. Run this file once instead:
--
--   mysql -u root barangay_information_system < db/migration_2026_09_28.sql
--
-- A fresh install created from schema.sql already has everything below and can
-- skip this file entirely.

USE barangay_information_system;

-- 1. Email address on resident records, so contact details can be validated
--    and stored alongside the phone number.
ALTER TABLE residents ADD COLUMN email VARCHAR(150) NULL AFTER contact;

-- 2. Indexes for the columns the list pages search and filter on.
ALTER TABLE residents ADD INDEX idx_residents_name (name);
ALTER TABLE residents ADD INDEX idx_residents_zone (zone);
ALTER TABLE incidents ADD INDEX idx_incidents_status (status);

-- 3. Case numbers must be unique — two incidents sharing CN-2026-001 makes the
--    number useless as a reference.
--
--    If this statement fails with "Duplicate entry", the existing data already
--    contains repeats. Find them first:
--
--      SELECT case_no, COUNT(*) FROM incidents GROUP BY case_no HAVING COUNT(*) > 1;
--
--    then renumber the duplicates before re-running this line.
ALTER TABLE incidents ADD UNIQUE KEY uq_incidents_case_no (case_no);
