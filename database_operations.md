# ডাটাবেস আর্কাইভিং এবং ম্যানেজমেন্ট গাইড

## সার্ভার তথ্য
- **সার্ভার ১**: xxx.xxx.xxx.xxx (আর্কাইভিং সার্ভার)
- **সার্ভার ২**: xxx.xxx.xxx.xxx (এক্সপোর্ট সার্ভার)

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

## PHP টেবিল আর্কাইভ স্ক্রিপ্ট দিনের পর দিন

### কমান্ড ফরম্যাট
```bash
php table_archive.php <source_db> <target_db> <main_table> <date_formate> <from_date> <to_date> <table_suffix>

php table_archive.php blink_dob z_blink_dob_archive sdp_6d_callback "d_date" "2025-07-01" "2025-07-02 23:59:59" "202507_01_02"
```

### একসাথে সব কমান্ড রান করা
```bash
php table_archive.php blink_dob z_blink_dob_archive sdp_6d_callback "d_date" "2025-07-01" "2025-07-02 23:59:59" "202507_01_02" && \
php table_archive.php robi_sm z_robi_sm_archive sdp_send_sms_log "d_date" "2025-07-07" "2025-07-08 23:59:59" "202507_07_08" && \
php table_archive.php gp_global z_gp_global_archive renews "created_at" "2025-07-01" "2025-07-02 23:59:59" "202507_01_02"
```
## PHP টেবিল আর্কাইভ চাঙ্ক, আইডি ধরে কমান্ড

```bash
php table_chunk_archive_id.php <source_db> <target_db> <main_table> <id_column_name> <date_column_name> <from_date> <to_date> <table_suffix> [chunk_size]

php table_chunk_archive_id.php blink_dob z_blink_dob_archive charge_log log_id d_date "2025-07-01" "2025-07-10 23:59:59" 01_10 10000
```

## PHP টেবিল আর্কাইভ চাঙ্ক, আইডি ধরে সাথে লগ ফাইল দেখা কমান্ড

```bash
php table_chunk_archive_id.php \
blink_dob \
z_blink_dob_archive \
charge_log \
log_id \
d_date \
"2025-07-01" \
"2025-07-10 23:59:59" \
01_03 \
1000 > chunk_01_03.log 2>&1 &

-----------or

php table_chunk_archive_id.php blink_dob z_blink_dob_archive charge_log log_id d_date "2025-07-01" "2025-07-10 23:59:59" 01_03 10000 > chunk_01_03.log 2>&1 &

এখানে:
 chunk_01_03.log → আউটপুট যাবে এই ফাইলে
 2>&1 → error আউটপুটও একই ফাইলে
 & → এটি background এ যাবে

```
## একই সার্ভার ডাটাবেস আর্কাইভ
``` bash
mysqldump -u <username> -p'<password>' -v <db name> <table name> > <path>/<table name>.sql

mysqldump -u root -p'n0@ccess4U' -v z_robi_sm_archive sdp_broadcast_content_202504 > sdp_broadcast_content_202504.sql
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

### টেবিল স্ট্রাকচার দেখা ও নতুন টেবিল ক্রিয়েট করা 
```sql
SHOW CREATE TABLE `sdp_6d_raw_subs_payment`;
```

## mysqldump ব্যবহার করে ডাটা এক্সপোর্ট

### নতুন টেবিলের জন্য ডাটা এক্সপোর্ট করা (সম্পূর্ণ স্ট্রাকচার সহ)
```bash
mysqldump --single-transaction --routines --triggers --skip-extended-insert --skip-comments --complete-insert --no-tablespaces -u root -p'351f0*57034e1a025#' -h 192.168.20.14 z_blink_dob_archive sdp_6d_raw_subs_payment_202506_12 > /var/www/wwwroot/operation/db-transfer/sdp_6d_raw_subs_payment_202506_12.sql && sed -i '1d' /var/www/wwwroot/operation/db-transfer/sdp_6d_raw_subs_payment_202506_12.sql


| অপশন                   | কাজ                                                                                                  
| ------------------------ | ----------------------------------------------------------------------------------------------------
| `--single-transaction`   | ডাম্প করার সময় ট্রাঞ্জেকশন ইউজ করে যাতে ডাম্প করা সময় টেবিল লক না হয় (InnoDB টেবিলের জন্য খুব উপকারী)
| `--skip-comments`        | SQL ডাম্প ফাইলের শুরুতে কোনো কমেন্ট (যেমন: Dumped by mysqldump...) লেখা হবে না।                     
| `--routines`             | স্টোরড প্রোসিজার এবং ফাংশনগুলিও ডাম্প হবে।                                                            
| `--triggers`             | ট্রিগারও ডাম্প হবে।                                                                                   
| `--skip-extended-insert` | প্রতিটি ইনসার্ট আলাদাভাবে হবে (উন্নত রিডেবিলিটি, কিন্তু ফাইল সাইজ বড়)।                                  
| `--complete-insert`      | প্রতিটি `INSERT` স্টেটমেন্টে কলামের নাম উল্লেখ থাকবে।                                               
| `--no-tablespaces`       | টেবিলস্পেস সংক্রান্ত তথ্য বাদ দেবে (নতুন ভার্সনের MySQL-এর জন্য গুরুত্বপূর্ণ, নয়তো এরর হতে পারে)।       

```

### ক্রিয়েট করা পুরানো টেবিলের জন্য ডাটা এক্সপোর্ট করা
```bash
mysqldump --single-transaction --routines --triggers --skip-extended-insert --skip-comments --complete-insert --no-tablespaces -u root -p'351f0*57034e1a025#' -h 192.168.20.14 z_blink_dob_archive sdp_6d_raw_subs_payment_202506_12 > /var/www/wwwroot/operation/db-transfer/sdp_6d_raw_subs_payment_202506_12.sql && sed -i -e '1d' -e '/CREATE TABLE/,/);/d' -e '/DROP TABLE IF EXISTS `sdp_6d_raw_subs_payment_202506_12`;/d' /var/www/wwwroot/operation/db-transfer/sdp_6d_raw_subs_payment_202506_12.sql
```

### ফাইল কম্প্রেশন
```bash
gzip /var/www/wwwroot/operation/db-transfer/sdp_6d_raw_subs_payment_202506_12.sql
```

## Windows PowerShell (Run as Administrator) দিয়ে ফাইল প্রসেসিং

### টেবিল ভিতরে নাম পরিবর্তন (PowerShell)
```powershell
(Get-Content "G:\z-db\blink_dob\sdp_6d_raw_subs_payment_202506_12.sql") `
| ForEach-Object { $_ -replace "sdp_6d_raw_subs_payment_202506_12", "sdp_6d_raw_subs_payment_202506" } `
| Set-Content "G:\z-db\blink_dob\sdp_final_12.sql"
```

## ডাটাবেস ইমপোর্ট

### স্থানীয় ডাটাবেসে ইমপোর্ট
```bash
mysql -u root -p -v database_name < "G:\B2M\z-db\gp_global\sdp_final_12.sql"

------------- or

mysql -u root -p -v database_name < "G:\\z-db\\blink_dob\\sdp_final_12.sql"

------------- or

D:\xampp8\mysql\bin\mysql.exe -u root -p -v blink_dob < "G:\z-db\blink_dob\sdp_6d_raw_subs_payment_202506_11.sql"
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



