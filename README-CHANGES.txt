CHANGES — how to apply this package
====================================

1. Copy the contents of this zip into your project root, overwriting
   existing files at the matching paths.

2. DELETE this file manually — it's replaced by the two files below and
   a zip can't delete files for you:
       app/Http/Requests/Bilan/StoreBilanRequest.php

   It's replaced by:
       app/Http/Requests/Bilan/StoreBilanAmanaFoodRequest.php
       app/Http/Requests/Bilan/StoreBilanPresenceRequest.php

3. Run:
       php artisan migrate
       npm run type-check
       npm run build

Full file list in this package
-------------------------------
app/Helpers/AuditHelper.php
app/Http/Controllers/Admin/AuditLogController.php
app/Http/Controllers/BilanController.php
app/Http/Requests/Bilan/StoreBilanAmanaFoodRequest.php      (new)
app/Http/Requests/Bilan/StoreBilanPresenceRequest.php       (new)
app/Models/AuditLog.php
app/Models/Bilan.php
app/Services/AuditStatistics.php
database/migrations/2026_05_24_000000_create_audit_logs_table.php
database/migrations/2026_05_28_000003_refactor_auth_create_ref_applications.php
database/migrations/2026_07_01_000001_create_plan_bilans_quotidiens_table.php
database/seeders/TestBilansSeeder.php
public/favicon.ico                                          (overwritten — was empty)
public/apple-touch-icon.png                                 (new)
public/favicon-96x96.png                                    (new)
public/favicon.svg                                           (new)
public/site.webmanifest                                      (new)
public/web-app-manifest-192x192.png                          (new)
public/web-app-manifest-512x512.png                          (new)
resources/js/components/bilan/BilanView.vue
resources/views/bilan/index.blade.php
resources/views/echanges/token-result.blade.php
resources/views/layouts/partials/head.blade.php
resources/views/layouts/partials/sidebar.blade.php
resources/views/partials/head.blade.php
resources/views/partials/favicon.blade.php                   (new)
routes/web.php
