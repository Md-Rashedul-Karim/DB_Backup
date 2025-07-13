# ডাটাবেস আর্কাইভিং এবং ম্যানেজমেন্ট গাইড

## সার্ভার তথ্য
- **সার্ভার ১**: 118.67.213.169 (আর্কাইভিং সার্ভার)
- **সার্ভার ২**: 118.67.213.177 (এক্সপোর্ট সার্ভার)

## ডাটাবেস কানেকশন

### MySQL কানেকশন
```bash
mysql -u root -p'n0@ccess4U'
use blink_dob;
```

### ম্যানুয়াল আর্কাইভ টেবিল তৈরি
```sql
CREATE TABLE z_blink_dob_archive.sdp_6d_raw_subs_payment_202506_11_31 
LIKE blink_dob.sdp_6d_raw_subs_payment;

INSERT INTO z_blink_dob_archive.sdp_6d_raw_subs_payment_202506_11_31 
SELECT * FROM blink_dob.sdp_6d_raw_subs_payment 
WHERE (`d_date` between '2025-06-11' and '2025-06-31 23:59:59');
```

## PHP টেবিল আর্কাইভ স্ক্রিপ্ট

### কমান্ড ফরম্যাট
```bash
php table_archive.php <source_db> <target_db> <main_table> <date_formate> <from_date> <to_date> <table_suffix>
```

### বিভিন্ন ডাটাবেসের জন্য আর্কাইভিং

#### blink_dob ডাটাবেস
```bash
php table_archive.php blink_dob z_blink_dob_archive sdp_6d_callback "d_date" "2025-07-01" "2025-07-02 23:59:59" "202507_01_02"
```

#### robi_sm ডাটাবেস
```bash
php table_archive.php robi_sm z_robi_sm_archive sdp_send_sms_log "d_date" "2025-07-07" "2025-07-08 23:59:59" "202507_07_08"
```

#### gp_global ডাটাবেস
```bash
php table_archive.php gp_global z_gp_global_archive renews "created_at" "2025-07-01" "2025-07-02 23:59:59" "202507_01_02"
```

### একসাথে সব কমান্ড রান করা
```bash
php table_archive.php blink_dob z_blink_dob_archive sdp_6d_callback "d_date" "2025-07-01" "2025-07-02 23:59:59" "202507_01_02" && \
php table_archive.php robi_sm z_robi_sm_archive sdp_send_sms_log "d_date" "2025-07-07" "2025-07-08 23:59:59" "202507_07_08" && \
php table_archive.php gp_global z_gp_global_archive renews "created_at" "2025-07-01" "2025-07-02 23:59:59" "202507_01_02"
```

## ডাটা এক্সপোর্ট (118.67.213.177 সার্ভার)

### একক এক্সপোর্ট
```bash
cd /var/www/wwwroot/operation/db-transfer && php db_exports.php charge_log_202502
```

### মাল্টি-লাইন এক্সপোর্ট
```bash
cd /var/www/wwwroot/operation/db-transfer && \
php db_exports.php z_robi_sm_archive sdp_send_sms_log_202507_07_08 && \
php db_exports.php z_gp_global_archive renews_202507_01_02 && \
php db_exports.php z_blink_dob_archive sdp_6d_callback_202507_01_02
```

### টেবিল স্ট্রাকচার দেখা
```sql
SHOW CREATE TABLE `sdp_6d_raw_subs_payment`;
```

## mysqldump ব্যবহার করে ডাটা এক্সপোর্ট

### নতুন টেবিলের জন্য (সম্পূর্ণ স্ট্রাকচার সহ)
```bash
mysqldump --single-transaction --routines --triggers --skip-extended-insert --skip-comments --complete-insert --no-tablespaces -u root -p'351f0*57034e1a025#' -h 192.168.20.14 z_blink_dob_archive sdp_6d_raw_subs_payment_202506_12 > /var/www/wwwroot/operation/db-transfer/sdp_6d_raw_subs_payment_202506_12.sql && sed -i '1d' /var/www/wwwroot/operation/db-transfer/sdp_6d_raw_subs_payment_202506_12.sql
```

### পুরানো টেবিলের জন্য (শুধুমাত্র ডাটা)
```bash
mysqldump --single-transaction --routines --triggers --skip-extended-insert --skip-comments --complete-insert --no-tablespaces -u root -p'351f0*57034e1a025#' -h 192.168.20.14 z_blink_dob_archive sdp_6d_raw_subs_payment_202506_12 > /var/www/wwwroot/operation/db-transfer/sdp_6d_raw_subs_payment_202506_12.sql && sed -i -e '1d' -e '/CREATE TABLE/,/);/d' -e '/DROP TABLE IF EXISTS `sdp_6d_raw_subs_payment_202506_12`;/d' /var/www/wwwroot/operation/db-transfer/sdp_6d_raw_subs_payment_202506_12.sql
```

### ফাইল কম্প্রেশন
```bash
gzip /var/www/wwwroot/operation/db-transfer/sdp_6d_raw_subs_payment_202506_12.sql
```

## Windows PowerShell দিয়ে ফাইল প্রসেসিং

### টেবিল নাম পরিবর্তন (PowerShell)
```powershell
(Get-Content "G:\z-db\blink_dob\sdp_6d_raw_subs_payment_202506_12.sql") `
| ForEach-Object { $_ -replace "sdp_6d_raw_subs_payment_202506_12", "sdp_6d_raw_subs_payment_202506" } `
| Set-Content "G:\z-db\blink_dob\sdp_final_12.sql"
```

## ডাটাবেস ইমপোর্ট

### স্থানীয় ডাটাবেসে ইমপোর্ট
```bash
mysql -u root -p database_name < "G:\B2M\z-db\gp_global\sdp_final_12.sql"
```

### টেবিল ডিলিট করা
```sql
DROP TABLE IF EXISTS table_name;
```

## MySQL টেবিল মেইনটেনেন্স

### একক টেবিল মেইনটেনেন্স
```sql
ANALYZE TABLE `table_name`;
OPTIMIZE TABLE `table_name`;
REPAIR TABLE `table_name`;
```

### সব টেবিল একসাথে মেইনটেনেন্স
```bash
mysqlcheck -u root -p --optimize --all-databases
mysqlcheck -u root -p --analyze --all-databases
mysqlcheck -u root -p --check --all-databases
```

## সম্পূর্ণ ডাটাবেস আর্কাইভিং কমান্ড

### Robi_sm ডাটাবেস
```bash
php table_archive.php robi_sm z_robi_sm_archive sdp_send_sms_log "d_date" "2025-07-01" "2025-07-10 23:59:59" "202507_01_10" && \
php table_archive.php robi_sm z_robi_sm_archive sdp_broadcast_content "date_added" "2025-07-01" "2025-07-10 23:59:59" "202507_01_10" && \
php table_archive.php robi_sm z_robi_sm_archive sdp_sequential_broadcast "date_added" "2025-07-01" "2025-07-10 23:59:59" "202507_01_10"
```

### Blink_dob ডাটাবেস
```bash
php table_archive.php blink_dob z_blink_dob_archive sdp_6d_raw_subs_payment "d_date" "2025-07-01" "2025-07-10 23:59:59" "202507_01_10" && \
php table_archive.php blink_dob z_blink_dob_archive sdp_6d_raw_callback "d_date" "2025-07-01" "2025-07-10 23:59:59" "202507_01_10" && \
php table_archive.php blink_dob z_blink_dob_archive sdp_6d_callback "d_date" "2025-07-01" "2025-07-10 23:59:59" "202507_01_10" && \
php table_archive.php blink_dob z_blink_dob_archive charge_log "d_date" "2025-07-01" "2025-07-10 23:59:59" "202507_01_10" && \
php table_archive.php blink_dob z_blink_dob_archive sdp_6d_raw_consent "d_date" "2025-07-01" "2025-07-10 23:59:59" "202507_01_10"
```

### GP_global ডাটাবেস
```bash
php table_archive.php gp_global z_gp_global_archive renew_logs "created_at" "2025-07-01" "2025-07-10 23:59:59" "202507_01_10" && \
php table_archive.php gp_global z_gp_global_archive consents "created_at" "2025-07-01" "2025-07-10 23:59:59" "202507_01_10" && \
php table_archive.php gp_global z_gp_global_archive renews "created_at" "2025-07-01" "2025-07-10 23:59:59" "202507_01_10" && \
php table_archive.php gp_global z_gp_global_archive charge_log "created_at" "2025-07-01" "2025-07-10 23:59:59" "202507_01_10" && \
php table_archive.php gp_global z_gp_global_archive partner_payments "created_at" "2025-07-01" "2025-07-10 23:59:59" "202507_01_10"
```

## ডাটাবেস সাইজ পরিমাপ

### টেবিলের সাইজ দেখা (Adminer)
```sql
SELECT 
  table_name AS 'Table Name',
  ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)'  
FROM 
  information_schema.TABLES
WHERE 
  table_schema = 'আপনার_ডাটাবেসের_নাম'      
ORDER BY 
  (data_length + index_length) DESC;
```

### সব ডাটাবেসের সাইজ
```sql
SELECT 
  table_schema AS 'Database Name',
  ROUND(SUM(data_length + index_length) / 1024 / 1024 / 1024, 3) AS 'Total Size (GB)'
FROM 
  information_schema.tables
GROUP BY 
  table_schema
ORDER BY 
  SUM(data_length + index_length) DESC;
```

## ডাটাবেস/টেবিল ডিলিট করা

### পূর্ণ ডাটাবেস ডিলিট
```sql
DROP DATABASE your_database_name;
```

### টেবিল ডিলিট
```sql
DROP TABLE IF EXISTS customers;
```

## গুরুত্বপূর্ণ নোট

1. **sed কমান্ড**: `sed -i '1d'` প্রথম লাইন মুছে দেয়
2. **মাল্টি-লাইন কমান্ড**: ব্যাকস্ল্যাশ (\) এর পরে কোনো স্পেস রাখবেন না
3. **ব্যাকআপ**: আর্কাইভিং এর আগে সর্বদা ব্যাকআপ নিন
4. **ডেট ফরম্যাট**: সঠিক ডেট কলামের নাম ব্যবহার করুন (d_date, created_at)
5. **অনুমতি**: স্ক্রিপ্ট চালানোর আগে প্রয়োজনীয় অনুমতি নিশ্চিত করুন

## টিপস
- রেগুলার আর্কাইভিং এর জন্য ক্রন জব সেট করুন
- বড় টেবিল এর জন্য চাঙ্কিং ব্যবহার করুন
- আর্কাইভ করার পর প্রোডাকশন টেবিল থেকে পুরাতন ডেটা মুছে ফেলুন
- নিয়মিত আর্কাইভ টেবিল অপটিমাইজ করুন