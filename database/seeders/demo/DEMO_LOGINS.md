# SmartBazars demo data — logins & test guide

Load it (after `php artisan migrate`):

```bash
php artisan db:seed --class=SmartBazarsDemoSeeder
php artisan storage:link
```
Safe to run again — it never duplicates rows.

## Logins
| Role | Where to log in | Email | Password |
|---|---|---|---|
| Super admin | `/admin/login` | admin@smartbazars.in *(only created if you have no admin yet)* | Admin@123 |
| Vendor – Delhi (Sharma Fresh Mart, Lajpat Nagar) | `/seller/login` | vendor.delhi@smartbazars.in | Vendor@123 |
| Vendor – Patna (Patliputra Kirana Bhandar, Boring Road) | `/seller/login` | vendor.patna@smartbazars.in | Vendor@123 |
| Top agent (directly under admin) – Rakesh Verma | `/partner/login` | agent@smartbazars.in | Agent@123 |
| Agent – Delhi (onboarded Delhi vendor) – Sunil Kumar | `/partner/login` | agent.delhi@smartbazars.in | Agent@123 |
| Agent – Bihar (onboarded Patna vendor) – Amit Raj | `/partner/login` | agent.bihar@smartbazars.in | Agent@123 |
| Sub-agent under Amit Raj – Pooja Singh | `/partner/login` | agent.sub@smartbazars.in | Agent@123 |
| Worker – Delhi vendor – Neha Gupta | `/partner/login` | worker.delhi@smartbazars.in | Worker@123 |
| Worker – Patna vendor – Rohit Kumar | `/partner/login` | worker.patna@smartbazars.in | Worker@123 |
| Delivery boy – Delhi NCR – Vikas Yadav | Rider app | rider.delhi@smartbazars.in | Rider@123 |
| Delivery boy – Bihar – Ravi Paswan | Rider app | rider.patna@smartbazars.in | Rider@123 |
| Customer – Lajpat Nagar, Delhi | App / website | customer.delhi@smartbazars.in (mobile 9000000051) | Customer@123 |
| Customer – Sector 62, Noida | App / website | customer.noida@smartbazars.in (9000000052) | Customer@123 |
| Customer – Boring Canal Road, Patna | App / website | customer.patna@smartbazars.in (9000000053) | Customer@123 |
| Customer – A.P. Colony, Gaya | App / website | customer.gaya@smartbazars.in (9000000054) | Customer@123 |

Every customer starts with **₹1,000 wallet** + **50 cashback coins** and a saved address.
Delhi/Noida customers are linked to worker Neha, Patna/Gaya customers to worker Rohit.

## What's inside
- **Zones:** *Delhi NCR* (Delhi, Noida, Gr. Noida, Gurugram, Ghaziabad, Faridabad, Meerut, Sonipat, Panipat,
  Bahadurgarh, Rewari, Palwal, Hapur, Bulandshahr, Alwar) and *Bihar (All Districts)*. Charges: ₹25 delivery,
  free above ₹299, ₹5 handling, rush delivery ₹49.
- **Stores:** Delhi store serves Delhi NCR, Patna store serves all of Bihar (each 30 products; 10 daily essentials in both).
- **Catalogue:** 10 categories, 31 brands, 50 products (MRP + offer price, stock, veg/non-veg, cashback coins, 2 images each).
- **Program:** agent tree (Rakesh → Sunil / Amit → Pooja), 2 workers, ₹250 Welcome Kit (5 items) live.
- **Home:** 3 banners, 3 product sections. Settings: INR ₹, Asia/Kolkata, wallet + COD on.

## 10-minute test script
1. Customer app/web → log in as customer.patna → set location Patna → products from Patliputra Kirana show.
2. Change location to Lucknow → "not deliverable" (outside both zones).
3. Account → SmartBazars User Card → buy kit with code of worker Rohit (see `/partner` as worker.patna) → card appears.
   Check `/partner` earnings: Rohit ₹30, Amit ₹5, Rakesh ₹2; Patna vendor statement ₹10.
4. Add items, turn on **Use cashback coins**, place COD order.
5. Patna vendor accepts → rider.patna delivers → customer gets cashback coins; Amit earns 1%, Rakesh 0.5%.
