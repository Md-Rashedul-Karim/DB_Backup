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

CREATE TABLE z_gp_global_archive.renews_null LIKE gp_global.renews;
INSERT INTO z_gp_global_archive.renews_null SELECT * FROM gp_global.renews where created_at is null;
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

php table_chunk_archive_id.php blink_dob z_blink_dob_archive charge_log log_id d_date "2025-07-01" "2025-07-10 23:59:59" 202507_01_10 10000
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
202507_01_10 \
1000 > chunk_01_03.log 2>&1 &

-----------or

php table_chunk_archive_id.php blink_dob z_blink_dob_archive charge_log log_id d_date "2025-07-01" "2025-07-10 23:59:59" 202507_01_10 10000 > chunk_01_03.log 2>&1 &

এখানে:
 chunk_01_03.log → আউটপুট যাবে এই ফাইলে
 2>&1 → error আউটপুটও একই ফাইলে
 & → এটি background এ যাবে

```
### চাঙ্ক টেবিল একসাথে সব কমান্ড রান করা
```bash
php table_chunk_archive_id.php blink_dob z_blink_dob_archive sdp_6d_raw_subs_payment id d_date "2025-07-01" "2025-07-02 23:59:59" 202507_01_02 10000 && \
php table_chunk_archive_id.php blink_dob z_blink_dob_archive sdp_6d_raw_subs_payment id d_date "2025-07-03" "2025-07-04 23:59:59" 202507_03_04 10000 && \
php table_chunk_archive_id.php blink_dob z_blink_dob_archive sdp_6d_raw_subs_payment id d_date "2025-07-09" "2025-07-10 23:59:59" 202507_09_10 10000
```
## একই সার্ভার ডাটাবেস আর্কাইভ
``` bash
mysqldump -u <username> -p'<password>' -v <db name> <table name> > <path>/<table name>.sql

mysqldump -u root -p'n0@ccess4U' -v z_robi_sm_archive sdp_broadcast_content_202504 > sdp_broadcast_content_202504.sql
```
## ডাটা এক্সপোর্ট (118.67.213.177 সার্ভার) ভিন্ন সার্ভারে ডাটাবেস এ ইমপোর্ট
[ <b>Note</b> যে সার্ভার ডাটা এক্সপোর্ট করবো সে সার্ভার গিয়ে নিচের কম্যান্ড গুলো এক্সেকিউটি করবো। ]

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


## 🔥 টেবিল ডেটা ডিলিট, রিপেয়ার, অপ্টিমাইজ, অ্যানালাইস হবে

### টেবিল ডেটা ডিলিট ডেট ধরে 

```
php table_chunk_delete_date_range.php <source_db> <main_table> <id_column_name> <date_column> <from_date> <to_date> [chunk_size]


php table_chunk_delete_date_range.php robi_sm sdp_broadcast_content id date_added "2025-07-27" "2025-07-27 23:59:59" 10000
```
### টেবিল ডেটা ডিলিট Date ও ID ধরে 

```
php table_chunk_delete.php <source_db> <main_table> <id_column> <date_column> <from_date> <to_date> <start_id> <end_id> [chunk_size]

php table_chunk_delete_date_id_range.php z_robi_sm_archive sdp_broadcast_content_202507 id date_added "2025-07-28" "2025-07-28 23:59:59" 205 854 100

```

* এখানে

  * `205 854` → আইডি রেঞ্জ (শুরু ও শেষ)
  * `100` → প্রতি চাঙ্কে কতগুলো রো ডিলিট হবে


## ডাটাবেসের টেবিল অপ্টিমাইজ (optimize)

```mysql
  mysql -u root -p'n0@ccess4U'
  show databases;
  use blink_dob;
  optimize table sdp_6d_callback;
```

## ডাটাবেসের টেবিল অপ্টিমাইজ (optimize) একটি স্ক্রিপ্টের মাধ্যমে

```bash
 php table_optimize.php <source_db> <main_table>

 php table_optimize.php blink_dob sdp_6d_callback

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
### PHP Script (replace_tables.php)

* Make folder in htdocs like scripts
* cd F:\xampp-8\htdocs\scripts\replace_tables.php

```php

<?php
// Folder path
$folderPath = "G:/z-db/blink_dob/202508";

// Replacement patterns
$replacements = [
    [
        "old" => "sdp_6d_callback_202508_07_08",
        "new" => "sdp_6d_callback_202508",
        "output" => "G:/z-db/blink_dob/sdp_6d_callback_202508_08.sql"
    ],
	 [
        "old" => "sdp_6d_callback_202508_09_10",
        "new" => "sdp_6d_callback_202508",
        "output" => "G:/z-db/blink_dob/sdp_6d_callback_202508_10.sql"
    ],
 
];

// Buffer size (4MB chunks)
$chunkSize = 4 * 1024 * 1024;

foreach ($replacements as $item) {
    $files = glob($folderPath . "/*" . $item['old'] . "*.sql");

    if (!empty($files)) {
        $file = $files[0];
        echo "Processing: {$file}\n";

        $input = fopen($file, 'rb');
        if (!$input) {
            die("Cannot open input file: {$file}");
        }

        $output = fopen($item['output'], 'wb');
        if (!$output) {
            fclose($input);
            die("Cannot open output file: {$item['output']}");
        }

        $overlap = strlen($item['old']) - 1; // Keep last few bytes from previous chunk
        $buffer = '';

        while (!feof($input)) {
            // Read next chunk
            $chunk = fread($input, $chunkSize);
            if ($chunk === false) {
                echo "Error reading file.\n";
                break;
            }

            // Prepend leftover from last chunk
            $chunk = $buffer . $chunk;

            // Save last bytes for next loop
            $buffer = substr($chunk, -$overlap);

            // Remove overlap portion before replacing
            $processPart = substr($chunk, 0, strlen($chunk) - $overlap);

            // Replace text
            $processPart = str_replace($item['old'], $item['new'], $processPart);

            // Write processed part
            fwrite($output, $processPart);
        }

        // Process remaining buffer
        if ($buffer !== '') {
            $buffer = str_replace($item['old'], $item['new'], $buffer);
            fwrite($output, $buffer);
        }

        fclose($input);
        fclose($output);

        echo "✅ Saved to: {$item['output']}\n";
    } else {
        echo "❌ No file found for: {$item['old']}\n";
    }
}

?>

```

### মাল্টি টেবিল ভিতরে নাম পরিবর্তন করে

* Press Windows + R
* Type `cmd` and press Enter
* cd F:\xampp-8\htdocs\scripts

```bash
 php replace_tables.php 

```
### মাল্টি টেবিল modify_id

```bash
 php modify_id.php 

```

### মাল্টি টেবিল import

```bash
 php import_sql.php 

```

## ডাটাবেস ইমপোর্ট

### লোকাল পিসি ডাটাবেসে ডাটা ইনসার্ট
```bash
mysql -u root -p -v database_name < "G:\B2M\z-db\gp_global\sdp_final_12.sql"

------------- or

mysql -u root -p -v database_name < "G:\\z-db\\blink_dob\\sdp_final_12.sql"

------------- or

D:\xampp8\mysql\bin\mysql.exe -u root -p -v blink_dob < "G:\z-db\blink_dob\sdp_6d_raw_subs_payment_202506_11.sql"

```

### Duplicate ID হলে আর লোকাল PC data insert না হলে (modify_sql_duplicate_id.php, modify_sql_ignor_id.php)

<p> 
  [ Open modify_sql_ignor_id.php file and change the input, output file path and table name  and run the command prompt again ]
</p>

#### Open CMD command prompt

```
cd D:\xampp8\htdocs 
mkdir modify_sql
cd modify_sql 
php modify_sql_ignor_id.php

```


### লোকাল পিসি মাল্টিপল ডাটা ইনসার্ট
``` bash
D:\xampp8\mysql\bin\mysql.exe -u root -v blink_dob < "G:\z-db\blink_dob\sdp_6d_callback_202507_02.sql" && ^
D:\xampp8\mysql\bin\mysql.exe -u root -v blink_dob < "G:\z-db\blink_dob\sdp_6d_callback_202507_04.sql" && ^
D:\xampp8\mysql\bin\mysql.exe -u root -v blink_dob < "G:\z-db\blink_dob\sdp_6d_callback_202507_10.sql"

```

### লোকাল পিসি মাল্টিপল ডাটাবেসে ডাটা ইনসার্ট
```bash
start "" cmd /c "D:\xampp8\mysql\bin\mysql.exe -u root  -v blink_dob < G:\z-db\blink_dob\sdp_6d_raw_consent_202507_10.sql 
start "" cmd /c "D:\xampp8\mysql\bin\mysql.exe -u root  -v gp_global < G:\z-db\gp_global\sdp_6d_callback_202507_04.sql"  
start "" cmd /c "D:\xampp8\mysql\bin\mysql.exe -u root  -v robi_sm < G:\z-db\robi_sm\sdp_6d_callback_202507_06.sql"

```


## টেবিল ডিলিট করা
```sql
DROP TABLE IF EXISTS table_name;
```

## MySQL টেবিল মেইনটেনেন্স

### একক টেবিল মেইনটেনেন্স
<p> ✅ 1. টেবিল চেক করা (Check if table has issues) </p>

  ```sql
  CHECK TABLE your_table_name;
  ```

  📝 ব্যাখ্যা: টেবিল খারাপ হয়েছে কিনা দেখে।

<p> ✅ 2. টেবিল রিপেয়ার করা (Repair broken tables) </p>

  ```sql
  REPAIR TABLE your_table_name;
  ```

  📝 ব্যাখ্যা: যদি টেবিলে সমস্যা পাওয়া যায়, তাহলে তা ঠিক করে।

<p> ✅ 3. টেবিল অপটিমাইজ করা (Clean & defragment) </p>

  ```sql
  OPTIMIZE TABLE your_table_name;
  ```
  📝 ব্যাখ্যা: ফাঁকা জায়গা মুছে দেয়, পারফরম্যান্স বাড়ায়।


<p> ✅ 4. টেবিল অ্যানালাইস করা (Update statistics) </p>

  ```sql
  ANALYZE TABLE your_table_name;
  ```

  📝 ব্যাখ্যা: MySQL-কে বলে কোন ইনডেক্স কতটা কার্যকর।


<p>  ✅ 5. ফ্লাশ টেবিল (Flush cache & write to disk) </p>

```sql
  FLUSH TABLES;
  ```
 
  📝 ব্যাখ্যা: ক্যাশ করা টেবিলগুলো ডিস্কে লেখা হয় এবং বন্ধ হয়।


<p>   ✅ 6. ইনডেক্স রিফ্রেশ (Manually re-create index if needed) </p>

  ```sql
  ALTER TABLE your_table_name ENGINE=InnoDB;
  ```

  📝 ব্যাখ্যা: একই ইঞ্জিনে রিক্রিয়েট করলে অনেক সময় ইনডেক্স গুছিয়ে ফেলে।

<p> ✅ 7. সব টেবিল একসাথে optimize/analyze/check করতে চাইলে (Command Line) </p>

```sql
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