-- -------------------------------------------------------------------------
-- install/database.sql — ข้อมูลตั้งต้นของ enroll
--
-- ⚠️ ห้ามประกาศตารางแกนซ้ำที่นี่ ตารางแกนของ Gcms (user, category, logs,
-- login_attempt, number, migration, user_meta, user_session, language)
-- อยู่ใน install/core.sql ซึ่งเหมือนกันทุกโปรเจ็ค — ก่อนแก้ ไฟล์นี้ประกาศซ้ำ
-- ไว้ 8 ตาราง ทำให้ **ติดตั้งใหม่ไม่ได้เลย** (Table 'app_category' already exists)
--
-- ตารางของโมดูล enroll อยู่ใน modules/enroll/install/database.sql
-- ไฟล์นี้จึงเหลือเฉพาะข้อมูลตั้งต้นที่ลงในตารางแกน ซึ่งไม่มีโมดูลไหนเป็นเจ้าของ
-- -------------------------------------------------------------------------

--
-- Dumping data for table `{prefix}_category`
--

INSERT INTO `{prefix}_category` (`type`, `category_id`, `topic`, `color`, `is_active`) VALUES
('department', '1', 'บริหาร', NULL, 1),
('department', '2', 'จัดซื้อจัดจ้าง', NULL, 1),
('department', '3', 'บุคคล', NULL, 1);
