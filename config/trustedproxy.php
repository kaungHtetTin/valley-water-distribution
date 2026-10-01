<?php

return [
    /*
     * Leave this unset when PHP receives HTTPS directly. When TLS terminates
     * at a load balancer or reverse proxy, list only its IP addresses or CIDR
     * ranges. Never trust every caller in production.
     */
    'proxies' => env('TRUSTED_PROXIES'),
];
