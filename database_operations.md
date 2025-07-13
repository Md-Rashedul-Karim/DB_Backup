# MySQL Table Archive & Export Guide

## Sample SQL Commands
```sql
mysql -u root -p'n0@ccess4U'
use blink_dob;
CREATE TABLE z_blink_dob_archive.sdp_6d_raw_subs_payment_202506_11_31 LIKE blink_dob.sdp_6d_raw_subs_payment;
INSERT INTO z_blink_dob_archive.sdp_6d_raw_subs_payment_202506_11_31 
SELECT * FROM blink_dob.sdp_6d_raw_subs_payment 
WHERE (`d_date` BETWEEN '2025-06-11' AND '2025-06-31 23:59:59');

## Run PHP Script (Archive Table)
php table_archive.php <source_db> <target_db> <main_table> <date_field> <from_date> <to_date> <table_suffix>

## Example Commands:
# blink_dob
php table_archive.php blink_dob z_blink_dob_archive sdp_6d_callback "d_date" "2025-07-01" "2025-07-02 23:59:59" "202507_01_02"

# robi
php table_archive.php robi_sm z_robi_sm_archive sdp_send_sms_log "d_date" "2025-07-07" "2025-07-08 23:59:59" "202507_07_08"

# gp_global
php table_archive.php gp_global z_gp_global_archive renews "created_at" "2025-07-01" "2025-07-02 23:59:59" "202507_01_02"

