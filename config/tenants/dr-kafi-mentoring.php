<?php

/*
|--------------------------------------------------------------------------
| Tenant: dr-kafi-mentoring  (production slug of drkafimentoring.ir)
|--------------------------------------------------------------------------
|
| Production's tenant row uses the slug `dr-kafi-mentoring`, and SiteConfig
| loads the tenant layer by slug (app/Support/SiteConfig.php), so that
| environment reads THIS file. It re-exports the tracked tenant config next
| door — one source of truth: edit config/tenants/drkafimentoring.php and
| every environment picks the change up on the next deploy.
|
| No overrides belong here; a divergence would recreate the drift this file
| exists to prevent.
|
*/

return require __DIR__.'/drkafimentoring.php';
