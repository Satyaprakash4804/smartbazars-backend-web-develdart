# SmartBazars backend — this update

No database migrations. See RUN_COMMANDS.md for the commands to run.

## Premium look for every web panel (admin · seller · partner · offline POS)
| File | Change |
|---|---|
| `public/assets/css/sb-premium.css` (new) | One theme stylesheet on top of Tabler: SmartBazars blue/orange palette, navy gradient sidebar with orange active marker, frosted sticky top bar, softer cards with hover lift, gradient primary buttons, rounded inputs with brand focus ring, clean uppercase table headers, styled DataTables search/paging, pill badges, rounded modals/dropdowns/SweetAlert, branded login pages, card entrance motion (reduced-motion respected), mobile fixes (stacked DataTables controls, horizontal table scroll, wrapping card headers/buttons, hidden version stamp in the mobile bar). Remove the `<link>` to go back to stock Tabler. |
| `resources/views/layouts/partials/_head.blade.php` | Loads `sb-premium.css` (all panels + login pages share this head). |

## User Card QR = holder details (online and offline cards)
| File | Change |
|---|---|
| `app/Models/UserCard.php` | New `qrPayload()`: line 1 `SMARTBAZARS:CARD:<number>` (for scanners), then Name, Card No, Mobile, Ward/Panchayat · Block/District · State (offline cards) or city/state/PIN (online cards), Valid upto, Verify link. Used for `qr_data` and `qr_svg` in the API (customer app + website). New `cardNumberFromScan()` and `isValidNow()`. |
| `app/Services/SmartBazars/OfflineCardService.php` | Printed/PDF offline card uses the same `qrPayload()`. |
| `app/Services/SmartBazars/OfflinePosService.php` | Card lookup understands the new multi-line QR, the old one-line QR, formatted/typed numbers and mobile numbers. |
| `resources/views/offline/partials/scanner.blade.php` | POS camera scanner extracts the card number from either QR format. |

Tested: QR decoded with zbar (incl. Hindi text); POS lookup works for new QR, old QR, typed number, mobile.

## Categories & products — dynamic, consistent, scalable
| File | Change |
|---|---|
| `app/Http/Controllers/Api/CategoryApiController.php` | Product counts now follow the SAME rules as the product list (approved + active, and sold by a store delivering to the location) – before, without a location every product counted (incl. unapproved), and a location with no stores counted everything. Counts include sub-categories at every depth (was one level). 2 grouped queries instead of 2 queries per category; category images eager-loaded. Order = admin sort order, then title. `sub-categories?filter=top_category` (home "All" tab) now lists sub-categories of every active top-level category instead of only the first one, and hides sub-categories whose parent is switched off. `home=true` falls back to all top-level categories when the admin has not picked any. `include_no_product=false` string now handled correctly. |
| `app/Models/Category.php` | New category: placed at the end of its level's sort order (not before the admin's sorted ones); a new top-level category is included in "home categories" when the admin uses that list (can be unticked). |
| `app/Http/Controllers/Api/Seller/SellerProductApiController.php` | Seller product list returns `verification_status` and `rejection_reason`, so the seller app can show whether customers can see a product. |

Tested on a local MariaDB copy with the demo seeder: new category + sub-category + product appear in `categories`, `categories?home=true`, `categories/sub-categories?filter=top_category` and the zone product list; zone without stores returns nothing.
Smoke tests (`phpunit.smoke.xml`): 3 pass; `PanelActionsTest` fails the same way on the original code (vendor KYC gate in demo data) – not caused by this update.

Known, not changed: `php artisan route:cache` fails because some API routes share names (e.g. `seller.api`, `earnings.date-range`) – pre-existing; the app runs fine without route caching.
