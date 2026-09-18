<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$n = Illuminate\Support\Facades\DB::table('student_deals')->count();
echo 'rows='.$n.PHP_EOL;
if ($n === 0) {
    Illuminate\Support\Facades\DB::statement('DROP TABLE `student_deals`');
    echo "dropped student_deals".PHP_EOL;
} else {
    echo "NOT dropped (non-empty)".PHP_EOL;
}
