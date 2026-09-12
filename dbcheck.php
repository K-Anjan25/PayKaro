<?php

use App\Models\Buyer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$u = User::orderBy('id')->get();
echo 'db file     : '.config('database.connections.sqlite.database').PHP_EOL;
echo 'users       : '.$u->count().PHP_EOL;
echo 'invoices    : '.Invoice::withoutGlobalScopes()->count().PHP_EOL;
echo 'buyers      : '.Buyer::withoutGlobalScopes()->count().PHP_EOL;
foreach ($u as $x) {
    printf('  #%d tenant=%s %-28s passlen=%d bcrypt=%s check(demo1234)=%s%s%s'.PHP_EOL,
        $x->id, $x->business_id ?? 'NULL', $x->email,
        strlen((string) $x->getRawOriginal('password')),
        substr((string) $x->getRawOriginal('password'), 0, 7),
        $x->getRawOriginal('password') ? (Hash::check('demo1234', $x->getRawOriginal('password')) ? 'OK' : 'MISMATCH') : 'NULL',
        PHP_EOL, '   ');
}
echo 'migrations  : '.DB::table('migrations')->count().' ran'.PHP_EOL;
