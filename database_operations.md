# MySQL Table Archive & Export Guide

## Sample SQL Commands
```sql
mysql -u root -p'n0@ccess4U'
use blink_dob;
CREATE TABLE z_blink_dob_archive.sdp_6d_raw_subs_payment_202506_11_31 LIKE blink_dob.sdp_6d_raw_subs_payment;
INSERT INTO z_blink_dob_archive.sdp_6d_raw_subs_payment_202506_11_31 
SELECT * FROM blink_dob.sdp_6d_raw_subs_payment 
WHERE (`d_date` BETWEEN '2025-06-11' AND '2025-06-31 23:59:59');
