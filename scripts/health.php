<?php
declare(strict_types=1);
$context=stream_context_create(['http'=>['timeout'=>5]]);
for ($i=0;$i<20;$i++) {
    $body=@file_get_contents('http://127.0.0.1:8080/api?action=bootstrap',false,$context);
    if ($body!==false) {
        $json=json_decode($body,true);
        if (isset($json['data']['csrf']) && is_array($json['data']['rooms']) && isset($json['data']['settings']['earn_per'])) {
            echo 'Ready: HTTP + database + configuration; '.count($json['data']['rooms'])." rooms.\n";
            exit(0);
        }
    }
    usleep(250000);
}
fwrite(STDERR,"Not ready. Check database, migrate and seed/configuration.\n");
exit(1);
